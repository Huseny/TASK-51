<?php

namespace Tests\Feature\Vehicles;

use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleMediaRemoveTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a MediaAsset and attach it to a vehicle, returning the media_asset id.
     * Uses fake storage and queue to avoid real I/O during test setup.
     */
    private function attachMediaToVehicle(User $owner, Vehicle $vehicle): int
    {
        Storage::fake('local');
        Queue::fake();

        $mediaAsset = MediaAsset::create([
            'original_filename' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 1024,
            'sha256_hash' => hash('sha256', 'test-'.uniqid()),
            'disk_path' => 'uploads/vehicles/test-'.uniqid().'.jpg',
            'uploaded_by' => $owner->id,
            'created_at' => now(),
        ]);

        VehicleMedia::create([
            'vehicle_id' => $vehicle->id,
            'media_asset_id' => $mediaAsset->id,
            'sort_order' => 0,
            'is_cover' => false,
            'created_at' => now(),
        ]);

        return $mediaAsset->id;
    }

    // ── DELETE /api/v1/vehicles/{vehicle}/media/{mediaId} ─────────────────────

    public function test_owner_can_remove_media_from_gallery(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        $mediaId = $this->attachMediaToVehicle($driver, $vehicle);

        Sanctum::actingAs($driver);

        $this->assertDatabaseCount('vehicle_media', 1);

        $this->deleteJson('/api/v1/vehicles/'.$vehicle->id.'/media/'.$mediaId)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Media removed from gallery');

        $this->assertDatabaseCount('vehicle_media', 0);
    }

    public function test_removing_media_does_not_delete_the_media_asset_record(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        $mediaId = $this->attachMediaToVehicle($driver, $vehicle);

        Sanctum::actingAs($driver);

        $this->deleteJson('/api/v1/vehicles/'.$vehicle->id.'/media/'.$mediaId)
            ->assertStatus(200);

        // vehicle_media pivot row is gone but the media_asset itself remains
        $this->assertDatabaseCount('vehicle_media', 0);
        $this->assertDatabaseCount('media_assets', 1);
    }

    public function test_non_owner_cannot_remove_media(): void
    {
        $owner = User::factory()->create(['role' => 'driver']);
        $other = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $owner->id]);
        $mediaId = $this->attachMediaToVehicle($owner, $vehicle);

        Sanctum::actingAs($other);

        $this->deleteJson('/api/v1/vehicles/'.$vehicle->id.'/media/'.$mediaId)
            ->assertStatus(403);

        $this->assertDatabaseCount('vehicle_media', 1);
    }

    public function test_removing_nonexistent_media_id_returns_200_silently(): void
    {
        // Controller deletes silently — no error for a non-attached mediaId
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);

        Sanctum::actingAs($driver);

        $this->deleteJson('/api/v1/vehicles/'.$vehicle->id.'/media/99999')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Media removed from gallery');
    }

    public function test_admin_can_remove_media_from_any_vehicle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        $mediaId = $this->attachMediaToVehicle($driver, $vehicle);

        Sanctum::actingAs($admin);

        $this->deleteJson('/api/v1/vehicles/'.$vehicle->id.'/media/'.$mediaId)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Media removed from gallery');

        $this->assertDatabaseCount('vehicle_media', 0);
    }

    public function test_remove_only_unlinks_from_target_vehicle_not_other_vehicles(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicleA = Vehicle::factory()->create(['owner_id' => $driver->id]);
        $vehicleB = Vehicle::factory()->create(['owner_id' => $driver->id]);

        Storage::fake('local');
        Queue::fake();

        $sharedAsset = MediaAsset::create([
            'original_filename' => 'shared.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 1024,
            'sha256_hash' => hash('sha256', 'shared-test-'.uniqid()),
            'disk_path' => 'uploads/shared-'.uniqid().'.jpg',
            'uploaded_by' => $driver->id,
            'created_at' => now(),
        ]);

        VehicleMedia::create(['vehicle_id' => $vehicleA->id, 'media_asset_id' => $sharedAsset->id, 'sort_order' => 0, 'is_cover' => false, 'created_at' => now()]);
        VehicleMedia::create(['vehicle_id' => $vehicleB->id, 'media_asset_id' => $sharedAsset->id, 'sort_order' => 0, 'is_cover' => false, 'created_at' => now()]);

        Sanctum::actingAs($driver);

        $this->deleteJson('/api/v1/vehicles/'.$vehicleA->id.'/media/'.$sharedAsset->id)
            ->assertStatus(200);

        // vehicleA no longer linked; vehicleB still linked
        $this->assertDatabaseMissing('vehicle_media', ['vehicle_id' => $vehicleA->id, 'media_asset_id' => $sharedAsset->id]);
        $this->assertDatabaseHas('vehicle_media', ['vehicle_id' => $vehicleB->id, 'media_asset_id' => $sharedAsset->id]);
    }

    public function test_unauthenticated_cannot_remove_media(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        $mediaId = $this->attachMediaToVehicle($driver, $vehicle);

        $this->deleteJson('/api/v1/vehicles/'.$vehicle->id.'/media/'.$mediaId)
            ->assertStatus(401);
    }
}
