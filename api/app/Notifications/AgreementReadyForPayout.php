<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LoanApplication;
use Illuminate\Notifications\Notification;

/**
 * Tells the payouts desk that a signed loan is waiting.
 *
 * Sent when the agreement is signed, which is the moment the file leaves
 * origination - the same principle as notifying the officer on a decision:
 * whoever has to act next is told, rather than left to spot it on a list.
 */
final class AgreementReadyForPayout extends Notification
{
    public function __construct(
        private readonly LoanApplication $application,
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
            'approved' => true,
            'title' => "{$this->application->application_number} ready to pay",
            'body' => sprintf(
                '%s signed for R%s. The file is in the payout queue awaiting verification.',
                $client,
                number_format($this->application->amount, 2),
            ),
            'action_url' => '/disbursements',
        ];
    }
}
