<?php

declare(strict_types=1);

namespace App\Services\ChangeRequests;

use App\Enums\ApplicationDocumentType;
use App\Enums\ChangeRequestStatus;
use App\Enums\LoanApplicationStatus;
use App\Models\ApplicationDocument;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestDocument;
use App\Models\Client;
use App\Models\Recruiter;
use App\Models\User;
use App\Notifications\ChangeRequestReviewed;
use App\Notifications\ChangeRequestSubmitted;
use App\Services\Audit\AuditRecorder;
use App\Services\Notifications\NotificationAudience;
use App\Support\IdentityNumber;
use App\Support\Permissions;
use App\Support\SouthAfricanBanks;
use App\Support\SouthAfricanProvinces;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ChangeRequestService
{
    private const DISK = 'local';

    public const MAX_KILOBYTES = 5120;

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    /**
     * What may be asked for, per record type.
     *
     * A whitelist rather than "anything on the row": a request that could
     * reach client_number or recruiter_number would let the reference a file
     * is known by be rewritten, and one that could reach registered_by would
     * let a file change hands without anyone noticing.
     *
     * @var array<class-string, array<int, string>>
     */
    private const EDITABLE = [
        Client::class => [
            'first_name', 'last_name', 'id_number', 'phone', 'email',
            'address_line1', 'address_line2', 'city', 'province', 'postal_code',
            'employer_name', 'job_title', 'employment_status',
            'bank_name', 'bank_account_number',
        ],
        Recruiter::class => [
            'first_name', 'last_name', 'id_number', 'phone', 'email',
            'bank_name', 'bank_account_number', 'is_active',
        ],
    ];

    /**
     * What has to be produced before a change is considered.
     *
     * A request to change a surname is one person repeating what another told
     * them on the telephone. The ID copy is the thing an administrator can
     * actually decide on, and a bank statement is the only evidence that an
     * account belongs to the person about to be paid from it.
     *
     * @var array<string, string>
     */
    private const EVIDENCE_FOR = [
        'first_name' => ApplicationDocumentType::IdCopy->value,
        'last_name' => ApplicationDocumentType::IdCopy->value,
        'id_number' => ApplicationDocumentType::IdCopy->value,
        'bank_name' => ApplicationDocumentType::BankStatement->value,
        'bank_account_number' => ApplicationDocumentType::BankStatement->value,
        'employer_name' => ApplicationDocumentType::Payslip->value,
        'job_title' => ApplicationDocumentType::Payslip->value,
        'employment_status' => ApplicationDocumentType::Payslip->value,
    ];

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly NotificationAudience $audience,
    ) {}

    /**
     * The documents a given set of changes must be accompanied by.
     *
     * @param  array<int, string>  $fields
     * @return array<int, string>
     */
    public static function evidenceRequiredFor(array $fields): array
    {
        $required = [];

        foreach ($fields as $field) {
            if (isset(self::EVIDENCE_FOR[$field])) {
                $required[] = self::EVIDENCE_FOR[$field];
            }
        }

        return array_values(array_unique($required));
    }

    /**
     * @return array<int, string>
     */
    public static function editableFieldsFor(Model $subject): array
    {
        return self::EDITABLE[$subject::class] ?? [];
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, UploadedFile>  $documents  Keyed by document type.
     */
    public function submit(
        Model $subject,
        array $changes,
        string $reason,
        User $requestedBy,
        ?string $ipAddress = null,
        array $documents = [],
    ): ChangeRequest {
        $editable = self::editableFieldsFor($subject);

        if ($editable === []) {
            throw new RuntimeException('That record cannot be amended through a change request.');
        }

        $changes = array_intersect_key($changes, array_flip($editable));

        // Anything already equal to what is on the record is not a change, and
        // an administrator should not be asked to approve a no-op.
        $changes = array_filter(
            $changes,
            fn (mixed $value, string $field): bool => (string) $value !== (string) $subject->{$field},
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changes === []) {
            throw new RuntimeException('Nothing on this record would change.');
        }

        $this->assertClosedListsHold($changes);

        $missing = array_diff(
            self::evidenceRequiredFor(array_keys($changes)),
            array_keys($documents),
        );

        if ($missing !== []) {
            throw new RuntimeException(sprintf(
                'This change has to be supported by: %s.',
                implode(', ', array_map(
                    static fn (string $type): string => strtolower(ApplicationDocumentType::from($type)->label()),
                    $missing,
                )),
            ));
        }

        // One outstanding request per record. Two pending edits to the same
        // bank account would leave an administrator approving them in
        // sequence, the second silently overwriting the first, with no way to
        // tell afterwards which one the officer actually meant.
        $outstanding = ChangeRequest::query()
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->where('status', ChangeRequestStatus::Pending)
            ->exists();

        if ($outstanding) {
            throw new RuntimeException(
                'A change to this record is already awaiting a decision. Wait for that one to be decided first.'
            );
        }

        return DB::transaction(function () use ($subject, $changes, $reason, $requestedBy, $ipAddress, $documents): ChangeRequest {
            $request = ChangeRequest::create([
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'requested_by' => $requestedBy->id,
                'reason' => $reason,
                'changes' => $changes,
                'status' => ChangeRequestStatus::Pending,
            ]);

            foreach ($documents as $type => $file) {
                $this->storeEvidence($request, ApplicationDocumentType::from($type), $file, $requestedBy);
            }

            $label = $this->labelFor($subject);

            $this->audit->record(
                action: 'change_request.submitted',
                subject: $subject,
                summary: sprintf(
                    'Requested a change to %s (%s). Reason: %s',
                    $label,
                    implode(', ', array_keys($changes)),
                    $reason,
                ),
                actor: $requestedBy,
                metadata: ['change_request_id' => $request->id, 'changes' => $changes],
                ipAddress: $ipAddress,
            );

            // Only the desk that can decide it is told.
            $this->audience->notifyHoldersOf(
                Permissions::CHANGE_REQUESTS_REVIEW,
                new ChangeRequestSubmitted($request, $label, $requestedBy->fullName(), $this->urlFor($subject)),
                except: $requestedBy,
            );

            return $request;
        });
    }

    public function approve(
        ChangeRequest $request,
        User $reviewedBy,
        ?string $note = null,
        ?string $ipAddress = null,
    ): ChangeRequest {
        $this->assertPending($request);

        $subject = $request->subject;

        if ($subject === null) {
            throw new RuntimeException('The record this request refers to no longer exists.');
        }

        $request->loadMissing('documents');

        return DB::transaction(function () use ($request, $subject, $reviewedBy, $note, $ipAddress): ChangeRequest {
            $changes = array_intersect_key(
                $request->changes,
                array_flip(self::editableFieldsFor($subject)),
            );

            $replaced = [];

            foreach ($changes as $field => $value) {
                $replaced[$field] = $subject->{$field};
            }

            // Two fields are never taken at face value, for the same reasons
            // they are not accepted at registration: a branch code follows
            // from the bank, and date of birth and gender are read out of the
            // ID number rather than typed.
            if (array_key_exists('bank_name', $changes)) {
                $changes['bank_branch_code'] = SouthAfricanBanks::branchCodeFor((string) $changes['bank_name']);
                $replaced['bank_branch_code'] = $subject->bank_branch_code;
            }

            if (array_key_exists('id_number', $changes) && $subject instanceof Client) {
                $idNumber = (string) $changes['id_number'];
                $changes['date_of_birth'] = IdentityNumber::dateOfBirth($idNumber);
                $changes['gender'] = IdentityNumber::gender($idNumber);
                $replaced['date_of_birth'] = $subject->date_of_birth?->toDateString();
                $replaced['gender'] = $subject->gender;
            }

            $subject->fill($changes)->save();

            $request->update([
                'status' => ChangeRequestStatus::Approved,
                'reviewed_by' => $reviewedBy->id,
                'reviewed_at' => now(),
                'review_note' => $note,
                'replaced_values' => $replaced,
            ]);

            $replacedDocuments = $this->replaceApplicationDocuments($request, $subject, $reviewedBy);

            $label = $this->labelFor($subject);

            $this->audit->record(
                action: 'change_request.approved',
                subject: $subject,
                summary: sprintf(
                    'Approved a change to %s (%s), requested by %s.%s',
                    $label,
                    implode(', ', array_keys($changes)),
                    $request->requestedBy?->fullName() ?? 'a colleague',
                    $replacedDocuments === []
                        ? ''
                        : ' Documents replaced on '.implode('; ', $replacedDocuments).'.',
                ),
                actor: $reviewedBy,
                metadata: [
                    'change_request_id' => $request->id,
                    'applied' => $changes,
                    'replaced' => $replaced,
                    'documents_replaced' => $replacedDocuments,
                ],
                ipAddress: $ipAddress,
            );

            $request->requestedBy?->notify(
                new ChangeRequestReviewed($request->fresh(), $label, true, $reviewedBy->fullName(), $this->urlFor($subject)),
            );

            return $request->fresh();
        });
    }

    public function reject(
        ChangeRequest $request,
        string $note,
        User $reviewedBy,
        ?string $ipAddress = null,
    ): ChangeRequest {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $note, $reviewedBy, $ipAddress): ChangeRequest {
            $request->update([
                'status' => ChangeRequestStatus::Rejected,
                'reviewed_by' => $reviewedBy->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $subject = $request->subject;
            $label = $subject === null ? 'a record' : $this->labelFor($subject);

            if ($subject !== null) {
                $this->audit->record(
                    action: 'change_request.rejected',
                    subject: $subject,
                    summary: sprintf('Rejected a change to %s: %s', $label, $note),
                    actor: $reviewedBy,
                    metadata: ['change_request_id' => $request->id],
                    ipAddress: $ipAddress,
                );
            }

            $request->requestedBy?->notify(
                new ChangeRequestReviewed(
                    $request->fresh(),
                    $label,
                    false,
                    $reviewedBy->fullName(),
                    $subject === null ? '/change-requests' : $this->urlFor($subject),
                ),
            );

            return $request->fresh();
        });
    }

    /**
     * Writes an attached document to private storage.
     *
     * Same two rules as everywhere else a document is taken: a generated name,
     * because the one the browser sent is attacker controlled, and the private
     * disk, because an ID copy reachable by guessing a URL is a breach.
     */
    private function storeEvidence(
        ChangeRequest $request,
        ApplicationDocumentType $type,
        UploadedFile $file,
        User $uploadedBy,
    ): ChangeRequestDocument {
        $path = $file->storeAs(
            "change-requests/{$request->id}",
            Str::uuid()->toString().'.'.$file->extension(),
            ['disk' => self::DISK],
        );

        return ChangeRequestDocument::create([
            'change_request_id' => $request->id,
            'type' => $type,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
            'uploaded_by' => $uploadedBy->id,
        ]);
    }

    /**
     * Puts the approved documents onto the client's open loan files.
     *
     * Only files still in flight are touched. A disbursed or declined
     * application keeps the documents it was actually decided on: overwriting
     * those would rewrite the evidence behind a decision already taken and
     * money already paid, which is the opposite of what an audit trail is for.
     *
     * @return array<int, string>
     */
    private function replaceApplicationDocuments(
        ChangeRequest $request,
        Model $subject,
        User $approvedBy,
    ): array {
        if (! $subject instanceof Client) {
            return [];
        }

        $documents = $request->documents;

        if ($documents->isEmpty()) {
            return [];
        }

        $openApplications = $subject->loanApplications()
            ->whereNotIn('status', [
                LoanApplicationStatus::Disbursed,
                LoanApplicationStatus::Declined,
                LoanApplicationStatus::Cancelled,
            ])
            ->get();

        $touched = [];

        foreach ($openApplications as $application) {
            foreach ($documents as $document) {
                $existing = ApplicationDocument::query()
                    ->where('loan_application_id', $application->id)
                    ->where('type', $document->type)
                    ->first();

                $copy = "applications/{$application->id}/".Str::uuid()->toString()
                    .'.'.pathinfo($document->path, PATHINFO_EXTENSION);

                Storage::disk(self::DISK)->copy($document->path, $copy);

                $supersededPath = $existing?->path;

                ApplicationDocument::updateOrCreate(
                    ['loan_application_id' => $application->id, 'type' => $document->type],
                    [
                        'original_name' => $document->original_name,
                        'path' => $copy,
                        'mime_type' => $document->mime_type,
                        'size_bytes' => $document->size_bytes,
                        'uploaded_by' => $approvedBy->id,
                    ],
                );

                // The row no longer points at it, and keeping an identity
                // document nothing references is a retention problem rather
                // than a safety net. The request keeps its own copy.
                if ($supersededPath !== null) {
                    Storage::disk(self::DISK)->delete($supersededPath);
                }

                $touched[] = $application->application_number.': '.$document->type->label();
            }
        }

        return $touched;
    }

    /**
     * A bank and a province are closed lists at registration, and a change
     * request must not be the way round that. Checked when the request is
     * raised rather than when it is approved, so the officer is told at once
     * instead of an administrator finding out later.
     *
     * @param  array<string, mixed>  $changes
     */
    private function assertClosedListsHold(array $changes): void
    {
        $bank = $changes['bank_name'] ?? null;

        if ($bank !== null && $bank !== '' && SouthAfricanBanks::branchCodeFor((string) $bank) === null) {
            throw new RuntimeException('That is not a bank on the list.');
        }

        $province = $changes['province'] ?? null;

        if ($province !== null && $province !== ''
            && ! in_array((string) $province, SouthAfricanProvinces::all(), strict: true)) {
            throw new RuntimeException('That is not one of the nine provinces.');
        }
    }

    private function assertPending(ChangeRequest $request): void
    {
        if (! $request->isPending()) {
            throw new RuntimeException(
                'This request is '.$request->status->label().' and cannot be decided again.'
            );
        }
    }

    /**
     * Where the record lives in the browser, so a notification can lead to it.
     */
    private function urlFor(Model $subject): string
    {
        return match (true) {
            $subject instanceof Client => '/clients/'.$subject->getKey(),
            $subject instanceof Recruiter => '/recruiters/'.$subject->getKey(),
            default => '/change-requests',
        };
    }

    private function labelFor(Model $subject): string
    {
        return match (true) {
            $subject instanceof Client => $subject->fullName().' ('.$subject->client_number.')',
            $subject instanceof Recruiter => $subject->fullName().' ('.$subject->recruiter_number.')',
            default => 'a record',
        };
    }
}
