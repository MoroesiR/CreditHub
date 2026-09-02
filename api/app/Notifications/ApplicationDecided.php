<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LoanApplication;
use Illuminate\Notifications\Notification;

/**
 * Tells the officer who submitted an application how it was decided.
 *
 * Stored in the database rather than pushed at the screen: the officer is
 * usually with another client when a decision lands, and one they missed while
 * away from the desk still has to be waiting when they come back.
 */
final class ApplicationDecided extends Notification
{
    public function __construct(
        private readonly LoanApplication $application,
        private readonly bool $approved,
        private readonly ?string $declineReason,
        private readonly string $decidedBy,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $client = $this->application->client?->fullName() ?? 'the client';

        return [
            'application_id' => $this->application->id,
            'application_number' => $this->application->application_number,
            'client_name' => $client,
            'approved' => $this->approved,
            'decided_by' => $this->decidedBy,
            'decline_reason' => $this->declineReason,
            'title' => $this->approved
                ? "{$this->application->application_number} approved"
                : "{$this->application->application_number} declined",
            'body' => $this->approved
                ? "{$client}: approved by {$this->decidedBy}. The agreement can now be signed."
                : "{$client}: declined by {$this->decidedBy}. {$this->declineReason}",
            'action_url' => "/applications/{$this->application->id}",
        ];
    }
}
