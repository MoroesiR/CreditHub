<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Repayments;

use App\Enums\RepaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            // Backdating is allowed because receipts are often captured from a
            // statement days later. A future date is not, since money cannot
            // have been received on a day that has not happened.
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(RepaymentMethod::selectable())],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'received_on.before_or_equal' => 'A repayment cannot be dated in the future.',
        ];
    }
}
