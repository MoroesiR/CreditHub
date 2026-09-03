<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Models\Client;
use App\Models\LoanAgreement;
use App\Models\LoanRepayment;
use App\Services\Repayments\LoanAccount;

/**
 * Everything held about one borrower, gathered for the profile screen.
 *
 * Assembled server side in one pass rather than left to the browser to stitch
 * together from five endpoints: the screen is opened when someone is on the
 * phone to the client, and a page that fills in piecemeal is no use then.
 */
final class ClientProfile
{
    public function __construct(
        private readonly LoanAccount $accounts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Client $client): array
    {
        $client->loadMissing([
            'recruiter',
            'latestAffordability',
            'registeredBy',
            'loanApplications.disbursement',
            'loanApplications.documents',
            'loanApplications.agreement',
        ]);

        $applications = $client->loanApplications->sortByDesc('created_at')->values();

        return [
            'photo' => $this->photo($client),
            'applications' => $applications->map(fn ($application): array => [
                'id' => $application->id,
                'application_number' => $application->application_number,
                'amount' => $application->amount,
                'term_months' => $application->term_months,
                'interest_rate' => $application->interest_rate,
                'monthly_instalment' => $application->monthly_instalment,
                'total_repayable' => $application->total_repayable,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'submitted_at' => $application->submitted_at?->toIso8601String(),
                'decided_at' => $application->decided_at?->toIso8601String(),
                'decline_reason' => $application->decline_reason,
                'disbursed_on' => $application->disbursement?->paid_at?->toDateString(),
                'account' => $application->disbursement?->paid_at === null
                    ? null
                    : $this->accounts->summarise($application),
            ])->all(),
            'repayments' => $this->repayments($client),
            'documents' => $this->documents($client),
            'totals' => $this->totals($client, $applications),
        ];
    }

    /**
     * The client's likeness, taken from the most recent agreement they signed.
     *
     * There is no separate photograph on the client record: the one captured
     * at signing is already a picture of this person taken at a moment the
     * lender can date and attribute, which is worth more than an uploaded file
     * of unknown origin.
     *
     * @return array<string, mixed>|null
     */
    private function photo(Client $client): ?array
    {
        $agreement = LoanAgreement::query()
            ->whereIn('loan_application_id', $client->loanApplications->pluck('id'))
            ->whereNotNull('photo_path')
            ->latest('signed_at')
            ->first();

        if ($agreement === null) {
            return null;
        }

        return [
            'agreement_id' => $agreement->id,
            'loan_application_id' => $agreement->loan_application_id,
            'captured_at' => $agreement->signed_at?->toIso8601String(),
        ];
    }

    /**
     * Every receipt across every loan this client has held, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function repayments(Client $client): array
    {
        return LoanRepayment::query()
            ->whereIn('loan_application_id', $client->loanApplications->pluck('id'))
            ->with(['loanApplication', 'recordedBy'])
            ->orderByDesc('received_on')
            ->orderByDesc('id')
            ->get()
            ->map(static fn (LoanRepayment $repayment): array => [
                'id' => $repayment->id,
                'application_number' => $repayment->loanApplication?->application_number,
                'amount' => $repayment->amount,
                'received_on' => $repayment->received_on?->toDateString(),
                'method_label' => $repayment->method->label(),
                'reference' => $repayment->reference,
                'note' => $repayment->note,
                'is_reversal' => $repayment->isReversal(),
                'recorded_by' => $repayment->recordedBy?->fullName(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documents(Client $client): array
    {
        return $client->loanApplications
            ->flatMap(fn ($application) => $application->documents->map(
                static fn ($document): array => [
                    'id' => $document->id,
                    'loan_application_id' => $application->id,
                    'application_number' => $application->application_number,
                    'type_label' => $document->type->label(),
                    'original_name' => $document->original_name,
                    'mime_type' => $document->mime_type,
                    'size_bytes' => $document->size_bytes,
                    'uploaded_at' => $document->created_at?->toIso8601String(),
                ],
            ))
            ->sortByDesc('uploaded_at')
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\LoanApplication>  $applications
     * @return array<string, mixed>
     */
    private function totals(Client $client, $applications): array
    {
        $disbursed = $applications->filter(
            static fn ($application): bool => $application->disbursement?->paid_at !== null,
        );

        $outstanding = 0.0;
        $arrears = 0.0;

        foreach ($disbursed as $application) {
            $account = $this->accounts->summarise($application);
            $outstanding += $account['balance'];
            $arrears += $account['arrears'];
        }

        return [
            'applications' => $applications->count(),
            'borrowed' => round((float) $disbursed->sum('amount'), 2),
            'outstanding' => round($outstanding, 2),
            'arrears' => round($arrears, 2),
            'is_in_arrears' => $arrears > 0,
        ];
    }
}
