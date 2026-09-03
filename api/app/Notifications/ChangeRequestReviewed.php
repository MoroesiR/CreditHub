<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ChangeRequest;
use Illuminate\Notifications\Notification;

/**
 * Tells the officer who asked for a change what was decided.
 *
 * Goes to that one person. Nobody else asked, so nobody else is told.
 */
final class ChangeRequestReviewed extends Notification
{
    public function __construct(
        private readonly ChangeRequest $request,
        private readonly string $subjectLabel,
        private readonly bool $approved,
        private readonly string $reviewedBy,
        private readonly string $subjectUrl,
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
        return [
            'change_request_id' => $this->request->id,
            'approved' => $this->approved,
            'title' => $this->approved
                ? 'Change approved: '.$this->subjectLabel
                : 'Change rejected: '.$this->subjectLabel,
            'body' => $this->approved
                ? sprintf('%s applied your change to %s.', $this->reviewedBy, $this->subjectLabel)
                : sprintf(
                    '%s rejected your change to %s. %s',
                    $this->reviewedBy,
                    $this->subjectLabel,
                    $this->request->review_note ?? '',
                ),
            // The officer who asked is sent to the record too: what they want
            // to see is whether it now reads correctly.
            'action_url' => $this->subjectUrl.'?request='.$this->request->id,
        ];
    }
}
