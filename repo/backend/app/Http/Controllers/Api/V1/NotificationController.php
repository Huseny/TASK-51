<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));
        $page = max(1, (int) $request->query('page', 1));

        $allNotifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        $aggregated = $this->aggregate($allNotifications);

        $total = $aggregated->count();
        $offset = ($page - 1) * $perPage;

        $paginator = new LengthAwarePaginator(
            $aggregated->slice($offset, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json($paginator);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to update this notification',
            ], 403);
        }

        $updatedCount = 0;
        if ($notification->group_key !== null) {
            $updatedCount = Notification::query()
                ->where('user_id', $request->user()->id)
                ->where('group_key', $notification->group_key)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
        } else {
            $updatedCount = Notification::query()
                ->where('id', $notification->id)
                ->where('user_id', $request->user()->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
        }

        return response()->json([
            'message' => 'Notification marked as read',
            'updated_count' => $updatedCount,
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updatedCount = Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'All notifications marked as read',
            'updated_count' => $updatedCount,
        ]);
    }

    /**
     * @param  Collection<int, Notification>  $notifications
     * @return Collection<int, array<string, mixed>>
     */
    private function aggregate(Collection $notifications): Collection
    {
        $items = [];

        foreach ($notifications as $notification) {
            if (! $notification->is_read && $notification->group_key) {
                $existingIndex = array_search($notification->group_key, array_column($items, 'group_key'), true);

                if ($existingIndex !== false) {
                    $items[$existingIndex]['count']++;
                    $items[$existingIndex]['notification_ids'][] = $notification->id;
                    continue;
                }
            }

            $items[] = [
                'id' => $notification->id,
                'type' => $notification->type,
                'priority' => $notification->priority,
                'title' => $notification->title,
                'body' => $notification->body,
                'data' => $notification->data,
                'group_key' => $notification->group_key,
                'is_read' => $notification->is_read,
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at?->toISOString(),
                'count' => 1,
                'notification_ids' => [$notification->id],
            ];
        }

        foreach ($items as &$item) {
            if ($item['count'] > 1) {
                $item['title'] = $this->aggregatedTitle((string) $item['group_key'], (int) $item['count']);
            }
        }

        return collect($items);
    }

    private function aggregatedTitle(string $groupKey, int $count): string
    {
        if (str_starts_with($groupKey, 'reply_')) {
            return sprintf('%d new replies', $count);
        }

        if (str_starts_with($groupKey, 'comment_')) {
            return sprintf('%d new comments', $count);
        }

        if (str_starts_with($groupKey, 'mention_')) {
            return sprintf('%d new mentions', $count);
        }

        if (str_starts_with($groupKey, 'follower_')) {
            return sprintf('%d new followers', $count);
        }

        if (str_starts_with($groupKey, 'moderation_')) {
            return sprintf('%d moderation updates', $count);
        }

        if (str_starts_with($groupKey, 'announcement_')) {
            return sprintf('%d system announcements', $count);
        }

        if (preg_match('/ride_(\d+)/', $groupKey, $matches) === 1) {
            return sprintf('%d new updates on Ride #%d', $count, (int) $matches[1]);
        }

        return sprintf('%d new updates', $count);
    }
}
