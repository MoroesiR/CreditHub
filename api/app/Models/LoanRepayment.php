<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RepaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'loan_application_id', 'amount', 'fee_portion', 'interest_portion', 'capital_portion',
    'received_on', 'method',
    'reference', 'note', 'reverses_id', 'recorded_by',
])]
class LoanRepayment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'fee_portion' => 'float',
            'interest_portion' => 'float',
            'capital_portion' => 'float',
            'received_on' => 'immutable_date',
            'method' => RepaymentMethod::class,
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
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /**
     * @return HasOne<self, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    public function isReversal(): bool
    {
        return $this->reverses_id !== null;
    }
}
