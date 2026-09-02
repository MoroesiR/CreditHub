<?php

declare(strict_types=1);

namespace App\Services\Commissions;

use App\Models\CommissionScheme;
use App\Models\CommissionSchemeTier;
use RuntimeException;

/**
 * Prices a recruiter's commission on a disbursed loan.
 *
 * A flat percentage pays R5 000 on a R50 000 loan, which no lender carries, so
 * the rate tapers and every band above the entry one is capped. The entry band
 * stays flat: R1 000 introduced earns R100.
 *
 * The scheme used is returned alongside the figure so the caller can record
 * which version priced the payout. Without that, a historical commission
 * cannot be restated once the scheme changes.
 */
final class CommissionCalculator
{
    /**
     * @return array{scheme: CommissionScheme, rate: float, amount: float, capped: bool}
     */
    public function calculate(float $loanAmount): array
    {
        $scheme = CommissionScheme::where('is_active', true)
            ->with('tiers')
            ->orderByDesc('version')
            ->first();

        if ($scheme === null) {
            throw new RuntimeException('No active commission scheme is configured.');
        }

        $tier = $scheme->tiers->first(fn (CommissionSchemeTier $tier): bool => $loanAmount >= $tier->min_amount
            && ($tier->max_amount === null || $loanAmount <= $tier->max_amount));

        if ($tier === null) {
            throw new RuntimeException(sprintf(
                'No commission tier covers a loan of R%s under scheme %s v%d.',
                number_format($loanAmount, 2),
                $scheme->name,
                $scheme->version,
            ));
        }

        $uncapped = round($loanAmount * $tier->rate_percent / 100, 2);
        $capped = $tier->cap_amount !== null && $uncapped > $tier->cap_amount;

        return [
            'scheme' => $scheme,
            'rate' => $tier->rate_percent,
            'amount' => $capped ? (float) $tier->cap_amount : $uncapped,
            'capped' => $capped,
        ];
    }
}
