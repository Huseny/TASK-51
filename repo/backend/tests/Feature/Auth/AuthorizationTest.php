<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unauthenticated_request_to_protected_route_returns_401(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated');
    }

    public function test_rider_cannot_access_driver_only_route(): void
    {
        $rider = User::factory()->create([
            'username' => 'rider_guard',
            'role' => 'rider',
        ]);

        $token = $rider->createToken('auth', ['*'], now()->addHours(12))->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/queue')
            ->assertStatus(403)
            ->assertJsonPath('error', 'insufficient_permissions');
    }

    public function test_admin_accessing_restricted_route_succeeds(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin_guard',
            'role' => 'admin',
        ]);

        $token = $admin->createToken('auth', ['*'], now()->addHours(12))->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/panel')
            ->assertStatus(200);
    }

    public function test_fleet_manager_can_access_fleet_ride_workspace(): void
    {
        $fleet = User::factory()->create([
            'username' => 'fleet_guard',
            'role' => 'fleet_manager',
        ]);

        $token = $fleet->createToken('auth', ['*'], now()->addHours(12))->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/fleet/rides/queue')
            ->assertStatus(200);
    }

    public function test_expired_token_returns_401(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-25 10:00:00'));

        $user = User::factory()->create([
            'username' => 'expired_user',
            'role' => 'driver',
        ]);

        $token = $user->createToken('auth', ['*'], now()->addHours(12))->plainTextToken;

        Carbon::setTestNow(Carbon::parse('2026-03-25 23:01:00'));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }
}
