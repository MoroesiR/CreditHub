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
 * One at a time. A client still repaying cannot borrow again, and neither can
 * one whose previous application is somewhere between capture and payout,
 * because two files running side by side would each be assessed against an
 * affordability figure that ignores the other. The instalments would be
 * affordable separately and unaffordable together, which is how a lender ends
 * up having granted credit it can show was never affordable.
 *
 * Settling the loan clears the block. Repeat borrowing is the point of keeping
 * a client on file at all, so the door reopens the moment the balance reaches
 * zero.
 *
 * Answering here rather than inside the capture service lets the screen say
 * why a client cannot borrow before an officer has typed anything, and lets
 * the service refuse for exactly the same reason if anyone gets past it.
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
