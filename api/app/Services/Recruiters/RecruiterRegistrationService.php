<?php

declare(strict_types=1);

namespace App\Services\Recruiters;

use App\Models\Recruiter;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Reference\ReferenceNumberService;
use App\Support\SouthAfricanBanks;
use Illuminate\Support\Facades\DB;

final class RecruiterRegistrationService
{
    public function __construct(
        private readonly ReferenceNumberService $references,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, User $registeredBy, ?string $ipAddress = null): Recruiter
    {
        return DB::transaction(function () use ($data, $registeredBy, $ipAddress): Recruiter {
            $bank = $data['bank_name'] ?? null;

            $recruiter = Recruiter::create([
                ...$data,
                // Set here rather than left to the column default: the model
                // returned from create() would otherwise carry a null the
                // caller could read as inactive.
                'is_active' => true,
                // Derived from the bank rather than taken from the request, so
                // the code on file always routes to the bank that was chosen.
                'bank_branch_code' => $bank === null ? null : SouthAfricanBanks::branchCodeFor($bank),
                'recruiter_number' => $this->references->nextRecruiterNumber(),
                'registered_by' => $registeredBy->id,
            ]);

            $this->audit->record(
                action: 'recruiter.registered',
                subject: $recruiter,
                summary: "Registered recruiter {$recruiter->fullName()} as {$recruiter->recruiter_number}.",
                actor: $registeredBy,
                metadata: ['recruiter_number' => $recruiter->recruiter_number],
                ipAddress: $ipAddress,
            );

            return $recruiter;
        });
    }
}
