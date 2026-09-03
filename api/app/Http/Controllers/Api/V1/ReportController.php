<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reporting\PortfolioReport;
use Illuminate\Http\JsonResponse;

final class ReportController extends Controller
{
    public function __construct(
        private readonly PortfolioReport $portfolio,
    ) {}

    public function portfolio(): JsonResponse
    {
        return response()->json(['data' => $this->portfolio->build()]);
    }
}
