<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LoanApplication;
use Illuminate\Notifications\Notification;

/**
 * Tells the credit desk that a file is waiting on a decision.
 *
 * Addressed to the permission to decide, so it reaches whoever is on duty and
 * nobody else. An officer who captured the file already knows it exists, and a
 * payouts officer cannot act on it for another two stages yet.
 */
final class ApplicationAwaitingDecision extends Notification
{
    public function __construct(
        private readonly LoanApplication $application,
        private readonly string $submittedBy,
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
        $client = $this->application->client?->fullName() ?? 'A client';

        return [
            'application_id' => $this->application->id,
            'application_number' => $this->application->application_number,
            'title' => $this->application->application_number.' awaiting decision',
            'body' => sprintf(
                '%s submitted %s for R%s over %d months.',
                $this->submittedBy,
                $client,
                number_format($this->application->amount, 2),
                $this->application->term_months,
            ),
            'action_url' => '/applications/'.$this->application->id,
        ];
    }
}
