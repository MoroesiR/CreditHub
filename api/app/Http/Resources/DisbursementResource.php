<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Disbursement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Disbursement
 */
class DisbursementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),

            'verified_at' => $this->verified_at?->toIso8601String(),
            'verified_by' => $this->whenLoaded('verifiedBy', fn () => $this->verifiedBy?->fullName()),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'paid_by' => $this->whenLoaded('paidBy', fn () => $this->paidBy?->fullName()),
            'payment_reference' => $this->payment_reference,
            'paid_to_bank_name' => $this->paid_to_bank_name,
            'paid_to_account_number' => $this->paid_to_account_number,
            'paid_to_branch_code' => $this->paid_to_branch_code,
            'hold_reason' => $this->hold_reason,

            'application' => new LoanApplicationResource($this->whenLoaded('loanApplication')),

            // Present once the loan is paid and the introduction earned one, so
            // the payouts desk can be taken straight to it instead of having to
            // go and find it - a commission left owing by oversight is the
            // failure this guards against.
            'commission' => $this->when(
                $this->relationLoaded('loanApplication')
                    && $this->loanApplication?->relationLoaded('commission')
                    && $this->loanApplication->commission !== null,
                fn () => new CommissionResource($this->loanApplication->commission),
            ),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
