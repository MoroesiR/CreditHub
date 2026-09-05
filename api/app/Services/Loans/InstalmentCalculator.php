<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Support\FeeSchedule;

/**
 * Prices a loan.
 *
 * Reducing balance, the way an amortising credit agreement actually works:
 * interest is charged on what is still owed, not on the original advance, so
 * the instalment is lower than a flat-rate calculation would suggest and the
 * quote survives comparison with any other lender's.
 *
 * Three things make up what a client pays, and they behave differently. The
 * initiation fee is capitalised, so it is financed alongside the advance and
 * carries interest like the rest of the balance. Interest accrues on the
 * reducing balance. The service fee is a flat monthly charge that earns
 * nothing and reduces nothing, so it sits on top of the instalment rather
 * than inside the amortisation.
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
     * Walks the loan down month by month.
     *
     * Interest is charged on what is still owed at the start of the month, so
     * it falls as the balance does and the capital portion rises to match. The
     * last instalment clears the remaining balance rather than following the
     * formula, which is where the rounding across the term is absorbed.
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
