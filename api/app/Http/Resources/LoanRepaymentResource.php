<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LoanRepayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoanRepayment
 */
class LoanRepaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            // How the receipt was split, in the order the Act prescribes.
            'fee_portion' => $this->fee_portion,
            'interest_portion' => $this->interest_portion,
            'capital_portion' => $this->capital_portion,
            'received_on' => $this->received_on?->toDateString(),
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'reference' => $this->reference,
            'note' => $this->note,
            'is_reversal' => $this->isReversal(),
            'reverses_id' => $this->reverses_id,
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy?->fullName()),
            'recorded_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
