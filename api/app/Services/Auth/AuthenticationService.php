<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Credential checking and token issue.
 *
 * Kept out of the controller so the rules - what a valid sign-in is, what a
 * token may do - are stated once and can be tested without an HTTP request.
 */
final class AuthenticationService
{
    /**
     * @return array{user: User, token: NewAccessToken}
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password, string $deviceName): array
    {
        $user = User::query()->where('email', $email)->first();

        // One message for "no such account" and "wrong password" alike: telling
        // the two apart hands an attacker a list of valid staff emails.
        if (! $user instanceof User || ! Hash::check($password, $user->password)) {
            Event::dispatch(new Failed('web', $user, ['email' => $email]));

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => __('This account has been deactivated. Contact your administrator.'),
            ]);
        }

        $user->load('roles.permissions');

        $user->forceFill(['last_login_at' => now()])->save();

        Event::dispatch(new Login('web', $user, false));

        return [
            'user' => $user,
            // Abilities mirror the account's permissions, so a leaked token can
            // never do more than the person it was issued to.
            'token' => $user->createToken($deviceName, $user->permissionSlugs()),
        ];
    }

    /**
     * Revoke only the token that made the request, leaving other devices
     * signed in.
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        // Guarded because currentAccessToken() also yields a TransientToken on
        // cookie-authenticated requests, which has nothing to revoke.
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
