<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\MarkChatReadRequest;
use App\Http\Requests\Chat\SendGroupMessageRequest;
use App\Http\Requests\Chat\UpdateDndRequest;
use App\Models\GroupChat;
use App\Models\GroupChatParticipant;
use App\Models\GroupMessage;
use App\Models\MessageReadReceipt;
use App\Models\RideOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GroupChatController extends Controller
{
    public function showByRide(Request $request, RideOrder $rideOrder): JsonResponse
    {
        $chat = GroupChat::query()->where('ride_order_id', $rideOrder->id)->first();

        if (! $chat) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Group chat not found for this ride',
            ], 404);
        }

        if (! $this->getActiveParticipant($chat->id, $request->user()->id)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You are not an active participant in this chat',
            ], 403);
        }

        $messages = $chat->messages()
            ->with(['sender:id,username', 'readReceipts.user:id,username'])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->sortBy('id')
            ->values();

        $unreadCount = $chat->messages()
            ->whereNotIn('id', function ($query) use ($request): void {
                $query->from('message_read_receipts')
                    ->select('message_id')
                    ->where('user_id', $request->user()->id);
            })
            ->where('sender_id', '!=', $request->user()->id)
            ->count();

        return response()->json([
            'chat' => $chat->load(['participants.user:id,username']),
            'messages' => $messages,
            'unread_count' => $unreadCount,
        ]);
    }

    public function sendMessage(SendGroupMessageRequest $request, GroupChat $chat): JsonResponse
    {
        if ($chat->status === 'disbanded') {
            return response()->json([
                'error' => 'chat_disbanded',
                'message' => 'This chat has been disbanded',
            ], 403);
        }

        $participant = $this->getActiveParticipant($chat->id, $request->user()->id);

        if (! $participant) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You are not an active participant in this chat',
            ], 403);
        }

        $message = GroupMessage::query()->create([
            'group_chat_id' => $chat->id,
            'sender_id' => $request->user()->id,
            'content' => $request->validated('content'),
            'type' => 'user_message',
            'created_at' => now(),
        ]);

        Log::channel('app')->info(
            sprintf('User #%d sent message in chat #%d', $request->user()->id, $chat->id),
            ['user_id' => $request->user()->id, 'chat_id' => $chat->id]
        );

        return response()->json([
            'message' => $message->load('sender:id,username'),
        ], 201);
    }

    public function getMessages(Request $request, GroupChat $chat): JsonResponse
    {
        if (! $this->getActiveParticipant($chat->id, $request->user()->id)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You are not an active participant in this chat',
            ], 403);
        }

        $afterId = (int) $request->query('after_id', 0);
        $limit = max(1, min((int) $request->query('limit', 50), 100));

        $messages = $chat->messages()
            ->with(['sender:id,username', 'readReceipts.user:id,username'])
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(function (GroupMessage $message) use ($request) {
                $payload = $message->toArray();
                $payload['is_read_by_me'] = $message->readReceipts->contains('user_id', $request->user()->id);
                return $payload;
            });

        return response()->json([
            'messages' => $messages,
        ]);
    }

    public function markRead(MarkChatReadRequest $request, GroupChat $chat): JsonResponse
    {
        if (! $this->getActiveParticipant($chat->id, $request->user()->id)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You are not an active participant in this chat',
            ], 403);
        }

        $messageIds = $chat->messages()
            ->where('id', '<=', $request->validated('up_to_message_id'))
            ->whereNotIn('id', function ($query) use ($request): void {
                $query->from('message_read_receipts')
                    ->select('message_id')
                    ->where('user_id', $request->user()->id);
            })
            ->pluck('id');

        $insertRows = $messageIds->map(fn (int $id) => [
            'message_id' => $id,
            'user_id' => $request->user()->id,
            'read_at' => now(),
        ])->all();

        if (! empty($insertRows)) {
            MessageReadReceipt::query()->insert($insertRows);
        }

        return response()->json([
            'newly_marked' => count($insertRows),
        ]);
    }

    public function updateDnd(UpdateDndRequest $request, GroupChat $chat): JsonResponse
    {
        $participant = $this->getActiveParticipant($chat->id, $request->user()->id);

        if (! $participant) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You are not an active participant in this chat',
            ], 403);
        }

        $participant->dnd_start = $request->validated('dnd_start').':00';
        $participant->dnd_end = $request->validated('dnd_end').':00';
        $participant->save();

        return response()->json([
            'participant' => $participant,
        ]);
    }

    private function getActiveParticipant(int $chatId, int $userId): ?GroupChatParticipant
    {
        return GroupChatParticipant::query()
            ->where('group_chat_id', $chatId)
            ->where('user_id', $userId)
            ->active()
            ->first();
    }
}
