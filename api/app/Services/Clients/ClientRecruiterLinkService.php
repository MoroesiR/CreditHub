<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Models\Client;
use App\Models\Recruiter;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Attaches a client to the recruiter who introduced them, after the fact.
 *
 * The case this exists for: a client is registered while the recruiter who
 * brought them in is not yet on file, so the client is captured as a walk-in.
 * Once that recruiter is registered, the introduction has to be recorded
 * against them or the commission is never earned.
 *
 * Reassignment is deliberately refused. A client already attached to one
 * recruiter cannot be moved to another here, because the link decides who is
 * paid - moving it silently would move the money.
 */
final class ClientRecruiterLinkService
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    public function link(
        Client $client,
        Recruiter $recruiter,
        User $linkedBy,
        ?string $ipAddress = null,
    ): Client {
        if ($client->recruiter_id !== null) {
            throw new RuntimeException(
                'This client is already attached to a recruiter. Reassignment is not done here.'
            );
        }

        if (! $recruiter->is_active) {
            throw new RuntimeException('That recruiter is not active.');
        }

        return DB::transaction(function () use ($client, $recruiter, $linkedBy, $ipAddress): Client {
            $client->update(['recruiter_id' => $recruiter->id]);

            $this->audit->record(
                action: 'client.recruiter_linked',
                subject: $client,
                summary: "Attached {$client->fullName()} ({$client->client_number}) to recruiter "
                    ."{$recruiter->fullName()} ({$recruiter->recruiter_number}).",
                actor: $linkedBy,
                metadata: [
                    'recruiter_id' => $recruiter->id,
                    'recruiter_number' => $recruiter->recruiter_number,
                ],
                ipAddress: $ipAddress,
            );

            // The same event is recorded against the recruiter, so their own
            // trail shows every introduction credited to them.
            $this->audit->record(
                action: 'recruiter.client_linked',
                subject: $recruiter,
                summary: "Credited with introducing {$client->fullName()} ({$client->client_number}).",
                actor: $linkedBy,
                metadata: ['client_id' => $client->id, 'client_number' => $client->client_number],
                ipAddress: $ipAddress,
            );

            return $client->load(['recruiter', 'latestAffordability']);
        });
    }
}
