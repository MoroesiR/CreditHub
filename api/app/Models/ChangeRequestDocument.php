<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'change_request_id', 'type', 'original_name', 'path',
    'mime_type', 'size_bytes', 'uploaded_by',
])]
class ChangeRequestDocument extends Model
{
    /**
     * The same three kinds a loan application carries, because this document
     * replaces one of those when the request is approved.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => ApplicationDocumentType::class];
    }

    /**
     * @return BelongsTo<ChangeRequest, $this>
     */
    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }
}
