<?php

declare(strict_types=1);

namespace App\Services\Repayments;

use App\Models\LoanApplication;
use DateTimeImmutable;

/**
 * Splits a receipt across what the client owes.
 *
 * Section 126(3) of the National Credit Act sets the order and it is not the
 * lender's to choose: interest, then fees and charges, then capital. A short
 * payment still clears the month's charges, so the shortfall lands on capital
 * and the loan runs longer.
 *
 * Only what has fallen due is charged for, so an early payment reduces capital
 * rather than prepaying interest that has not accrued.
 */
final class PaymentAllocator
{
    /**
     * @return array{fee: float, interest: float, capital: float}
     */
    public function allocate(
        LoanApplication $application,
        float $amount,
        ?DateTimeImmutable $on = null,
    ): array {
        $today = $on ?? new DateTimeImmutable('today');

        $due = $application->scheduleEntries()
            ->whereDate('due_on', '<=', $today->format('Y-m-d'))
            ->get();

        // Everything received so far, split the same way, so this payment is
        // measured against what is still outstanding rather than the gross.
        $paid = $application->repayments()
            ->reorder()
            ->selectRaw('COALESCE(SUM(fee_portion), 0) as fee, COALESCE(SUM(interest_portion), 0) as interest, COALESCE(SUM(capital_portion), 0) as capital')
            ->first();

        $outstanding = [
            'fee' => round(max((float) $due->sum('service_fee_due') - (float) ($paid->fee ?? 0), 0), 2),
            'interest' => round(max((float) $due->sum('interest_due') - (float) ($paid->interest ?? 0), 0), 2),
        ];

        $remaining = round($amount, 2);
        $split = ['fee' => 0.0, 'interest' => 0.0, 'capital' => 0.0];

        foreach (['interest', 'fee'] as $bucket) {
            $taken = min($remaining, $outstanding[$bucket]);
            $split[$bucket] = round($taken, 2);
            $remaining = round($remaining - $taken, 2);
        }

        // Whatever is left reduces the balance, which is the only part of a
        // payment that shortens the loan.
        $split['capital'] = round($remaining, 2);

        return $split;
    }
}
