<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_number' => $this->client_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'id_number' => $this->id_number,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            // Derived on read: an age stored at registration would be wrong
            // by the next birthday.
            'age' => $this->date_of_birth?->age,
            'phone' => $this->phone,
            'email' => $this->email,
            'city' => $this->city,
            'province' => $this->province,
            'country' => $this->country,
            'employer_name' => $this->employer_name,
            'employment_status' => $this->employment_status,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'bank_branch_code' => $this->bank_branch_code,
            'recruiter' => new RecruiterResource($this->whenLoaded('recruiter')),
            'affordability' => new AffordabilityAssessmentResource($this->whenLoaded('latestAffordability')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
