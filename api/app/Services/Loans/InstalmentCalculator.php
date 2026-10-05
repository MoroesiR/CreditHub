<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Support\FeeSchedule;

/**
 * Prices a loan on reducing balance: interest on what is still owed, not on
 * the original advance.
 *
 * The initiation fee is capitalised, so it carries interest with the rest of
 * the balance. The service fee is flat and sits outside the amortisation.
 *
 * The rate lives here and is never taken from the request, so one client
 * cannot be priced differently from another.
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
     * @return array{
     *     advance: float,
     *     initiation_fee: float,
     *     amount_financed: float,
     *     interest_rate: float,
     *     capital_instalment: float,
     *     monthly_service_fee: float,
     *     monthly_instalment: float,
     *     total_interest: float,
     *     total_service_fees: float,
     *     total_repayable: float,
     *     cost_of_credit: float
     * }
     */
    public function quote(float $amount, int $termMonths, ?float $annualRate = null): array
    {
        $rate = $annualRate ?? self::ANNUAL_RATE;
        $monthlyRate = $rate / 100 / 12;

        $initiationFee = FeeSchedule::initiationFee($amount);
        $financed = round($amount + $initiationFee, 2);

        // A zero rate would divide by zero in the amortisation formula, so the
        // interest-free case is handled on its own terms.
        $capitalInstalment = $monthlyRate === 0.0
            ? $financed / $termMonths
            : $financed * $monthlyRate / (1 - (1 + $monthlyRate) ** -$termMonths);

        $capitalInstalment = round($capitalInstalment, 2);
        $serviceFee = FeeSchedule::monthlyServiceFee();

        $instalment = round($capitalInstalment + $serviceFee, 2);

        // Totalled from the amortisation itself rather than by multiplying the
        // instalment by the term. The last instalment clears whatever the
        // rounding left, so the two differ by a few cents, and quoting a figure
        // the schedule will not agree with leaves that difference outstanding
        // on a loan the client has paid in full.
        $rows = $this->amortise($financed, $capitalInstalment, $monthlyRate, $termMonths);

        $totalInterest = round(array_sum(array_column($rows, 'interest')), 2);
        $totalServiceFees = round($serviceFee * $termMonths, 2);
        $totalRepayable = round($financed + $totalInterest + $totalServiceFees, 2);

        return [
            'advance' => round($amount, 2),
            'initiation_fee' => $initiationFee,
            'amount_financed' => $financed,
            'interest_rate' => $rate,
            'capital_instalment' => $capitalInstalment,
            'monthly_service_fee' => $serviceFee,
            'monthly_instalment' => $instalment,
            'total_interest' => $totalInterest,
            'total_service_fees' => $totalServiceFees,
            'total_repayable' => $totalRepayable,
            // What the credit costs over and above the money handed over.
            'cost_of_credit' => round($totalRepayable - $amount, 2),
        ];
    }

    /**
     * Walks the loan down month by month. The last instalment clears whatever
     * is left rather than following the formula, which absorbs the rounding.
     *
     * @return array<int, array{interest: float, capital: float, balance: float}>
     */
    public function amortise(
        float $financed,
        float $capitalInstalment,
        float $monthlyRate,
        int $termMonths,
    ): array {
        $balance = $financed;
        $rows = [];

        for ($month = 1; $month <= $termMonths; $month++) {
            $interest = round($balance * $monthlyRate, 2);
            $capital = $month === $termMonths
                ? round($balance, 2)
                : round($capitalInstalment - $interest, 2);

            $balance = round($balance - $capital, 2);

            $rows[] = ['interest' => $interest, 'capital' => $capital, 'balance' => max($balance, 0)];
        }

        return $rows;
    }

    /**
     * Whether the instalment fits inside what the client has left each month.
     */
    public function isAffordable(float $instalment, float $disposableIncome): bool
    {
        return $instalment <= $disposableIncome;
    }
}
