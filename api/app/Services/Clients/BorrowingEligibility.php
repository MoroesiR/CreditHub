<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\LoanApplicationStatus;
use App\Models\Client;
use App\Models\LoanApplication;
use App\Services\Repayments\LoanAccount;

/**
 * Whether a client may take a loan today.
 *
 * One at a time: still repaying, or an earlier application anywhere between
 * capture and payout, and the answer is no. Two files assessed separately
 * against the same income are affordable apart and unaffordable together.
 *
 * Settling clears the block. Asked here rather than inside the capture service
 * so the screen can say why before an officer has typed anything.
 */
final class BorrowingEligibility
{
    /** Captured but not yet paid out. */
    private const IN_FLIGHT = [
        LoanApplicationStatus::Submitted,
        LoanApplicationStatus::Approved,
        LoanApplicationStatus::AgreementSigned,
    ];

    public function __construct(
        private readonly LoanAccount $accounts,
    ) {}

    /**
     * @return array{
     *     eligible: bool,
     *     reason: string|null,
     *     blocking_application_id: int|null,
     *     blocking_application_number: string|null,
     *     outstanding: float|null
     * }
     */
    public function check(Client $client): array
    {
        $inFlight = $client->loanApplications()
            ->whereIn('status', array_column(self::IN_FLIGHT, 'value'))
            ->orderByDesc('id')
            ->first();

        if ($inFlight !== null) {
            return $this->blocked(
                $inFlight,
                sprintf(
                    'Application %s is %s. A client may only have one loan running at a time.',
                    $inFlight->application_number,
                    strtolower($inFlight->status->label()),
                ),
            );
        }

        $outstanding = $client->loanApplications()
            ->where('status', LoanApplicationStatus::Disbursed)
            ->orderByDesc('id')
            ->get()
            ->first(fn (LoanApplication $loan): bool => ! $this->accounts->summarise($loan)['is_settled']);

        if ($outstanding !== null) {
            $account = $this->accounts->summarise($outstanding);

            return $this->blocked(
                $outstanding,
                sprintf(
                    'Loan %s still has R%s outstanding%s.',
                    $outstanding->application_number,
                    number_format($account['balance'], 2),
                    $account['is_in_arrears']
                        ? sprintf(', and is R%s in arrears', number_format($account['arrears'], 2))
                        : '',
                ),
                $account['balance'],
            );
        }

        return [
            'eligible' => true,
            'reason' => null,
            'blocking_application_id' => null,
            'blocking_application_number' => null,
            'outstanding' => null,
        ];
    }

    /**
     * @return array{
     *     eligible: bool,
     *     reason: string|null,
     *     blocking_application_id: int|null,
     *     blocking_application_number: string|null,
     *     outstanding: float|null
     * }
     */
    private function blocked(LoanApplication $application, string $reason, ?float $outstanding = null): array
    {
        return [
            'eligible' => false,
            'reason' => $reason,
            'blocking_application_id' => $application->id,
            'blocking_application_number' => $application->application_number,
            'outstanding' => $outstanding,
        ];
    }
}
