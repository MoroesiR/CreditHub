<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Recruiters;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkClientRequest extends FormRequest
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
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
