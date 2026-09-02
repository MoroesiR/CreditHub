<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A member of staff's own notifications.
 *
 * Every route here is scoped to the signed-in user's own relation, so there is
 * no permission to check and no way to read anyone else's.
 */
final class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($notification): array => [
                'id' => $notification->id,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
                ...$notification->data,
            ]);

        return response()->json([
            'data' => $notifications,
            'meta' => ['unread' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->first();

        abort_if($record === null, Response::HTTP_NOT_FOUND);

        $record->markAsRead();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
