<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Enums\CommissionStatus;
use App\Enums\LoanApplicationStatus;
use App\Models\LoanApplication;

/**
 * Who handled a loan, in order, from capture to the last thing that happened
 * to it.
 *
 * Read from the records themselves rather than from the audit trail. The audit
 * trail says what was done; this says where the file stands and whose desk it
 * passed through, which is the question asked when a client phones to ask what
 * is happening to their money.
 *
 * Stages that have not happened yet are still returned, marked as pending, so
 * the caller can show the whole route rather than only the part travelled.
 */
final class ApplicationJourney
{
    /**
     * @return array<int, array{
     *     key: string, label: string, actor: string|null,
     *     at: string|null, done: bool, detail: string|null
     * }>
     */
    public function for(LoanApplication $application): array
    {
        $application->loadMissing([
            'submittedBy',
            'decidedBy',
            'agreement.witnessedBy',
            'disbursement.verifiedBy',
            'disbursement.paidBy',
            'commission.recruiter',
            'commission.paidBy',
        ]);

        $declined = $application->status === LoanApplicationStatus::Declined;
        $agreement = $application->agreement;
        $disbursement = $application->disbursement;
        $commission = $application->commission;

        $stages = [
            [
                'key' => 'captured',
                'label' => 'Captured and submitted',
                'actor' => $application->submittedBy?->fullName(),
                'at' => $application->submitted_at?->toIso8601String(),
                'done' => $application->submitted_at !== null,
                'detail' => null,
            ],
            [
                'key' => 'decision',
                'label' => $declined ? 'Declined' : 'Credit decision',
                'actor' => $application->decidedBy?->fullName(),
                'at' => $application->decided_at?->toIso8601String(),
                'done' => $application->decided_at !== null,
                'detail' => $declined ? $application->decline_reason : null,
            ],
        ];

        // A declined file stops here. Showing it the remaining stages would
        // imply it is still travelling when it is not.
        if ($declined) {
            return $stages;
        }

        $stages[] = [
            'key' => 'agreement',
            'label' => 'Agreement signed',
            'actor' => $agreement?->witnessedBy?->fullName(),
            'at' => $agreement?->signed_at?->toIso8601String(),
            'done' => $agreement?->signed_at !== null,
            'detail' => $agreement?->signed_name === null
                ? null
                : 'Signed by '.$agreement->signed_name,
        ];

        $stages[] = [
            'key' => 'verified',
            'label' => 'Payout verified',
            'actor' => $disbursement?->verifiedBy?->fullName(),
            'at' => $disbursement?->verified_at?->toIso8601String(),
            'done' => $disbursement?->verified_at !== null,
            'detail' => $disbursement?->hold_reason === null
                ? null
                : 'On hold: '.$disbursement->hold_reason,
        ];

        $stages[] = [
            'key' => 'paid',
            'label' => 'Loan paid out',
            'actor' => $disbursement?->paidBy?->fullName(),
            'at' => $disbursement?->paid_at?->toIso8601String(),
            'done' => $disbursement?->paid_at !== null,
            'detail' => $disbursement?->payment_reference === null
                ? null
                : 'Reference '.$disbursement->payment_reference,
        ];

        // A walk-in earns nobody a commission, so the stage is not part of that
        // file's route at all.
        if ($application->recruiter_id !== null) {
            $stages[] = [
                'key' => 'commission',
                'label' => 'Commission paid',
                'actor' => $commission?->paidBy?->fullName(),
                'at' => $commission?->paid_at?->toIso8601String(),
                'done' => $commission?->status === CommissionStatus::Paid,
                'detail' => $commission === null
                    ? null
                    : number_format($commission->amount, 2).' to '.($commission->recruiter?->fullName() ?? 'the recruiter'),
            ];
        }

        return $stages;
    }

    /**
     * The last thing that actually happened, for a list view that has no room
     * for the whole route.
     *
     * @return array{label: string, actor: string|null, at: string|null}|null
     */
    public function latest(LoanApplication $application): ?array
    {
        $done = array_filter($this->for($application), static fn (array $stage): bool => $stage['done']);

        if ($done === []) {
            return null;
        }

        $last = end($done);

        return [
            'label' => $last['label'],
            'actor' => $last['actor'],
            'at' => $last['at'],
        ];
    }
}
