<?php

declare(strict_types=1);

namespace App\Services\Loans;

/**
 * Prices a loan.
 *
 * Reducing balance, the way an amortising credit agreement actually works:
 * interest is charged on what is still owed, not on the original advance, so
 * the instalment is lower than a flat-rate calculation would suggest and the
 * quote survives comparison with any other lender's.
 *
 * The rate is held here rather than typed in per application. An officer who
 * can set the rate on a file can price a loan differently for one client than
 * another, which is exactly what a lender must be able to show it does not do.
 */
final class InstalmentCalculator
{
    /** Annual nominal rate, as a percentage. */
    public const ANNUAL_RATE = 28.75;

    public const MIN_AMOUNT = 500.00;

    public const MAX_AMOUNT = 250000.00;

    public const MIN_TERM_MONTHS = 1;

    public const MAX_TERM_MONTHS = 72;

    /**
     * @return array{monthly_instalment: float, total_repayable: float, total_interest: float, interest_rate: float}
     */
    public function quote(float $amount, int $termMonths, ?float $annualRate = null): array
    {
        $rate = $annualRate ?? self::ANNUAL_RATE;
        $monthlyRate = $rate / 100 / 12;

        // A zero rate would divide by zero in the amortisation formula, so the
        // interest-free case is handled on its own terms.
        $instalment = $monthlyRate === 0.0
            ? $amount / $termMonths
            : $amount * $monthlyRate / (1 - (1 + $monthlyRate) ** -$termMonths);

        $instalment = round($instalment, 2);
        $totalRepayable = round($instalment * $termMonths, 2);

        return [
            'monthly_instalment' => $instalment,
            'total_repayable' => $totalRepayable,
            'total_interest' => round($totalRepayable - $amount, 2),
            'interest_rate' => $rate,
        ];
    }

    /**
     * Whether the instalment fits inside what the client has left each month.
     */
    public function isAffordable(float $instalment, float $disposableIncome): bool
    {
        return $instalment <= $disposableIncome;
    }
}
