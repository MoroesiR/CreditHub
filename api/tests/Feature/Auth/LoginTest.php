<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Support\Facades\Hash;

it('issues a token and returns the signed-in user', function (): void {
    $user = User::factory()->withRole(Roles::LOAN_OFFICER)->create([
        'email' => 'sipho@credithub.test',
        'password' => Hash::make('correct-horse'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'sipho@credithub.test',
        'password' => 'correct-horse',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'email', 'roles', 'permissions']])
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.roles.0.slug', Roles::LOAN_OFFICER);

    expect($response->json('user.permissions'))
        ->toContain(Permissions::CLIENTS_CREATE)
        ->not->toContain(Permissions::DISBURSEMENTS_PAY);
});

it('stamps the sign-in time', function (): void {
    $user = User::factory()->create([
        'email' => 'sipho@credithub.test',
        'password' => Hash::make('correct-horse'),
        'last_login_at' => null,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'sipho@credithub.test',
        'password' => 'correct-horse',
    ])->assertOk();

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects a wrong password without revealing whether the account exists', function (): void {
    User::factory()->create([
        'email' => 'sipho@credithub.test',
        'password' => Hash::make('correct-horse'),
    ]);

    $wrongPassword = $this->postJson('/api/v1/auth/login', [
        'email' => 'sipho@credithub.test',
        'password' => 'guess',
    ])->assertUnprocessable();

    $unknownAccount = $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@credithub.test',
        'password' => 'guess',
    ])->assertUnprocessable();

    expect($wrongPassword->json('message'))->toBe($unknownAccount->json('message'));
});

it('refuses a deactivated account', function (): void {
    User::factory()->inactive()->create([
        'email' => 'former.staff@credithub.test',
        'password' => Hash::make('correct-horse'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'former.staff@credithub.test',
        'password' => 'correct-horse',
    ])->assertUnprocessable()->assertJsonValidationErrorFor('email');
});

it('requires an email and a password', function (): void {
    $this->postJson('/api/v1/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('throttles repeated failures for one email', function (): void {
    User::factory()->create([
        'email' => 'sipho@credithub.test',
        'password' => Hash::make('correct-horse'),
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'sipho@credithub.test',
            'password' => 'guess',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'sipho@credithub.test',
        'password' => 'correct-horse',
    ])->assertStatus(429);
});
