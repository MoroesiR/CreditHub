<?php

declare(strict_types=1);

namespace App\Services\Repayments;

use App\Enums\LoanApplicationStatus;
use App\Enums\RepaymentMethod;
use App\Models\LoanApplication;
use App\Models\LoanRepayment;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RepaymentService
{
    public function __construct(
        private readonly LoanAccount $accounts,
        private readonly PaymentAllocator $allocator,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * Receipts money against a loan.
     *
     * @param  array{amount: float, received_on: string, method: string, reference: string|null, note: string|null}  $data
     */
    public function record(
        LoanApplication $application,
        array $data,
        User $recordedBy,
        ?string $ipAddress = null,
    ): LoanRepayment {
        if ($application->status !== LoanApplicationStatus::Disbursed) {
            throw new RuntimeException(sprintf(
                'This loan is %s. Repayments can only be received against money that has gone out.',
                $application->status->label(),
            ));
        }

        $account = $this->accounts->summarise($application);

        // Taking more than is owed hides a problem rather than solving one, so
        // the overpayment is refused and has to be dealt with deliberately.
        if ($data['amount'] > $account['balance']) {
            throw new RuntimeException(sprintf(
                'That is more than the outstanding balance of R%s. Capture the settlement amount, or refund the difference separately.',
                number_format($account['balance'], 2),
            ));
        }

        return DB::transaction(function () use ($application, $data, $recordedBy, $ipAddress): LoanRepayment {
            $split = $this->allocator->allocate(
                $application,
                (float) $data['amount'],
                new DateTimeImmutable($data['received_on']),
            );

            $repayment = $application->repayments()->create([
                'amount' => $data['amount'],
                'fee_portion' => $split['fee'],
                'interest_portion' => $split['interest'],
                'capital_portion' => $split['capital'],
                'received_on' => $data['received_on'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'recorded_by' => $recordedBy->id,
            ]);

            $after = $this->accounts->summarise($application->fresh());

            $this->audit->record(
                action: 'repayment.recorded',
                subject: $application,
                summary: sprintf(
                    'Received R%s by %s on %s, applied as R%s fees, R%s interest and R%s capital. Balance now R%s.',
                    number_format((float) $data['amount'], 2),
                    RepaymentMethod::from($data['method'])->label(),
                    $repayment->received_on->format('j M Y'),
                    number_format($split['fee'], 2),
                    number_format($split['interest'], 2),
                    number_format($split['capital'], 2),
                    number_format($after['balance'], 2),
                ),
                actor: $recordedBy,
                metadata: [
                    'amount' => $data['amount'],
                    'allocation' => $split,
                    'method' => $data['method'],
                    'reference' => $data['reference'] ?? null,
                    'balance_after' => $after['balance'],
                ],
                ipAddress: $ipAddress,
            );

            return $repayment;
        });
    }

    /**
     * Backs out a receipt captured in error.
     *
     * The original row is left exactly as it was and an opposing entry is
     * written beside it. Deleting the mistake would leave the account correct
     * and the history a lie.
     */
    public function reverse(
        LoanRepayment $repayment,
        string $reason,
        User $recordedBy,
        ?string $ipAddress = null,
    ): LoanRepayment {
        if ($repayment->isReversal()) {
            throw new RuntimeException('A reversal cannot itself be reversed.');
        }

        if ($repayment->reversal()->exists()) {
            throw new RuntimeException('This receipt has already been reversed.');
        }

        return DB::transaction(function () use ($repayment, $reason, $recordedBy, $ipAddress): LoanRepayment {
            $reversal = LoanRepayment::create([
                'loan_application_id' => $repayment->loan_application_id,
                'amount' => -$repayment->amount,
                'fee_portion' => -$repayment->fee_portion,
                'interest_portion' => -$repayment->interest_portion,
                'capital_portion' => -$repayment->capital_portion,
                'received_on' => $repayment->received_on,
                'method' => RepaymentMethod::Reversal,
                'reference' => $repayment->reference,
                'note' => $reason,
                'reverses_id' => $repayment->id,
                'recorded_by' => $recordedBy->id,
            ]);

            $application = $repayment->loanApplication;

            $this->audit->record(
                action: 'repayment.reversed',
                subject: $application,
                summary: sprintf(
                    'Reversed a receipt of R%s taken on %s. %s',
                    number_format($repayment->amount, 2),
                    $repayment->received_on->format('j M Y'),
                    $reason,
                ),
                actor: $recordedBy,
                metadata: ['reversed_repayment_id' => $repayment->id, 'reason' => $reason],
                ipAddress: $ipAddress,
            );

            return $reversal;
        });
    }
}
