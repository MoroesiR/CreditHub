<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\ChangeRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChangeRequestRequest extends FormRequest
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
            'subject_kind' => ['required', Rule::in(['client', 'recruiter'])],
            'subject_id' => ['required', 'integer'],
            // A reason is required. An administrator deciding a correction
            // needs to know why it was asked for, not only what would change.
            'reason' => ['required', 'string', 'max:255'],
            'changes' => ['required', 'array', 'min:1'],
            'changes.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'changes.required' => 'Set at least one field to a new value.',
            'reason.required' => 'Say why the change is needed.',
        ];
    }
}
