<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\LoanApplication;
use DateTimeImmutable;
use Illuminate\Support\Carbon;

/**
 * Writes the repayment schedule when the money goes out.
 *
 * Each instalment is split into what it is actually made of. Interest is
 * charged on the balance still owed at the start of the month, so it falls as
 * the loan runs down; capital is whatever is left of the instalment after the
 * interest is met, so it rises. The service fee is flat and sits outside that
 * arithmetic entirely.
 *
 * Rounding is settled on the last instalment rather than spread across the
 * term. Every other row is a round number of cents, and the final one carries
 * whatever the rounding left over, so the schedule adds up to the balance
 * exactly instead of leaving a few cents outstanding on a settled loan.
 */
final class ScheduleBuilder
{
    public function __construct(
        private readonly InstalmentCalculator $pricing,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(LoanApplication $application, DateTimeImmutable $disbursedOn): array
    {
        $balance = (float) ($application->amount_financed > 0
            ? $application->amount_financed
            : $application->amount);

        $serviceFee = (float) $application->monthly_service_fee;
        $instalment = (float) $application->monthly_instalment;
        $capitalInstalment = round($instalment - $serviceFee, 2);
        $monthlyRate = (float) $application->interest_rate / 100 / 12;
        $term = (int) $application->term_months;

        $start = Carbon::instance($disbursedOn);
        $rows = [];

        // The same walk the quote was totalled from, so a schedule can never
        // disagree with the figure the client was given.
        $amortised = $this->pricing->amortise($balance, $capitalInstalment, $monthlyRate, $term);

        foreach ($amortised as $index => $month) {
            $number = $index + 1;
            $interest = $month['interest'];
            $capital = $month['capital'];

            $rows[] = [
                'loan_application_id' => $application->id,
                'instalment_number' => $number,
                // The first instalment falls a month after the payout.
                'due_on' => $start->copy()->addMonthsNoOverflow($number)->toDateString(),
                'service_fee_due' => $serviceFee,
                'interest_due' => $interest,
                'capital_due' => $capital,
                'total_due' => round($serviceFee + $interest + $capital, 2),
                'closing_balance' => $month['balance'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $rows;
    }
}
