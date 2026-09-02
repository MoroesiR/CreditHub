<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'loan_application_id', 'agreement_number', 'amount', 'term_months', 'interest_rate',
    'monthly_instalment', 'total_repayable', 'generated_at', 'generated_by',
    'signature_path', 'photo_path', 'signed_name', 'signed_at', 'witnessed_by', 'signed_ip',
])]
class LoanAgreement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'interest_rate' => 'float',
            'monthly_instalment' => 'float',
            'total_repayable' => 'float',
            'generated_at' => 'immutable_datetime',
            'signed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<LoanApplication, $this>
     */
    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function witnessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witnessed_by');
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }
}
