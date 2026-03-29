<?php

namespace Tests\Feature\Vehicles;

use App\Jobs\ProcessMediaAsset;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_jpeg_upload_returns_201_and_creates_records(): void
    {
        Storage::fake('local');
        Queue::fake();

        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        Sanctum::actingAs($driver);

        $response = $this->post('/api/v1/vehicles/'.$vehicle->id.'/media', [
            'file' => UploadedFile::fake()->image('car.jpg'),
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['media' => ['id', 'sha256_hash', 'disk_path'], 'url']);

        $this->assertTrue(Storage::disk('local')->exists((string) $response->json('media.disk_path')));
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('vehicle_media', 1);
        Queue::assertPushed(ProcessMediaAsset::class);
    }

    public function test_valid_png_upload_returns_201(): void
    {
        Storage::fake('local');
        Queue::fake();

        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        Sanctum::actingAs($driver);

        $this->post('/api/v1/vehicles/'.$vehicle->id.'/media', [
            'file' => UploadedFile::fake()->image('car.png'),
        ])->assertStatus(201);
    }

    public function test_valid_mp4_upload_returns_201(): void
    {
        Storage::fake('local');
        Queue::fake();

        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        Sanctum::actingAs($driver);

        $this->post('/api/v1/vehicles/'.$vehicle->id.'/media', [
            'file' => UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4'),
        ])->assertStatus(201);
    }

    public function test_oversized_image_returns_422(): void
    {
        Storage::fake('local');
        Queue::fake();

        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        Sanctum::actingAs($driver);

        $this->post('/api/v1/vehicles/'.$vehicle->id.'/media', [
            'file' => UploadedFile::fake()->create('huge.jpg', 9000, 'image/jpeg'),
        ])->assertStatus(422);
    }

    public function test_oversized_video_returns_422(): void
    {
        Storage::fake('local');
        Queue::fake();

        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        Sanctum::actingAs($driver);

        $this->post('/api/v1/vehicles/'.$vehicle->id.'/media', [
            'file' => UploadedFile::fake()->create('huge.mp4', 250000, 'video/mp4'),
        ])->assertStatus(422);
    }

    public function test_invalid_mime_type_returns_422(): void
    {
        Storage::fake('local');
        Queue::fake();

        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        Sanctum::actingAs($driver);

        $this->post('/api/v1/vehicles/'.$vehicle->id.'/media', [
            'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertStatus(422);
    }

    public function test_extension_mismatch_jpg_name_with_png_content_is_accepted(): void
    {
        Storage::fake('local');
        Queue::fake();

        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        Sanctum::actingAs($driver);

        $png = UploadedFile::fake()->image('real.png');
        $content = file_get_contents($png->getRealPath());
        $mismatch = UploadedFile::fake()->createWithContent('mismatch.jpg', $content ?: '');

        $this->post('/api/v1/vehicles/'.$vehicle->id.'/media', [
            'file' => $mismatch,
        ])->assertStatus(201);
    }

    public function test_sha256_deduplication_reuses_single_media_asset_across_vehicles(): void
    {
        Storage::fake('local');
        Queue::fake();

        $driver = User::factory()->create(['role' => 'driver']);
        $vehicleA = Vehicle::factory()->create(['owner_id' => $driver->id]);
        $vehicleB = Vehicle::factory()->create(['owner_id' => $driver->id]);
        Sanctum::actingAs($driver);

        $file = UploadedFile::fake()->image('same.jpg');
        $content = file_get_contents($file->getRealPath());
        $copy = UploadedFile::fake()->createWithContent('same-copy.jpg', $content ?: '');

        $this->post('/api/v1/vehicles/'.$vehicleA->id.'/media', ['file' => $file])->assertStatus(201);
        $this->post('/api/v1/vehicles/'.$vehicleB->id.'/media', ['file' => $copy])->assertStatus(201);

        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('vehicle_media', 2);
    }

    public function test_non_owner_cannot_upload_to_another_users_vehicle(): void
    {
        Storage::fake('local');
        Queue::fake();

        $owner = User::factory()->create(['role' => 'driver']);
        $other = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($other);

        $this->post('/api/v1/vehicles/'.$vehicle->id.'/media', [
            'file' => UploadedFile::fake()->image('nope.jpg'),
        ])->assertStatus(403);
    }
}
