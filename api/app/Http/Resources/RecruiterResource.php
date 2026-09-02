<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Recruiter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Recruiter
 */
class RecruiterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recruiter_number' => $this->recruiter_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'id_number' => $this->id_number,
            'phone' => $this->phone,
            'email' => $this->email,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'bank_branch_code' => $this->bank_branch_code,
            'is_active' => $this->is_active,
            'clients_count' => $this->whenCounted('clients'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
