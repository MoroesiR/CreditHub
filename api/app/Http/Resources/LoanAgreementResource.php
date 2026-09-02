<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LoanAgreement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoanAgreement
 */
class LoanAgreementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agreement_number' => $this->agreement_number,
            'loan_application_id' => $this->loan_application_id,

            'amount' => $this->amount,
            'term_months' => $this->term_months,
            'interest_rate' => $this->interest_rate,
            'monthly_instalment' => $this->monthly_instalment,
            'total_repayable' => $this->total_repayable,

            'generated_at' => $this->generated_at?->toIso8601String(),
            'signed_name' => $this->signed_name,
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signed_ip' => $this->signed_ip,
            'witnessed_by' => $this->whenLoaded('witnessedBy', fn () => $this->witnessedBy?->fullName()),
            'is_signed' => $this->isSigned(),
            // Flags only: the images themselves are fetched through an
            // authenticated route, never linked by path.
            'has_signature' => $this->signature_path !== null,
            'has_photo' => $this->photo_path !== null,
        ];
    }
}
