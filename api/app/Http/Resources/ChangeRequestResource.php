<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Recruiter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChangeRequest
 */
class ChangeRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subject = $this->whenLoaded('subject');

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reason' => $this->reason,
            'changes' => $this->changes,
            'replaced_values' => $this->replaced_values,

            'subject_kind' => match ($this->subject_type) {
                Client::class => 'client',
                Recruiter::class => 'recruiter',
                default => 'record',
            },
            'subject_id' => $this->subject_id,
            'subject_label' => $this->when(
                $this->relationLoaded('subject') && $subject !== null,
                fn () => match (true) {
                    $subject instanceof Client => $subject->fullName().' ('.$subject->client_number.')',
                    $subject instanceof Recruiter => $subject->fullName().' ('.$subject->recruiter_number.')',
                    default => 'Record removed',
                },
            ),

            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($document): array => [
                'id' => $document->id,
                'type' => $document->type->value,
                'type_label' => $document->type->label(),
                'original_name' => $document->original_name,
                'size_bytes' => $document->size_bytes,
            ])->values()),

            'requested_by' => $this->whenLoaded('requestedBy', fn () => $this->requestedBy?->fullName()),
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy?->fullName()),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_note' => $this->review_note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
