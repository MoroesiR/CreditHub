<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['commission_scheme_id', 'min_amount', 'max_amount', 'rate_percent', 'cap_amount'])]
class CommissionSchemeTier extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_amount' => 'float',
            'max_amount' => 'float',
            'rate_percent' => 'float',
            'cap_amount' => 'float',
        ];
    }

    /**
     * @return BelongsTo<CommissionScheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(CommissionScheme::class, 'commission_scheme_id');
    }
}
