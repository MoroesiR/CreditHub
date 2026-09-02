<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApplicationDocument
 */
class ApplicationDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'uploaded_at' => $this->created_at?->toIso8601String(),
            // The storage path is deliberately absent: the file is fetched
            // through a route that checks permission, never by path.
        ];
    }
}
