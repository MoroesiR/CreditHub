<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Fail loudly in development on a lazily-loaded relation or a mass
        // assignment that was never declared, rather than shipping the N+1.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Credential stuffing protection: five attempts a minute per email and
        // per source address, which is generous for a person and useless to a
        // script.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by($request->string('email')->lower()->value()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
    }
}
