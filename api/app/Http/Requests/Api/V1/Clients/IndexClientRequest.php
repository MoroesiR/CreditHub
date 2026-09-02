<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Clients;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'recruiter_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            // Whitelisted rather than passed through: an unchecked sort column
            // is an injection point.
            'sort' => ['nullable', Rule::in(['created_at', 'last_name', 'client_number'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
