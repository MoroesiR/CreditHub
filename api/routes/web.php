<?php

declare(strict_types=1);

use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

/*
| The API serves JSON under /api/v1. Where the built React application has
| been copied into public/, every other path hands back its index.html so the
| browser can route it. Health checks use /up.
*/

Route::get('/', SpaController::class);
Route::fallback(SpaController::class);
