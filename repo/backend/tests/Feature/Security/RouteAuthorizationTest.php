<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_post_products_is_forbidden(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $this->postJson('/api/v1/products', [
            'name' => 'nope',
            'category' => 'gear',
            'variants' => [],
        ])->assertStatus(403);
    }

    public function test_driver_get_reports_is_forbidden(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/reports/trends')->assertStatus(403);
    }
}
