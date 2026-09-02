<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Commission
 */
class CommissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_amount' => $this->loan_amount,
            'rate_applied' => $this->rate_applied,
            'amount' => $this->amount,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'calculated_at' => $this->calculated_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'recruiter' => new RecruiterResource($this->whenLoaded('recruiter')),
            'application' => new LoanApplicationResource($this->whenLoaded('loanApplication')),
        ];
    }
}
