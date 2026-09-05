<?php

declare(strict_types=1);

namespace App\Services\Disbursements;

use App\Models\LoanApplication;
use App\Models\LoanScheduleEntry;
use App\Services\Loans\ScheduleBuilder;
use DateTimeImmutable;

/**
 * Puts a loan's schedule on the record.
 *
 * Separate from the builder that works the numbers out, so the arithmetic can
 * be exercised without a database and this can be called from the payout, from
 * a backfill, or from a test, without either knowing about the other.
 */
final class ScheduleWriter
{
    public function __construct(
        private readonly ScheduleBuilder $builder,
    ) {}

    public function write(LoanApplication $application, ?DateTimeImmutable $disbursedOn = null): int
    {
        // Writing a second schedule over a live one would change what a client
        // owes without anybody deciding to, so an existing one is left alone.
        if ($application->scheduleEntries()->exists()) {
            return 0;
        }

        $rows = $this->builder->build(
            $application,
            $disbursedOn ?? new DateTimeImmutable('today'),
        );

        LoanScheduleEntry::insert($rows);

        return count($rows);
    }
}
