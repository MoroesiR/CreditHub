<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LoanApplication;
use Illuminate\Notifications\Notification;

/**
 * Tells the officer who originated the loan that the money has gone out, so
 * they can tell the client without having to chase the payouts desk.
 */
final class LoanDisbursed extends Notification
{
    public function __construct(
        private readonly LoanApplication $application,
        private readonly float $amount,
        private readonly string $reference,
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
            'approved' => true,
            'title' => "{$this->application->application_number} paid out",
            'body' => sprintf(
                'R%s was released to %s, reference %s.',
                number_format($this->amount, 2),
                $client,
                $this->reference,
            ),
            'action_url' => "/applications/{$this->application->id}",
        ];
    }
}
