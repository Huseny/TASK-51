<?php

namespace Tests\Integration;

use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EndToEndRideLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_to_end_ride_lifecycle_with_chat_and_notification(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);

        Sanctum::actingAs($rider);

        $createResponse = $this->postJson('/api/v1/ride-orders', [
            'origin_address' => '123 Main St',
            'destination_address' => 'Airport Terminal',
            'rider_count' => 2,
            'time_window_start' => now()->addHour()->format('Y-m-d H:i'),
            'time_window_end' => now()->addHours(2)->format('Y-m-d H:i'),
            'notes' => 'Luggage: small',
        ])->assertStatus(201);

        $rideId = (int) $createResponse->json('order.id');
        $this->assertDatabaseHas('ride_orders', ['id' => $rideId, 'status' => 'matching']);

        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/driver/available-rides')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $rideId]);

        $this->patchJson('/api/v1/ride-orders/'.$rideId.'/transition', [
            'action' => 'accept',
        ])->assertStatus(200)
            ->assertJsonPath('order.status', 'accepted');

        Sanctum::actingAs($rider);

        $chatResponse = $this->getJson('/api/v1/ride-orders/'.$rideId.'/chat')
            ->assertStatus(200);

        $chatId = (int) $chatResponse->json('chat.id');
        $this->assertSame('active', $chatResponse->json('chat.status'));
        $this->assertContains(
            'system_notice',
            collect($chatResponse->json('messages'))->pluck('type')->all()
        );

        $this->postJson('/api/v1/group-chats/'.$chatId.'/messages', [
            'content' => 'I am at the pickup point.',
        ])->assertStatus(201)
            ->assertJsonPath('message.content', 'I am at the pickup point.');

        Sanctum::actingAs($driver);

        $this->patchJson('/api/v1/ride-orders/'.$rideId.'/transition', [
            'action' => 'start',
        ])->assertStatus(200)
            ->assertJsonPath('order.status', 'in_progress');

        $this->patchJson('/api/v1/ride-orders/'.$rideId.'/transition', [
            'action' => 'complete',
        ])->assertStatus(200)
            ->assertJsonPath('order.status', 'completed');

        Sanctum::actingAs($rider);

        $disbandedChat = $this->getJson('/api/v1/ride-orders/'.$rideId.'/chat')
            ->assertStatus(200);
        $this->assertSame('disbanded', $disbandedChat->json('chat.status'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $rider->id,
            'type' => 'ride_update',
            'title' => 'Ride completed',
        ]);

        $this->assertDatabaseHas('ride_orders', [
            'id' => $rideId,
            'status' => 'completed',
            'driver_id' => $driver->id,
        ]);
    }
}
