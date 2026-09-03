<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:255'],
            // Staff hold client records and the ability to move money, so the
            // floor is well above the framework default.
            //
            // uncompromised() checks the password against known breach data,
            // but it fails open: if the lookup cannot be made the password is
            // treated as clean. The local rules below therefore have to stand
            // on their own, and the breach check is a bonus when the host can
            // reach the service.
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'slug')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.required' => 'An account with no role can sign in and do nothing. Choose at least one.',
            'password.uncompromised' => 'That password appears in a known breach. Choose another.',
        ];
    }
}
