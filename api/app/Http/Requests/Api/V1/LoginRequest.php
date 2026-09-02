<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            // Labels the issued token in the user's session list.
            'device_name' => ['sometimes', 'string', 'max:64'],
        ];
    }

    public function deviceName(): string
    {
        return $this->string('device_name')->value() ?: 'web';
    }
}
