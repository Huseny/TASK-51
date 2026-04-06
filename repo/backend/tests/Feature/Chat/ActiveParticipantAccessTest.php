<?php

namespace Tests\Feature\Chat;

use App\Models\GroupChat;
use App\Models\RideOrder;
use App\Models\User;
use App\Services\RideOrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActiveParticipantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_removed_participant_cannot_view_chat(): void
    {
        [$driver, $chat] = $this->seedRemovedDriverChat();

        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/ride-orders/'.$chat->ride_order_id.'/chat')
            ->assertStatus(403);
    }

    public function test_removed_participant_cannot_fetch_messages(): void
    {
        [$driver, $chat] = $this->seedRemovedDriverChat();

        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/group-chats/'.$chat->id.'/messages')
            ->assertStatus(403);
    }

    public function test_removed_participant_cannot_mark_read(): void
    {
        [$driver, $chat] = $this->seedRemovedDriverChat();
        $messageId = $chat->messages()->latest('id')->value('id');

        Sanctum::actingAs($driver);

        $this->postJson('/api/v1/group-chats/'.$chat->id.'/read', [
            'up_to_message_id' => $messageId,
        ])->assertStatus(403);
    }

    public function test_removed_participant_cannot_update_dnd(): void
    {
        [$driver, $chat] = $this->seedRemovedDriverChat();

        Sanctum::actingAs($driver);

        $this->patchJson('/api/v1/group-chats/'.$chat->id.'/dnd', [
            'dnd_start' => '09:00',
            'dnd_end' => '17:00',
        ])->assertStatus(403);
    }

    /**
     * @return array{User, GroupChat}
     */
    private function seedRemovedDriverChat(): array
    {
        $stateMachine = app(RideOrderStateMachine::class);
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);
        $newDriver = User::factory()->create(['role' => 'driver']);
        $ride = RideOrder::factory()->create(['status' => 'matching', 'rider_id' => $rider->id]);

        $accepted = $stateMachine->transition($ride, 'accept', $driver);
        $matching = $stateMachine->transition($accepted, 'reassign', null, ['reason' => 'manual_reassignment']);
        $stateMachine->transition($matching, 'accept', $newDriver);

        $chat = GroupChat::query()->where('ride_order_id', $ride->id)->firstOrFail();
        $chat->messages()->create([
            'sender_id' => $newDriver->id,
            'content' => 'Driver switched',
            'type' => 'user_message',
            'created_at' => now(),
        ]);

        return [$driver, $chat];
    }
}
