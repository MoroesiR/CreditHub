<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SpaController extends Controller
{
    public function __invoke(Request $request): Response|JsonResponse
    {
        abort_if($request->is('api/*'), 404);

        $page = public_path('index.html');

        if (! file_exists($page)) {
            return response()->json([
                'service' => config('app.name'),
                'api' => url('/api/v1'),
                'health' => url('/up'),
            ]);
        }

        return response(
            (string) file_get_contents($page),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
