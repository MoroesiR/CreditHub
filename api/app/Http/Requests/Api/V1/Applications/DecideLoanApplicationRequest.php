<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Applications;

use Illuminate\Foundation\Http\FormRequest;

class DecideLoanApplicationRequest extends FormRequest
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
            'approved' => ['required', 'boolean'],
            // A decline has to say why: the client is entitled to a reason and
            // the file has to carry it.
            'decline_reason' => ['nullable', 'required_if:approved,false', 'string', 'max:255'],
        ];
    }
}
