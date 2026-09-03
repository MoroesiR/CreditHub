<?php

declare(strict_types=1);

namespace App\Services\ChangeRequests;

use App\Enums\ChangeRequestStatus;
use App\Models\ChangeRequest;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ChangeRequestService
{
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

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly NotificationAudience $audience,
    ) {}

    /**
     * @return array<int, string>
     */
    public static function editableFieldsFor(Model $subject): array
    {
        return self::EDITABLE[$subject::class] ?? [];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function submit(
        Model $subject,
        array $changes,
        string $reason,
        User $requestedBy,
        ?string $ipAddress = null,
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

        return DB::transaction(function () use ($subject, $changes, $reason, $requestedBy, $ipAddress): ChangeRequest {
            $request = ChangeRequest::create([
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'requested_by' => $requestedBy->id,
                'reason' => $reason,
                'changes' => $changes,
                'status' => ChangeRequestStatus::Pending,
            ]);

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
                new ChangeRequestSubmitted($request, $label, $requestedBy->fullName()),
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

            $label = $this->labelFor($subject);

            $this->audit->record(
                action: 'change_request.approved',
                subject: $subject,
                summary: sprintf(
                    'Approved a change to %s (%s), requested by %s.',
                    $label,
                    implode(', ', array_keys($changes)),
                    $request->requestedBy?->fullName() ?? 'a colleague',
                ),
                actor: $reviewedBy,
                metadata: ['change_request_id' => $request->id, 'applied' => $changes, 'replaced' => $replaced],
                ipAddress: $ipAddress,
            );

            $request->requestedBy?->notify(
                new ChangeRequestReviewed($request->fresh(), $label, true, $reviewedBy->fullName()),
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
                new ChangeRequestReviewed($request->fresh(), $label, false, $reviewedBy->fullName()),
            );

            return $request->fresh();
        });
    }

    private function assertPending(ChangeRequest $request): void
    {
        if (! $request->isPending()) {
            throw new RuntimeException(
                'This request is '.$request->status->label().' and cannot be decided again.'
            );
        }
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
