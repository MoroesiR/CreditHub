<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChangeRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'subject_type', 'subject_id', 'requested_by', 'reason', 'changes',
    'status', 'reviewed_by', 'reviewed_at', 'review_note', 'replaced_values',
])]
class ChangeRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'replaced_values' => 'array',
            'status' => ChangeRequestStatus::class,
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<ChangeRequestDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ChangeRequestDocument::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === ChangeRequestStatus::Pending;
    }
}
