<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoanApplication
 */
class LoanApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_number' => $this->application_number,

            'amount' => $this->amount,
            'term_months' => $this->term_months,
            'interest_rate' => $this->interest_rate,
            'monthly_instalment' => $this->monthly_instalment,
            'total_repayable' => $this->total_repayable,
            'disposable_income_at_capture' => $this->disposable_income_at_capture,
            'purpose' => $this->purpose,

            'status' => $this->status->value,
            'status_label' => $this->status->label(),

            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'submitted_by' => $this->whenLoaded('submittedBy', fn () => $this->submittedBy?->fullName()),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'decided_by' => $this->whenLoaded('decidedBy', fn () => $this->decidedBy?->fullName()),
            'decline_reason' => $this->decline_reason,

            'documents' => ApplicationDocumentResource::collection($this->whenLoaded('documents')),

            'client' => new ClientResource($this->whenLoaded('client')),
            'recruiter' => new RecruiterResource($this->whenLoaded('recruiter')),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
