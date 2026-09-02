<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Applications;

use App\Enums\ApplicationDocumentType;
use App\Services\Loans\ApplicationDocumentStore;
use App\Services\Loans\InstalmentCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanApplicationRequest extends FormRequest
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
            'amount' => [
                'required',
                'numeric',
                'min:'.InstalmentCalculator::MIN_AMOUNT,
                'max:'.InstalmentCalculator::MAX_AMOUNT,
            ],
            'term_months' => [
                'required',
                'integer',
                'min:'.InstalmentCalculator::MIN_TERM_MONTHS,
                'max:'.InstalmentCalculator::MAX_TERM_MONTHS,
            ],
            'purpose' => ['nullable', 'string', 'max:255'],
            // interest_rate is absent by design: the rate is set by the lender,
            // not per file, so it cannot be negotiated on a single application.

            // All three supporting documents are required at submission: a
            // file assessed without them is one the lender cannot defend.
            ...$this->documentRules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentRules(): array
    {
        $rules = [];

        foreach (ApplicationDocumentType::cases() as $type) {
            $rules["documents.{$type->value}"] = [
                'required',
                'file',
                // Checked on content, not on the filename's extension.
                'mimes:'.implode(',', ApplicationDocumentStore::ALLOWED_EXTENSIONS),
                'max:'.ApplicationDocumentStore::MAX_KILOBYTES,
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (ApplicationDocumentType::cases() as $type) {
            $attributes["documents.{$type->value}"] = strtolower($type->label());
        }

        return $attributes;
    }
}
