<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\SouthAfricanBanks;
use App\Support\SouthAfricanProvinces;
use Illuminate\Http\JsonResponse;

/**
 * Reference data the SPA needs to render its forms. Read-only, and available
 * to anyone signed in.
 *
 * Served from the API rather than hardcoded in the SPA so that the dropdown a
 * user picks from and the list the validator checks against are the same list.
 */
final class ReferenceController extends Controller
{
    public function banks(): JsonResponse
    {
        return response()->json(['data' => SouthAfricanBanks::forSelect()]);
    }

    public function provinces(): JsonResponse
    {
        return response()->json([
            'data' => [
                'provinces' => SouthAfricanProvinces::all(),
                'country' => SouthAfricanProvinces::COUNTRY,
            ],
        ]);
    }
}
