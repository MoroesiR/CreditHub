<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AffordabilityAssessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AffordabilityAssessment
 */
class AffordabilityAssessmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gross_monthly_income' => $this->gross_monthly_income,
            'net_monthly_income' => $this->net_monthly_income,
            'monthly_living_expenses' => $this->monthly_living_expenses,
            'monthly_debt_repayments' => $this->monthly_debt_repayments,
            'disposable_income' => $this->disposable_income,
            'assessed_at' => $this->assessed_at?->toIso8601String(),
        ];
    }
}
