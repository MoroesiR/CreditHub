<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardSummaryService $summary,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->summary->forUser($request->user()),
        ]);
    }
}
