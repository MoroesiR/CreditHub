<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CommissionScheme;
use Illuminate\Database\Seeder;

/**
 * The commission scheme in force.
 *
 * A flat percentage pays R5 000 on a R50 000 loan, which no lender would
 * carry, so the rate tapers and each tier above the entry band is capped. The
 * entry band stays at a flat 10%: R1 000 introduced earns R100.
 */
class CommissionSchemeSeeder extends Seeder
{
    public function run(): void
    {
        $scheme = CommissionScheme::updateOrCreate(
            ['name' => 'Standard', 'version' => 1],
            ['is_active' => true, 'effective_from' => '2026-01-01'],
        );

        $tiers = [
            ['min_amount' => 0, 'max_amount' => 2000, 'rate_percent' => 10.00, 'cap_amount' => null],
            ['min_amount' => 2000.01, 'max_amount' => 10000, 'rate_percent' => 7.00, 'cap_amount' => 900],
            ['min_amount' => 10000.01, 'max_amount' => 50000, 'rate_percent' => 5.00, 'cap_amount' => 2500],
            ['min_amount' => 50000.01, 'max_amount' => null, 'rate_percent' => 3.00, 'cap_amount' => 4000],
        ];

        foreach ($tiers as $tier) {
            $scheme->tiers()->updateOrCreate(
                ['min_amount' => $tier['min_amount']],
                $tier,
            );
        }
    }
}
