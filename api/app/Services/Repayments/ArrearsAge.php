<?php

declare(strict_types=1);

namespace App\Services\Repayments;

use App\Models\LoanApplication;
use DateTimeImmutable;

/**
 * How long an account has been behind, not how much.
 *
 * Collections works by age. R500 missed yesterday and R500 missed four months
 * ago carry the same arrears figure and are different problems.
 *
 * Receipts cover the oldest instalments first, so the age is taken from the
 * oldest instalment the money has not reached rather than from the first one
 * ever missed.
 */
final class ArrearsAge
{
    public const CURRENT = 'current';

    public const BUCKETS = [self::CURRENT, '1-30', '31-60', '61-90', '90+'];

    /**
     * @return array{days: int, bucket: string, oldest_unpaid_due_on: string|null}
     */
    public function for(LoanApplication $application, ?DateTimeImmutable $on = null): array
    {
        $today = $on ?? new DateTimeImmutable('today');

        $due = $application->scheduleEntries()
            ->whereDate('due_on', '<=', $today->format('Y-m-d'))
            ->orderBy('instalment_number')
            ->get();

        if ($due->isEmpty()) {
            return $this->current();
        }

        $paid = round((float) $application->repayments()->reorder()->sum('amount'), 2);
        $running = 0.0;

        foreach ($due as $entry) {
            $running = round($running + (float) $entry->total_due, 2);

            if ($running <= $paid) {
                continue;
            }

            $days = $this->daysSince($entry->due_on->toDateString(), $today);

            return [
                'days' => $days,
                'bucket' => $this->bucket($days),
                'oldest_unpaid_due_on' => $entry->due_on->toDateString(),
            ];
        }

        return $this->current();
    }

    private function daysSince(string $from, DateTimeImmutable $today): int
    {
        $elapsed = (new DateTimeImmutable($from))->diff($today);

        return $elapsed->invert === 1 ? 0 : (int) $elapsed->days;
    }

    private function bucket(int $days): string
    {
        return match (true) {
            $days <= 0 => self::CURRENT,
            $days <= 30 => '1-30',
            $days <= 60 => '31-60',
            $days <= 90 => '61-90',
            default => '90+',
        };
    }

    /**
     * @return array{days: int, bucket: string, oldest_unpaid_due_on: string|null}
     */
    private function current(): array
    {
        return ['days' => 0, 'bucket' => self::CURRENT, 'oldest_unpaid_due_on' => null];
    }
}
