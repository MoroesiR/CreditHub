<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the audit trail.
 *
 * Called from inside the transaction that makes the change, so an event can
 * never describe something that was rolled back, and a change can never
 * commit without its event.
 *
 * The actor's name is copied onto the event rather than only referenced: the
 * trail has to stay readable if that staff account is closed later.
 */
final class AuditRecorder
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        Model $subject,
        string $summary,
        User $actor,
        array $metadata = [],
        ?string $ipAddress = null,
    ): AuditEvent {
        return AuditEvent::create([
            'user_id' => $actor->id,
            'actor_name' => $actor->fullName(),
            'action' => $action,
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(),
            'summary' => $summary,
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }
}
