<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DisbursementStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'loan_application_id', 'amount', 'status',
    'verified_at', 'verified_by', 'paid_at', 'paid_by', 'payment_reference',
    'paid_to_bank_name', 'paid_to_account_number', 'paid_to_branch_code', 'hold_reason',
])]
class Disbursement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'status' => DisbursementStatus::class,
            'verified_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
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
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
