<?php

namespace Tests\Feature\Vehicles;

use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleMediaReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reorder_updates_sort_order_correctly(): void
    {
        [$driver, $vehicle, $mediaA, $mediaB] = $this->seedVehicleWithMedia();
        Sanctum::actingAs($driver);

        $this->patchJson('/api/v1/vehicles/'.$vehicle->id.'/media/reorder', [
            'order' => [
                ['media_id' => $mediaB->id, 'sort_order' => 0],
                ['media_id' => $mediaA->id, 'sort_order' => 1],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('vehicle_media', [
            'vehicle_id' => $vehicle->id,
            'media_asset_id' => $mediaB->id,
            'sort_order' => 0,
        ]);
    }

    public function test_cannot_reorder_with_media_from_another_vehicle(): void
    {
        [$driver, $vehicle, $mediaA] = $this->seedVehicleWithMedia();
        $otherVehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        $otherMedia = $this->createMedia($driver->id, 'other.jpg', 'image/jpeg');
        VehicleMedia::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'media_asset_id' => $otherMedia->id,
            'sort_order' => 0,
            'is_cover' => false,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($driver);

        $this->patchJson('/api/v1/vehicles/'.$vehicle->id.'/media/reorder', [
            'order' => [
                ['media_id' => $mediaA->id, 'sort_order' => 0],
                ['media_id' => $otherMedia->id, 'sort_order' => 1],
            ],
        ])->assertStatus(422);
    }

    public function test_set_cover_works_and_only_one_cover_remains(): void
    {
        [$driver, $vehicle, $mediaA, $mediaB] = $this->seedVehicleWithMedia();
        Sanctum::actingAs($driver);

        $this->patchJson('/api/v1/vehicles/'.$vehicle->id.'/media/'.$mediaB->id.'/cover')
            ->assertStatus(200);

        $this->assertDatabaseHas('vehicle_media', [
            'vehicle_id' => $vehicle->id,
            'media_asset_id' => $mediaB->id,
            'is_cover' => true,
        ]);

        $this->assertSame(1, VehicleMedia::query()->where('vehicle_id', $vehicle->id)->where('is_cover', true)->count());
    }

    public function test_cannot_set_video_as_cover(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        $video = $this->createMedia($driver->id, 'clip.mp4', 'video/mp4');

        VehicleMedia::query()->create([
            'vehicle_id' => $vehicle->id,
            'media_asset_id' => $video->id,
            'sort_order' => 0,
            'is_cover' => false,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($driver);

        $this->patchJson('/api/v1/vehicles/'.$vehicle->id.'/media/'.$video->id.'/cover')
            ->assertStatus(422);
    }

    /**
     * @return array{User, Vehicle, MediaAsset, MediaAsset}
     */
    private function seedVehicleWithMedia(): array
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        $mediaA = $this->createMedia($driver->id, 'a.jpg', 'image/jpeg');
        $mediaB = $this->createMedia($driver->id, 'b.jpg', 'image/jpeg');

        VehicleMedia::query()->create([
            'vehicle_id' => $vehicle->id,
            'media_asset_id' => $mediaA->id,
            'sort_order' => 0,
            'is_cover' => true,
            'created_at' => now(),
        ]);

        VehicleMedia::query()->create([
            'vehicle_id' => $vehicle->id,
            'media_asset_id' => $mediaB->id,
            'sort_order' => 1,
            'is_cover' => false,
            'created_at' => now(),
        ]);

        return [$driver, $vehicle, $mediaA, $mediaB];
    }

    private function createMedia(int $userId, string $name, string $mime): MediaAsset
    {
        return MediaAsset::query()->create([
            'original_filename' => $name,
            'mime_type' => $mime,
            'extension' => pathinfo($name, PATHINFO_EXTENSION),
            'size_bytes' => 100,
            'sha256_hash' => hash('sha256', $name.microtime(true)),
            'disk_path' => 'media/'.$name,
            'compressed_path' => null,
            'uploaded_by' => $userId,
            'created_at' => now(),
        ]);
    }
}
