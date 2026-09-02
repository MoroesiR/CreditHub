<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivating an account must take effect immediately, not whenever the
 * holder's token happens to expire, so every authenticated request re-checks.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            abort(Response::HTTP_FORBIDDEN, 'This account has been deactivated.');
        }

        return $next($request);
    }
}
