<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;

/**
 * Decides who a notification goes to.
 *
 * Two addresses only: the person whose file it is, or whoever holds the
 * permission for the desk that has to act next. Nothing is broadcast.
 *
 * Resolved here so the rule is stated once and cannot drift between callers.
 */
final class NotificationAudience
{
    /**
     * Everyone who may act on the thing being announced.
     *
     * Permission rather than role: a permission moved onto another role keeps
     * working without anyone remembering to update a notification.
     */
    public function holdersOf(string $permission): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas(
                'roles.permissions',
                fn ($query) => $query->where('slug', $permission),
            )
            ->get();
    }

    /**
     * Notify everyone holding a permission, optionally leaving out the person
     * who caused the event - being told about your own action is noise.
     */
    public function notifyHoldersOf(
        string $permission,
        Notification $notification,
        ?User $except = null,
    ): int {
        $recipients = $this->holdersOf($permission)
            ->reject(fn (User $user): bool => $except !== null && $user->is($except));

        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
        }

        return $recipients->count();
    }
}
