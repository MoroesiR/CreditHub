<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Clients;

use App\Rules\SouthAfricanIdNumber;
use App\Support\IdentityNumber;
use App\Support\SouthAfricanBanks;
use App\Support\SouthAfricanProvinces;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClientRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'id_number' => [
                'required',
                'string',
                new SouthAfricanIdNumber,
                Rule::unique('clients', 'id_number')->whereNull('deleted_at'),
            ],
            // date_of_birth and gender are absent by design: both are read out
            // of the ID number by the service. Accepting them would allow a
            // record whose stated date of birth contradicts its own ID.
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            // A closed list: free text produces "KZN" and "KwaZulu-Natal" in
            // the same column and no report can be run over it afterwards.
            'province' => ['nullable', Rule::in(SouthAfricanProvinces::all())],
            'postal_code' => ['nullable', 'digits:4'],
            // country is absent by design: the lender operates in one country
            // and the service sets it.

            'employer_name' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'employment_status' => ['required', Rule::in(['permanent', 'contract', 'self_employed', 'pensioner'])],

            // The branch code is deliberately not accepted from the caller: it
            // is looked up from the chosen bank, so a mistyped code can never
            // reach a payout instruction.
            'bank_name' => ['nullable', Rule::in(SouthAfricanBanks::names())],
            'bank_account_number' => ['nullable', 'required_with:bank_name', 'digits_between:6,20'],

            'recruiter_id' => ['nullable', 'integer', Rule::exists('recruiters', 'id')->whereNull('deleted_at')],

            'new_recruiter' => ['nullable', 'array'],
            'new_recruiter.first_name' => ['required_with:new_recruiter', 'string', 'max:255'],
            'new_recruiter.last_name' => ['required_with:new_recruiter', 'string', 'max:255'],
            'new_recruiter.id_number' => [
                'required_with:new_recruiter',
                'string',
                new SouthAfricanIdNumber,
                Rule::unique('recruiters', 'id_number')->whereNull('deleted_at'),
            ],
            'new_recruiter.phone' => ['required_with:new_recruiter', 'string', 'max:20'],
            'new_recruiter.email' => ['nullable', 'email', 'max:255'],
            'new_recruiter.bank_name' => ['nullable', Rule::in(SouthAfricanBanks::names())],
            'new_recruiter.bank_account_number' => [
                'nullable',
                'required_with:new_recruiter.bank_name',
                'digits_between:6,20',
            ],

            'affordability' => ['required', 'array'],
            'affordability.gross_monthly_income' => ['required', 'numeric', 'min:0'],
            'affordability.net_monthly_income' => ['required', 'numeric', 'min:0'],
            'affordability.monthly_living_expenses' => ['required', 'numeric', 'min:0'],
            'affordability.monthly_debt_repayments' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('recruiter_id') && $this->filled('new_recruiter')) {
                $validator->errors()->add(
                    'recruiter_id',
                    'Link an existing recruiter or register a new one, not both.',
                );
            }

            // A credit agreement cannot be concluded with a minor, and the ID
            // number already states the age - so it is checked here rather
            // than left to the officer reading the age off the screen.
            $age = IdentityNumber::age((string) $this->input('id_number', ''));

            if ($age !== null && $age < 18) {
                $validator->errors()->add(
                    'id_number',
                    "This ID number belongs to someone aged {$age}. A client must be 18 or older.",
                );
            }

            $net = (float) $this->input('affordability.net_monthly_income', 0);
            $gross = (float) $this->input('affordability.gross_monthly_income', 0);

            if ($net > $gross) {
                $validator->errors()->add(
                    'affordability.net_monthly_income',
                    'Net income cannot exceed gross income.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_number.unique' => 'A client with this ID number is already registered.',
            'new_recruiter.id_number.unique' => 'That recruiter is already registered. Link them instead.',
        ];
    }
}
