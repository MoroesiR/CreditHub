<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ChangeRequest;
use Illuminate\Notifications\Notification;

/**
 * Tells administrators that a correction is waiting on them.
 *
 * Addressed to the review permission rather than to a named person, so it
 * reaches whoever is actually on duty rather than sitting unread with someone
 * who has left.
 */
final class ChangeRequestSubmitted extends Notification
{
    public function __construct(
        private readonly ChangeRequest $request,
        private readonly string $subjectLabel,
        private readonly string $requestedBy,
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
        $fields = implode(', ', array_keys($this->request->changes));

        return [
            'change_request_id' => $this->request->id,
            'title' => 'Change requested: '.$this->subjectLabel,
            'body' => sprintf(
                '%s asked to change %s. Reason: %s',
                $this->requestedBy,
                $fields === '' ? 'this record' : $fields,
                $this->request->reason,
            ),
            'action_url' => '/change-requests',
        ];
    }
}
