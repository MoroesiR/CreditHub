<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Recruiters;

use App\Rules\SouthAfricanIdNumber;
use App\Support\SouthAfricanBanks;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecruiterRequest extends FormRequest
{
    /**
     * Authorisation is declared on the route; reaching a controller means it
     * has already passed.
     */
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'id_number' => [
                'required',
                'string',
                new SouthAfricanIdNumber(),
                Rule::unique('recruiters', 'id_number')->whereNull('deleted_at'),
            ],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            // Branch code is derived from the bank, never accepted.
            'bank_name' => ['nullable', Rule::in(SouthAfricanBanks::names())],
            'bank_account_number' => ['nullable', 'required_with:bank_name', 'digits_between:6,20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_number.unique' => 'A recruiter with this ID number is already registered.',
        ];
    }
}
