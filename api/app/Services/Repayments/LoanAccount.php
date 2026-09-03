<?php

declare(strict_types=1);

namespace App\Services\Repayments;

use App\Models\LoanApplication;
use DateTimeImmutable;

/**
 * The state of a disbursed loan: what was owed, what has come in, and whether
 * the client is behind.
 *
 * Expected-to-date is worked out from the number of instalments that have
 * fallen due since the payout, rather than from a stored schedule. That is a
 * deliberate simplification while every loan here is a straight equal
 * instalment: it gives the same answer, and there is no schedule to drift out
 * of step with the loan. A restructure or a payment holiday would need a real
 * schedule table, and this is the seam to replace when that day comes.
 */
final class LoanAccount
{
    /**
     * @return array{
     *     total_repayable: float,
     *     paid: float,
     *     balance: float,
     *     instalments_due: int,
     *     expected_to_date: float,
     *     arrears: float,
     *     is_settled: bool,
     *     is_in_arrears: bool,
     *     months_behind: float
     * }
     */
    public function summarise(LoanApplication $application, ?DateTimeImmutable $on = null): array
    {
        $today = $on ?? new DateTimeImmutable('today');

        $totalRepayable = (float) $application->total_repayable;
        $instalment = (float) $application->monthly_instalment;

        $paid = round((float) $application->repayments()->sum('amount'), 2);
        $balance = round(max($totalRepayable - $paid, 0), 2);

        $instalmentsDue = $this->instalmentsDue($application, $today);
        $expected = round(min($instalmentsDue * $instalment, $totalRepayable), 2);
        $arrears = round(max($expected - $paid, 0), 2);

        return [
            'total_repayable' => $totalRepayable,
            'paid' => $paid,
            'balance' => $balance,
            'instalments_due' => $instalmentsDue,
            'expected_to_date' => $expected,
            'arrears' => $arrears,
            'is_settled' => $balance <= 0.0,
            'is_in_arrears' => $arrears > 0.0,
            // How many instalments the shortfall represents, which is the
            // figure collections actually work to.
            'months_behind' => $instalment > 0 ? round($arrears / $instalment, 1) : 0.0,
        ];
    }

    /**
     * Instalments that have fallen due since the money went out.
     *
     * The first is due a month after disbursement, and the count never exceeds
     * the term.
     */
    private function instalmentsDue(LoanApplication $application, DateTimeImmutable $today): int
    {
        $disbursement = $application->disbursement;
        $paidAt = $disbursement?->paid_at;

        if ($paidAt === null) {
            return 0;
        }

        $elapsed = ($paidAt->diff($today)->y * 12) + $paidAt->diff($today)->m;

        return (int) max(0, min($elapsed, $application->term_months));
    }
}
