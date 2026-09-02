<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'version', 'is_active', 'effective_from'])]
class CommissionScheme extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'effective_from' => 'immutable_date',
        ];
    }

    /**
     * @return HasMany<CommissionSchemeTier, $this>
     */
    public function tiers(): HasMany
    {
        return $this->hasMany(CommissionSchemeTier::class)->orderBy('min_amount');
    }
}
