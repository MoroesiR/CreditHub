<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LoanApplication;
use App\Services\Loans\ApplicationJourney;
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
            'initiation_fee' => $this->initiation_fee,
            'amount_financed' => $this->amount_financed,
            'monthly_service_fee' => $this->monthly_service_fee,
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

            // Whose hands the file has passed through. Built only when the
            // caller has loaded what it needs, so a list of fifty applications
            // does not turn into a few hundred queries.
            'journey' => $this->when(
                $this->relationLoaded('submittedBy'),
                fn () => app(ApplicationJourney::class)->for($this->resource),
            ),

            'client' => new ClientResource($this->whenLoaded('client')),
            'recruiter' => new RecruiterResource($this->whenLoaded('recruiter')),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
