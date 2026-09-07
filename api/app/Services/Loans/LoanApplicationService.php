<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Enums\ApplicationDocumentType;
use App\Enums\LoanApplicationStatus;
use App\Models\Client;
use App\Models\LoanApplication;
use App\Models\User;
use App\Notifications\ApplicationAwaitingDecision;
use App\Notifications\ApplicationDecided;
use App\Services\Audit\AuditRecorder;
use App\Services\Clients\BorrowingEligibility;
use App\Services\Notifications\NotificationAudience;
use App\Services\Reference\ReferenceNumberService;
use App\Support\Permissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LoanApplicationService
{
    public function __construct(
        private readonly ReferenceNumberService $references,
        private readonly InstalmentCalculator $pricing,
        private readonly ApplicationDocumentStore $documents,
        private readonly BorrowingEligibility $eligibility,
        private readonly AuditRecorder $audit,
        private readonly NotificationAudience $audience,
    ) {}

    /**
     * Captures an application and puts it in front of a credit manager.
     *
     * The recruiter, the affordability assessment and the quote are all copied
     * onto the application as it stands now. None of them may drift afterwards:
     * the recruiter decides who is paid commission, and the assessment and
     * quote are what any later decision has to be defensible against.
     *
     * @param  array{amount: float, term_months: int, purpose: string|null}  $data
     * @param  array<string, UploadedFile>  $documents  Keyed by document type.
     */
    public function submit(
        Client $client,
        array $data,
        User $submittedBy,
        ?string $ipAddress = null,
        array $documents = [],
    ): LoanApplication {
        // Checked before anything else: a client who may not borrow at all
        // should not be told about their affordability instead.
        $eligibility = $this->eligibility->check($client);

        if (! $eligibility['eligible']) {
            throw new RuntimeException($eligibility['reason'] ?? 'This client may not take a loan at present.');
        }

        $assessment = $client->latestAffordability;

        if ($assessment === null) {
            throw new RuntimeException(
                'This client has no affordability assessment. One is required before a loan can be applied for.'
            );
        }

        $quote = $this->pricing->quote($data['amount'], $data['term_months']);

        if (! $this->pricing->isAffordable($quote['monthly_instalment'], $assessment->disposable_income)) {
            throw new RuntimeException(sprintf(
                'The instalment of R%s exceeds the disposable income of R%s on this client\'s assessment.',
                number_format($quote['monthly_instalment'], 2),
                number_format($assessment->disposable_income, 2),
            ));
        }

        return DB::transaction(function () use ($client, $data, $submittedBy, $ipAddress, $assessment, $quote, $documents): LoanApplication {
            $application = LoanApplication::create([
                'application_number' => $this->references->nextApplicationNumber(),
                'client_id' => $client->id,
                'recruiter_id' => $client->recruiter_id,
                'affordability_assessment_id' => $assessment->id,
                'amount' => $data['amount'],
                'initiation_fee' => $quote['initiation_fee'],
                'amount_financed' => $quote['amount_financed'],
                'term_months' => $data['term_months'],
                'interest_rate' => $quote['interest_rate'],
                'monthly_service_fee' => $quote['monthly_service_fee'],
                'purpose' => $data['purpose'] ?? null,
                'monthly_instalment' => $quote['monthly_instalment'],
                'total_repayable' => $quote['total_repayable'],
                'disposable_income_at_capture' => $assessment->disposable_income,
                'status' => LoanApplicationStatus::Submitted,
                'submitted_at' => now(),
                'submitted_by' => $submittedBy->id,
            ]);

            // Stored inside the transaction: an application must never exist
            // without the documents it was assessed on.
            foreach ($documents as $type => $file) {
                $this->documents->store(
                    $application,
                    ApplicationDocumentType::from($type),
                    $file,
                    $submittedBy,
                );
            }

            $this->audit->record(
                action: 'application.submitted',
                subject: $application,
                summary: sprintf(
                    'Submitted application %s for %s: R%s over %d months, instalment R%s.',
                    $application->application_number,
                    $client->fullName(),
                    number_format((float) $data['amount'], 2),
                    $data['term_months'],
                    number_format($quote['monthly_instalment'], 2),
                ),
                actor: $submittedBy,
                metadata: [
                    'application_number' => $application->application_number,
                    'amount' => $data['amount'],
                    'term_months' => $data['term_months'],
                    'monthly_instalment' => $quote['monthly_instalment'],
                    'disposable_income' => $assessment->disposable_income,
                    'documents' => array_keys($documents),
                ],
                ipAddress: $ipAddress,
            );

            // The credit desk is told a file has arrived rather than having to
            // watch the queue for one. The officer who submitted it is left out:
            // they were the one who sent it.
            $this->audience->notifyHoldersOf(
                Permissions::APPLICATIONS_DECIDE,
                new ApplicationAwaitingDecision($application->loadMissing('client'), $submittedBy->fullName()),
                except: $submittedBy,
            );

            return $application->load(['client.recruiter', 'recruiter', 'documents']);
        });
    }

    /**
     * Approves or declines a submitted application.
     *
     * Approval does not release money. The agreement still has to be signed and
     * a different department has to verify and pay it - which is why this only
     * moves the status and never touches a disbursement.
     */
    public function decide(
        LoanApplication $application,
        bool $approved,
        ?string $declineReason,
        User $decidedBy,
        ?string $ipAddress = null,
    ): LoanApplication {
        if ($application->status !== LoanApplicationStatus::Submitted) {
            throw new RuntimeException(sprintf(
                'This application is %s and is no longer awaiting a decision.',
                $application->status->label(),
            ));
        }

        if ($application->submitted_by === $decidedBy->id) {
            throw new RuntimeException(
                'You submitted this application and may not also decide it.'
            );
        }

        return DB::transaction(function () use ($application, $approved, $declineReason, $decidedBy, $ipAddress): LoanApplication {
            $application->update([
                'status' => $approved ? LoanApplicationStatus::Approved : LoanApplicationStatus::Declined,
                'decided_at' => now(),
                'decided_by' => $decidedBy->id,
                'decline_reason' => $approved ? null : $declineReason,
            ]);

            $this->audit->record(
                action: $approved ? 'application.approved' : 'application.declined',
                subject: $application,
                summary: $approved
                    ? "Approved application {$application->application_number}."
                    : "Declined application {$application->application_number}: {$declineReason}",
                actor: $decidedBy,
                metadata: ['decline_reason' => $approved ? null : $declineReason],
                ipAddress: $ipAddress,
            );

            // The officer who submitted it is the one who has to act next, so
            // the decision is delivered to them rather than waiting to be
            // noticed on a list.
            $submitter = $application->submittedBy;

            if ($submitter !== null && $submitter->isNot($decidedBy)) {
                $submitter->notify(new ApplicationDecided(
                    $application->loadMissing('client'),
                    $approved,
                    $declineReason,
                    $decidedBy->fullName(),
                ));
            }

            return $application->load(['client.recruiter', 'recruiter']);
        });
    }
}
