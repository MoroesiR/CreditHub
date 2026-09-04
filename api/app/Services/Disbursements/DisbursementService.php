<?php

declare(strict_types=1);

namespace App\Services\Disbursements;

use App\Enums\CommissionStatus;
use App\Enums\DisbursementStatus;
use App\Enums\LoanApplicationStatus;
use App\Models\Commission;
use App\Models\Disbursement;
use App\Models\LoanApplication;
use App\Models\User;
use App\Notifications\LoanDisbursed;
use App\Services\Audit\AuditRecorder;
use App\Services\Commissions\CommissionCalculator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DisbursementService
{
    public function __construct(
        private readonly CommissionCalculator $commissions,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * Puts a signed loan into the payout queue.
     *
     * Called from the signing transaction, so a signed agreement and its queue
     * entry cannot exist apart.
     */
    public function queue(LoanApplication $application): Disbursement
    {
        return Disbursement::firstOrCreate(
            ['loan_application_id' => $application->id],
            ['amount' => $application->amount, 'status' => DisbursementStatus::Pending],
        );
    }

    /**
     * Records that the file has been checked.
     *
     * Verification is its own act with its own timestamp and its own actor -
     * "who checked it" and "who released the money" are separate questions.
     */
    public function verify(
        Disbursement $disbursement,
        User $verifiedBy,
        ?string $ipAddress = null,
    ): Disbursement {
        if ($disbursement->status !== DisbursementStatus::Pending
            && $disbursement->status !== DisbursementStatus::OnHold) {
            throw new RuntimeException(sprintf(
                'This payout is %s and cannot be verified again.',
                $disbursement->status->label(),
            ));
        }

        return DB::transaction(function () use ($disbursement, $verifiedBy, $ipAddress): Disbursement {
            $disbursement->update([
                'status' => DisbursementStatus::Verified,
                'verified_at' => now(),
                'verified_by' => $verifiedBy->id,
                'hold_reason' => null,
            ]);

            $this->audit->record(
                action: 'disbursement.verified',
                subject: $disbursement->loanApplication,
                summary: "Payout verified for {$disbursement->loanApplication->application_number}.",
                actor: $verifiedBy,
                ipAddress: $ipAddress,
            );

            return $disbursement->fresh();
        });
    }

    public function hold(
        Disbursement $disbursement,
        string $reason,
        User $heldBy,
        ?string $ipAddress = null,
    ): Disbursement {
        if ($disbursement->status === DisbursementStatus::Paid) {
            throw new RuntimeException('This loan has already been paid and cannot be held.');
        }

        return DB::transaction(function () use ($disbursement, $reason, $heldBy, $ipAddress): Disbursement {
            $disbursement->update([
                'status' => DisbursementStatus::OnHold,
                'hold_reason' => $reason,
                'verified_at' => null,
                'verified_by' => null,
            ]);

            $this->audit->record(
                action: 'disbursement.held',
                subject: $disbursement->loanApplication,
                summary: "Payout put on hold for {$disbursement->loanApplication->application_number}: {$reason}",
                actor: $heldBy,
                metadata: ['hold_reason' => $reason],
                ipAddress: $ipAddress,
            );

            return $disbursement->fresh();
        });
    }

    /**
     * Releases the loan, and prices the recruiter's commission.
     *
     * Commission is calculated here rather than at approval because it is
     * earned on money that actually left the business - an approved loan that
     * is never paid earns nobody anything.
     */
    public function pay(
        Disbursement $disbursement,
        string $paymentReference,
        User $paidBy,
        ?string $ipAddress = null,
    ): Disbursement {
        if ($disbursement->status !== DisbursementStatus::Verified) {
            throw new RuntimeException(sprintf(
                'This payout is %s. A loan is only released once it has been verified.',
                $disbursement->status->label(),
            ));
        }

        $application = $disbursement->loanApplication->load('client', 'recruiter');

        if ($application->status !== LoanApplicationStatus::AgreementSigned) {
            throw new RuntimeException(sprintf(
                'This application is %s and cannot be paid.',
                $application->status->label(),
            ));
        }

        return DB::transaction(function () use (
            $disbursement,
            $application,
            $paymentReference,
            $paidBy,
            $ipAddress,
        ): Disbursement {
            $client = $application->client;

            $disbursement->update([
                'status' => DisbursementStatus::Paid,
                'paid_at' => now(),
                'paid_by' => $paidBy->id,
                'payment_reference' => $paymentReference,
                // Copied at payment: where the money went must not change if
                // the client updates their account afterwards.
                'paid_to_bank_name' => $client?->bank_name,
                'paid_to_account_number' => $client?->bank_account_number,
                'paid_to_branch_code' => $client?->bank_branch_code,
            ]);

            $application->update(['status' => LoanApplicationStatus::Disbursed]);

            $this->audit->record(
                action: 'disbursement.paid',
                subject: $application,
                summary: sprintf(
                    'Released R%s for %s to %s, reference %s.',
                    number_format($disbursement->amount, 2),
                    $application->application_number,
                    $client?->bank_name ?? 'the client',
                    $paymentReference,
                ),
                actor: $paidBy,
                metadata: [
                    'payment_reference' => $paymentReference,
                    'amount' => $disbursement->amount,
                ],
                ipAddress: $ipAddress,
            );

            $this->recordCommission($application, $paidBy, $ipAddress);

            $submitter = $application->submittedBy;

            if ($submitter !== null) {
                $submitter->notify(new LoanDisbursed($application, $disbursement->amount, $paymentReference));
            }

            return $disbursement->fresh();
        });
    }

    /**
     * A walk-in client earns nobody a commission, so there is nothing to price.
     */
    private function recordCommission(
        LoanApplication $application,
        User $paidBy,
        ?string $ipAddress,
    ): void {
        if ($application->recruiter_id === null) {
            return;
        }

        $priced = $this->commissions->calculate($application->amount);

        $commission = Commission::create([
            'loan_application_id' => $application->id,
            'recruiter_id' => $application->recruiter_id,
            'commission_scheme_id' => $priced['scheme']->id,
            'loan_amount' => $application->amount,
            'rate_applied' => $priced['rate'],
            'amount' => $priced['amount'],
            'status' => CommissionStatus::Pending,
            'calculated_at' => now(),
        ]);

        $this->audit->record(
            action: 'commission.calculated',
            subject: $application,
            summary: sprintf(
                'Commission of R%s calculated for %s at %s%% under %s v%d%s.',
                number_format($commission->amount, 2),
                $application->recruiter?->fullName() ?? 'the recruiter',
                $priced['rate'],
                $priced['scheme']->name,
                $priced['scheme']->version,
                $priced['capped'] ? ', capped' : '',
            ),
            actor: $paidBy,
            metadata: [
                'commission_amount' => $commission->amount,
                'rate_applied' => $priced['rate'],
                'scheme_version' => $priced['scheme']->version,
                'capped' => $priced['capped'],
            ],
            ipAddress: $ipAddress,
        );
    }
}
