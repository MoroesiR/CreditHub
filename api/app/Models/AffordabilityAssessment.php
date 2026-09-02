<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'client_id', 'gross_monthly_income', 'net_monthly_income', 'monthly_living_expenses',
    'monthly_debt_repayments', 'disposable_income', 'assessed_by', 'assessed_at',
])]
class AffordabilityAssessment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_monthly_income' => 'float',
            'net_monthly_income' => 'float',
            'monthly_living_expenses' => 'float',
            'monthly_debt_repayments' => 'float',
            'disposable_income' => 'float',
            'assessed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
