<?php

namespace App\Services;

use App\Models\GroupChat;
use App\Models\GroupChatParticipant;
use App\Models\GroupMessage;
use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class GroupChatLifecycleService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function handleRideTransition(RideOrder $order, string $fromStatus, string $toStatus, array $context = []): void
    {
        if ($fromStatus === 'matching' && $toStatus === 'accepted') {
            $this->onAccepted($order);
            return;
        }

        if (in_array($toStatus, ['completed', 'canceled'], true)) {
            $this->disband($order, $toStatus);
            return;
        }

        if ($toStatus === 'matching') {
            $previousDriverId = isset($context['previous_driver_id']) ? (int) $context['previous_driver_id'] : null;
            $this->removeDriver($order, $previousDriverId);
        }
    }

    public function disband(RideOrder $order, string $reasonStatus): void
    {
        $chat = GroupChat::query()->where('ride_order_id', $order->id)->first();

        if (! $chat || $chat->status === 'disbanded') {
            return;
        }

        $chat->status = 'disbanded';
        $chat->disbanded_at = now();
        $chat->save();

        GroupChatParticipant::query()
            ->where('group_chat_id', $chat->id)
            ->whereNull('left_at')
            ->update(['left_at' => now()]);

        $this->createSystemNotice($chat, sprintf('Group has been disbanded - ride %s.', $reasonStatus));

        Log::channel('app')->info(
            sprintf('Group chat #%d disbanded (ride %s)', $chat->id, $reasonStatus),
            ['group_chat_id' => $chat->id, 'ride_order_id' => $order->id]
        );
    }

    private function onAccepted(RideOrder $order): void
    {
        if (! $order->driver_id) {
            return;
        }

        $chat = GroupChat::query()->firstOrCreate(
            ['ride_order_id' => $order->id],
            ['status' => 'active']
        );

        if ($chat->wasRecentlyCreated) {
            $this->upsertParticipant($chat, $order->rider_id);
            $this->upsertParticipant($chat, $order->driver_id);

            $riderName = $order->rider?->username ?? 'Rider';
            $driverName = $order->driver?->username ?? 'Driver';

            $this->createSystemNotice(
                $chat,
                sprintf('Group ride created - %s and %s are now connected.', $riderName, $driverName)
            );

            Log::channel('app')->info(
                sprintf('Group chat #%d created for ride order #%d', $chat->id, $order->id),
                ['group_chat_id' => $chat->id, 'ride_order_id' => $order->id]
            );

            return;
        }

        if ($chat->status === 'disbanded') {
            return;
        }

        $joined = $this->upsertParticipant($chat, $order->driver_id);
        if ($joined) {
            $driverName = $order->driver?->username ?? 'Driver';
            $this->createSystemNotice($chat, sprintf('%s has joined the group.', $driverName));
        }
    }

    private function removeDriver(RideOrder $order, ?int $driverId): void
    {
        if (! $driverId) {
            return;
        }

        $chat = GroupChat::query()
            ->where('ride_order_id', $order->id)
            ->where('status', 'active')
            ->first();

        if (! $chat) {
            return;
        }

        $participant = GroupChatParticipant::query()
            ->where('group_chat_id', $chat->id)
            ->where('user_id', $driverId)
            ->whereNull('left_at')
            ->first();

        if (! $participant) {
            return;
        }

        $participant->left_at = now();
        $participant->save();

        $driverName = User::query()->whereKey($driverId)->value('username') ?? 'Driver';
        $this->createSystemNotice($chat, sprintf('%s has left the group.', $driverName));
    }

    private function upsertParticipant(GroupChat $chat, int $userId): bool
    {
        $participant = GroupChatParticipant::query()->firstOrNew([
            'group_chat_id' => $chat->id,
            'user_id' => $userId,
        ]);

        $justJoined = false;

        if (! $participant->exists || $participant->left_at !== null) {
            $participant->joined_at = now();
            $participant->left_at = null;
            $participant->dnd_start = $participant->dnd_start ?: '22:00:00';
            $participant->dnd_end = $participant->dnd_end ?: '07:00:00';
            $justJoined = true;
        }

        $participant->save();

        return $justJoined;
    }

    private function createSystemNotice(GroupChat $chat, string $content): void
    {
        GroupMessage::query()->create([
            'group_chat_id' => $chat->id,
            'sender_id' => null,
            'content' => $content,
            'type' => 'system_notice',
            'created_at' => now(),
        ]);
    }
}
