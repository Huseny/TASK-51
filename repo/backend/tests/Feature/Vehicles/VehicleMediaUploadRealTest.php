<?php

namespace Tests\Feature\Vehicles;

use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Real-path upload tests — no Queue::fake().
 *
 * QUEUE_CONNECTION=sync in phpunit.xml means ProcessMediaAsset dispatches
 * and runs synchronously within the test process.  These tests verify that
 * the full pipeline (upload → store → compress → update DB) works end-to-end
 * without mocking the queue layer.
 */
class VehicleMediaUploadRealTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_dispatches_compression_job_and_creates_db_records(): void
    {
        Storage::fake('local');
        // No Queue::fake() — job runs synchronously

        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        Sanctum::actingAs($driver);

        $response = $this->post('/api/v1/vehicles/'.$vehicle->id.'/media', [
            'file' => UploadedFile::fake()->image('car.jpg', 1920, 1080),
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'media' => ['id', 'sha256_hash', 'disk_path'],
                'url',
            ]);

        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('vehicle_media', 1);

        // Verify the media_asset record created by the upload
        $mediaId = $response->json('media.id');
        $media = MediaAsset::findOrFail($mediaId);
        $this->assertNotNull($media->disk_path);
        $this->assertTrue(Storage::disk('local')->exists($media->disk_path));

        // Since job ran synchronously (no Queue::fake), compressed_path should be set
        // for an image that GD can process
        $this->assertNotNull($media->compressed_path, 'ProcessMediaAsset should have set compressed_path for a valid JPEG');
        $this->assertStringEndsWith('_compressed.jpg', $media->compressed_path);
    }

    public function test_second_upload_of_identical_file_reuses_media_asset(): void
    {
        Storage::fake('local');
        // No Queue::fake()

        $driver = User::factory()->create(['role' => 'driver']);
        $vehicleA = Vehicle::factory()->create(['owner_id' => $driver->id]);
        $vehicleB = Vehicle::factory()->create(['owner_id' => $driver->id]);
        Sanctum::actingAs($driver);

        $file = UploadedFile::fake()->image('same.jpg', 800, 600);
        $content = file_get_contents($file->getRealPath());
        $copy = UploadedFile::fake()->createWithContent('same-copy.jpg', $content ?: '');

        $this->post('/api/v1/vehicles/'.$vehicleA->id.'/media', ['file' => $file])
            ->assertStatus(201)
            ->assertJsonPath('deduplicated', false);

        $this->post('/api/v1/vehicles/'.$vehicleB->id.'/media', ['file' => $copy])
            ->assertStatus(201)
            ->assertJsonPath('deduplicated', true);

        // SHA256 deduplication: one media_asset used by two vehicles
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('vehicle_media', 2);
    }

    public function test_upload_response_contains_signed_url(): void
    {
        Storage::fake('local');

        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        Sanctum::actingAs($driver);

        $response = $this->post('/api/v1/vehicles/'.$vehicle->id.'/media', [
            'file' => UploadedFile::fake()->image('photo.jpg', 400, 300),
        ])->assertStatus(201);

        $url = $response->json('url');
        $this->assertNotEmpty($url);
        $this->assertStringContainsString('media', $url);
        $this->assertStringContainsString('signature', $url);
    }

    public function test_video_upload_creates_record_without_image_compression(): void
    {
        Storage::fake('local');

        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $driver->id]);
        Sanctum::actingAs($driver);

        $response = $this->post('/api/v1/vehicles/'.$vehicle->id.'/media', [
            'file' => UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4'),
        ])->assertStatus(201);

        $this->assertDatabaseCount('media_assets', 1);

        $mediaId = $response->json('media.id');
        $media = MediaAsset::findOrFail($mediaId);
        $this->assertSame('video/mp4', $media->mime_type);

        // Videos are not compressed via GD — compressed_path stays null
        $this->assertNull($media->compressed_path);
    }
}
