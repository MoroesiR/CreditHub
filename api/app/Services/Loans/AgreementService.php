<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Enums\LoanApplicationStatus;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\User;
use App\Notifications\AgreementReadyForPayout;
use App\Services\Audit\AuditRecorder;
use App\Services\Disbursements\DisbursementService;
use App\Services\Notifications\NotificationAudience;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class AgreementService
{
    private const DISK = 'local';

    /** A drawn signature or a webcam frame, well within a data URL's limits. */
    private const MAX_IMAGE_BYTES = 3_000_000;

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly DisbursementService $disbursements,
        private readonly NotificationAudience $audience,
    ) {}

    /**
     * Produces the agreement for an approved application.
     *
     * Generating is idempotent: opening the agreement twice must not produce
     * two documents with two numbers for one loan.
     */
    public function generate(
        LoanApplication $application,
        User $generatedBy,
        ?string $ipAddress = null,
    ): LoanAgreement {
        if ($application->status !== LoanApplicationStatus::Approved
            && $application->status !== LoanApplicationStatus::AgreementSigned) {
            throw new RuntimeException(sprintf(
                'An agreement can only be drawn for an approved application. This one is %s.',
                $application->status->label(),
            ));
        }

        $existing = $application->agreement()->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($application, $generatedBy, $ipAddress): LoanAgreement {
            $agreement = LoanAgreement::create([
                'loan_application_id' => $application->id,
                // Derived from the application's own number: one loan, one
                // agreement, and the pair are obvious on sight.
                'agreement_number' => str_replace('CHL', 'CHA', $application->application_number),
                'amount' => $application->amount,
                'initiation_fee' => $application->initiation_fee,
                'amount_financed' => $application->amount_financed,
                'term_months' => $application->term_months,
                'interest_rate' => $application->interest_rate,
                'monthly_service_fee' => $application->monthly_service_fee,
                'monthly_instalment' => $application->monthly_instalment,
                'total_repayable' => $application->total_repayable,
                'generated_at' => now(),
                'generated_by' => $generatedBy->id,
            ]);

            $this->audit->record(
                action: 'agreement.generated',
                subject: $application,
                summary: "Generated agreement {$agreement->agreement_number} for {$application->application_number}.",
                actor: $generatedBy,
                metadata: ['agreement_number' => $agreement->agreement_number],
                ipAddress: $ipAddress,
            );

            return $agreement;
        });
    }

    /**
     * Records the client's signature.
     *
     * Two artefacts are kept, and they answer different questions. The drawn
     * signature is what the client put on the agreement. The photograph is
     * evidence that the person doing the signing was present at the desk - a
     * signature alone cannot show that, and it is the part a client disputing
     * the agreement later would attack.
     *
     * @param  string  $signature  Data URL of the drawn signature.
     * @param  string|null  $photo  Data URL of the webcam frame.
     */
    public function sign(
        LoanAgreement $agreement,
        string $signedName,
        string $signature,
        ?string $photo,
        User $witnessedBy,
        ?string $ipAddress = null,
    ): LoanAgreement {
        if ($agreement->isSigned()) {
            throw new RuntimeException('This agreement has already been signed.');
        }

        $application = $agreement->loanApplication;

        if ($application->status !== LoanApplicationStatus::Approved) {
            throw new RuntimeException(sprintf(
                'This application is %s and cannot be signed.',
                $application->status->label(),
            ));
        }

        return DB::transaction(function () use (
            $agreement,
            $application,
            $signedName,
            $signature,
            $photo,
            $witnessedBy,
            $ipAddress,
        ): LoanAgreement {
            $agreement->update([
                'signature_path' => $this->storeImage($signature, $agreement, 'signature'),
                'photo_path' => $photo === null ? null : $this->storeImage($photo, $agreement, 'photo'),
                'signed_name' => $signedName,
                'signed_at' => now(),
                'witnessed_by' => $witnessedBy->id,
                'signed_ip' => $ipAddress,
            ]);

            // Signing is what moves the file out of origination and into the
            // payout queue. Approval alone never does.
            $application->update(['status' => LoanApplicationStatus::AgreementSigned]);

            // Queued in the same transaction: a signed agreement and its place
            // in the payout queue cannot exist apart.
            $this->disbursements->queue($application);

            // Whoever has to act next is told, rather than left to notice it.
            // Addressed to the permission to work a payout, not merely to see
            // one: an auditor and an administrator can both open the queue but
            // neither works it, so neither is told it has grown.
            $this->audience->notifyHoldersOf(
                Permissions::DISBURSEMENTS_VERIFY,
                new AgreementReadyForPayout($application->loadMissing('client')),
            );

            $this->audit->record(
                action: 'agreement.signed',
                subject: $application,
                summary: "Agreement {$agreement->agreement_number} signed by {$signedName}, witnessed by {$witnessedBy->fullName()}."
                    .($photo === null ? ' No photograph captured.' : ' Photograph captured at signing.'),
                actor: $witnessedBy,
                metadata: [
                    'agreement_number' => $agreement->agreement_number,
                    'signed_name' => $signedName,
                    'photo_captured' => $photo !== null,
                ],
                ipAddress: $ipAddress,
            );

            return $agreement->fresh();
        });
    }

    /**
     * Decodes a data URL and writes it to private storage.
     *
     * The payload is decoded and re-checked rather than trusted: a data URL is
     * user input, and its declared MIME type says nothing about its contents.
     */
    private function storeImage(string $dataUrl, LoanAgreement $agreement, string $kind): string
    {
        if (preg_match('#^data:image/(png|jpeg);base64,#', $dataUrl, $matches) !== 1) {
            throw new RuntimeException('The captured image must be a PNG or JPEG.');
        }

        $binary = base64_decode(substr($dataUrl, strlen($matches[0])), strict: true);

        if ($binary === false) {
            throw new RuntimeException('The captured image could not be decoded.');
        }

        if (strlen($binary) > self::MAX_IMAGE_BYTES) {
            throw new RuntimeException('The captured image is too large.');
        }

        // getimagesizefromstring returns false for anything that is not really
        // an image, whatever the data URL claimed.
        if (@getimagesizefromstring($binary) === false) {
            throw new RuntimeException('The captured image is not a readable image.');
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : 'png';
        $path = "agreements/{$agreement->id}/{$kind}-".Str::uuid()->toString().".{$extension}";

        Storage::disk(self::DISK)->put($path, $binary);

        return $path;
    }

    public function disk(): string
    {
        return self::DISK;
    }
}
