<?php

declare(strict_types=1);

namespace App\Services\Repayments;

use App\Models\LoanApplication;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The state of a disbursed loan: what was owed, what has come in, and whether
 * the client is behind.
 *
 * Expected-to-date is read off the schedule written when the money went out,
 * so it holds for a loan whose instalments are not all the same and can say
 * which instalment a client has reached rather than only how far behind they
 * are in rands.
 *
 * A loan disbursed before schedules existed has none, and for those the older
 * derivation from elapsed months still applies. It gives the same answer for a
 * straight equal-instalment loan, which is all of them from that period.
 */
final class LoanAccount
{
    /**
     * @return array{
     *     total_repayable: float,
     *     paid: float,
     *     balance: float,
     *     fees_paid: float,
     *     interest_paid: float,
     *     capital_paid: float,
     *     instalments_due: int,
     *     expected_to_date: float,
     *     arrears: float,
     *     is_settled: bool,
     *     is_in_arrears: bool,
     *     months_behind: float,
     *     next_due_on: string|null
     * }
     */
    public function summarise(LoanApplication $application, ?DateTimeImmutable $on = null): array
    {
        $today = $on ?? new DateTimeImmutable('today');

        $instalment = (float) $application->monthly_instalment;
        $schedule = $application->scheduleEntries()->get();

        // Once a schedule exists it is what the client owes. The figure quoted
        // at capture is the instalment multiplied by the term, which is a few
        // cents out from the schedule because the last instalment absorbs the
        // rounding. Reading the quote here would leave those cents outstanding
        // on a loan that has been paid in full.
        $totalRepayable = $schedule->isNotEmpty()
            ? round((float) $schedule->sum('total_due'), 2)
            : (float) $application->total_repayable;

        // reorder() strips the relation's own ordering. Summing over a
        // query that still carries an ORDER BY on a column outside the
        // aggregate is rejected outright by MySQL and MariaDB.
        $totals = $application->repayments()
            ->reorder()
            ->selectRaw('COALESCE(SUM(amount), 0) as paid, COALESCE(SUM(fee_portion), 0) as fees, COALESCE(SUM(interest_portion), 0) as interest, COALESCE(SUM(capital_portion), 0) as capital')
            ->first();

        $paid = round((float) ($totals->paid ?? 0), 2);
        $balance = round(max($totalRepayable - $paid, 0), 2);

        [$instalmentsDue, $expected, $nextDue] = $this->dueToDate($application, $schedule, $today, $instalment);

        $expected = round(min($expected, $totalRepayable), 2);
        $arrears = round(max($expected - $paid, 0), 2);

        return [
            'total_repayable' => $totalRepayable,
            'paid' => $paid,
            'balance' => $balance,
            'fees_paid' => round((float) ($totals->fees ?? 0), 2),
            'interest_paid' => round((float) ($totals->interest ?? 0), 2),
            'capital_paid' => round((float) ($totals->capital ?? 0), 2),
            'instalments_due' => $instalmentsDue,
            'expected_to_date' => $expected,
            'arrears' => $arrears,
            'is_settled' => $balance <= 0.0,
            'is_in_arrears' => $arrears > 0.0,
            // How many instalments the shortfall represents, which is the
            // figure collections actually work to.
            'months_behind' => $instalment > 0 ? round($arrears / $instalment, 1) : 0.0,
            'next_due_on' => $nextDue,
        ];
    }

    /**
     * @return array{0: int, 1: float, 2: string|null}
     */
    private function dueToDate(
        LoanApplication $application,
        Collection $schedule,
        DateTimeImmutable $today,
        float $instalment,
    ): array {
        if ($schedule->isNotEmpty()) {
            $due = $schedule->filter(
                static fn ($entry): bool => $entry->due_on->lessThanOrEqualTo($today),
            );

            $next = $schedule->first(
                static fn ($entry): bool => $entry->due_on->greaterThan($today),
            );

            return [$due->count(), (float) $due->sum('total_due'), $next?->due_on->toDateString()];
        }

        $paidAt = $application->disbursement?->paid_at;

        if ($paidAt === null) {
            return [0, 0.0, null];
        }

        $elapsed = ($paidAt->diff($today)->y * 12) + $paidAt->diff($today)->m;
        $count = (int) max(0, min($elapsed, $application->term_months));

        return [$count, $count * $instalment, null];
    }
}
