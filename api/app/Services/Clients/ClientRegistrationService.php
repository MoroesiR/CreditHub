<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Models\Client;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Recruiters\RecruiterRegistrationService;
use App\Services\Reference\ReferenceNumberService;
use App\Support\IdentityNumber;
use App\Support\SouthAfricanBanks;
use App\Support\SouthAfricanProvinces;
use Illuminate\Support\Facades\DB;

/**
 * Registration is one transaction covering three things that must not exist
 * apart: the client, the affordability assessment taken at the same interview,
 * and - when the client was introduced by someone not yet on file - the
 * recruiter record they are linked to.
 */
final class ClientRegistrationService
{
    public function __construct(
        private readonly ReferenceNumberService $references,
        private readonly RecruiterRegistrationService $recruiters,
        private readonly AffordabilityCalculator $affordability,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $client
     * @param  array<string, mixed>  $affordability
     * @param  array<string, mixed>|null  $newRecruiter  Recruiter to create and link, when the introducer is not on file.
     */
    public function register(
        array $client,
        array $affordability,
        User $registeredBy,
        ?array $newRecruiter = null,
        ?string $ipAddress = null,
    ): Client {
        return DB::transaction(function () use ($client, $affordability, $registeredBy, $newRecruiter, $ipAddress): Client {
            $recruiterId = $client['recruiter_id'] ?? null;

            if ($newRecruiter !== null) {
                $recruiterId = $this->recruiters->register($newRecruiter, $registeredBy, $ipAddress)->id;
            }

            $bank = $client['bank_name'] ?? null;
            $idNumber = (string) $client['id_number'];

            $record = Client::create([
                ...$client,
                // Read out of the ID number, never taken from the request: a
                // keyed date of birth could contradict the ID on its own record.
                'date_of_birth' => IdentityNumber::dateOfBirth($idNumber),
                'gender' => IdentityNumber::gender($idNumber),
                'country' => SouthAfricanProvinces::COUNTRY,
                // Derived from the bank rather than taken from the request, so
                // the code on file always routes to the bank that was chosen.
                'bank_branch_code' => $bank === null ? null : SouthAfricanBanks::branchCodeFor($bank),
                'recruiter_id' => $recruiterId,
                'client_number' => $this->references->nextClientNumber(),
                'registered_by' => $registeredBy->id,
            ]);

            $assessment = $record->affordabilityAssessments()->create([
                ...$affordability,
                'disposable_income' => $this->affordability->disposableIncome($affordability),
                'assessed_by' => $registeredBy->id,
                'assessed_at' => now(),
            ]);

            $record->load('recruiter');

            $introducedBy = $record->recruiter === null
                ? 'No recruiter, walk-in.'
                : "Introduced by {$record->recruiter->fullName()} ({$record->recruiter->recruiter_number}).";

            $this->audit->record(
                action: 'client.registered',
                subject: $record,
                summary: "Registered client {$record->fullName()} as {$record->client_number}. {$introducedBy}",
                actor: $registeredBy,
                metadata: [
                    'client_number' => $record->client_number,
                    'recruiter_id' => $record->recruiter_id,
                    'disposable_income' => $assessment->disposable_income,
                ],
                ipAddress: $ipAddress,
            );

            return $record->load('latestAffordability');
        });
    }
}
