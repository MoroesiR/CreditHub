<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'loan_application_id', 'recruiter_id', 'commission_scheme_id', 'loan_amount',
    'rate_applied', 'amount', 'status', 'calculated_at', 'paid_at', 'paid_by',
])]
class Commission extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'loan_amount' => 'float',
            'rate_applied' => 'float',
            'amount' => 'float',
            'status' => CommissionStatus::class,
            'calculated_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Recruiter, $this>
     */
    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(Recruiter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * @return BelongsTo<LoanApplication, $this>
     */
    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }
}
