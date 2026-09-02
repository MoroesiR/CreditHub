<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| This application serves JSON only; the user interface is the React SPA in
| /web. The single route below identifies the service to anything that lands
| on the root URL. Health checks use /up.
*/

Route::get('/', fn (): array => [
    'service' => config('app.name'),
    'api' => url('/api/v1'),
    'health' => url('/up'),
]);
