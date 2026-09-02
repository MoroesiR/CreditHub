<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Agreements;

use Illuminate\Foundation\Http\FormRequest;

class SignAgreementRequest extends FormRequest
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
            'signed_name' => ['required', 'string', 'max:255'],
            // Data URLs. The contents are decoded and re-checked in the
            // service; nothing here trusts the declared type.
            'signature' => ['required', 'string', 'starts_with:data:image/'],
            'photo' => ['nullable', 'string', 'starts_with:data:image/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'signature.required' => 'The client must sign before the agreement can be submitted.',
            'signed_name.required' => 'Type the name of the person signing.',
        ];
    }
}
