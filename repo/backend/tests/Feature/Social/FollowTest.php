<?php

namespace Tests\Feature\Social;

use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_follow_another_user(): void
    {
        $follower = User::factory()->create(['role' => 'rider']);
        $followed = User::factory()->create(['role' => 'driver']);

        Sanctum::actingAs($follower);

        $this->postJson('/api/v1/follows', [
            'followed_id' => $followed->id,
        ])->assertStatus(201)
            ->assertJsonPath('message', 'Followed successfully');

        $this->assertDatabaseHas('user_follows', [
            'follower_id' => $follower->id,
            'followed_id' => $followed->id,
        ]);
    }

    public function test_user_cannot_follow_themselves(): void
    {
        $user = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/follows', [
            'followed_id' => $user->id,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonPath('message', 'You cannot follow yourself.');

        $this->assertDatabaseMissing('user_follows', [
            'follower_id' => $user->id,
            'followed_id' => $user->id,
        ]);
    }

    public function test_duplicate_follow_is_idempotent(): void
    {
        $follower = User::factory()->create(['role' => 'rider']);
        $followed = User::factory()->create(['role' => 'driver']);

        Sanctum::actingAs($follower);

        $this->postJson('/api/v1/follows', ['followed_id' => $followed->id])
            ->assertStatus(201);

        $this->postJson('/api/v1/follows', ['followed_id' => $followed->id])
            ->assertStatus(201);

        $this->assertSame(1, UserFollow::query()
            ->where('follower_id', $follower->id)
            ->where('followed_id', $followed->id)
            ->count());
    }

    public function test_follow_requires_valid_followed_id(): void
    {
        $follower = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($follower);

        $this->postJson('/api/v1/follows', [
            'followed_id' => 99999,
        ])->assertStatus(422);
    }

    public function test_follow_requires_followed_id(): void
    {
        $follower = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($follower);

        $this->postJson('/api/v1/follows', [])
            ->assertStatus(422);
    }

    public function test_unauthenticated_cannot_follow(): void
    {
        $followed = User::factory()->create(['role' => 'driver']);

        $this->postJson('/api/v1/follows', [
            'followed_id' => $followed->id,
        ])->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated');
    }

    public function test_any_authenticated_role_can_follow(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        $target = User::factory()->create(['role' => 'rider']);

        Sanctum::actingAs($driver);
        $this->postJson('/api/v1/follows', ['followed_id' => $target->id])
            ->assertStatus(201);

        Sanctum::actingAs($manager);
        $this->postJson('/api/v1/follows', ['followed_id' => $target->id])
            ->assertStatus(201);
    }
}
