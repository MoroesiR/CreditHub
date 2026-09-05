<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'loan_application_id', 'instalment_number', 'due_on',
    'service_fee_due', 'interest_due', 'capital_due', 'total_due', 'closing_balance',
])]
class LoanScheduleEntry extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_on' => 'immutable_date',
            'service_fee_due' => 'float',
            'interest_due' => 'float',
            'capital_due' => 'float',
            'total_due' => 'float',
            'closing_balance' => 'float',
        ];
    }

    /**
     * @return BelongsTo<LoanApplication, $this>
     */
    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }
}
