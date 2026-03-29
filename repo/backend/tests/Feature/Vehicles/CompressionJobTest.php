<?php

namespace Tests\Feature\Vehicles;

use App\Jobs\ProcessMediaAsset;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompressionJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_compression_produces_compressed_file(): void
    {
        Storage::fake('local');
        $uploader = User::factory()->create(['role' => 'driver']);

        $image = UploadedFile::fake()->image('large.jpg', 4000, 2500);
        $path = $image->storeAs('media', 'img-original.jpg', 'local');

        $media = MediaAsset::query()->create([
            'original_filename' => 'large.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => (int) $image->getSize(),
            'sha256_hash' => hash_file('sha256', $image->getRealPath()),
            'disk_path' => $path,
            'compressed_path' => null,
            'uploaded_by' => $uploader->id,
            'created_at' => now(),
        ]);

        (new ProcessMediaAsset($media->id))->handle();

        $media->refresh();
        $this->assertNotNull($media->compressed_path);
        $this->assertTrue(Storage::disk('local')->exists($media->compressed_path));
    }

    public function test_compressed_image_is_smaller_than_original(): void
    {
        Storage::fake('local');
        $uploader = User::factory()->create(['role' => 'driver']);

        $image = UploadedFile::fake()->image('large2.jpg', 4200, 2800);
        $path = $image->storeAs('media', 'img2-original.jpg', 'local');

        $media = MediaAsset::query()->create([
            'original_filename' => 'large2.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => (int) $image->getSize(),
            'sha256_hash' => hash_file('sha256', $image->getRealPath()),
            'disk_path' => $path,
            'compressed_path' => null,
            'uploaded_by' => $uploader->id,
            'created_at' => now(),
        ]);

        (new ProcessMediaAsset($media->id))->handle();
        $media->refresh();

        $originalSize = Storage::disk('local')->size($media->disk_path);
        $compressedSize = Storage::disk('local')->size((string) $media->compressed_path);

        $this->assertTrue($compressedSize < $originalSize);
    }

    public function test_video_compression_graceful_when_ffmpeg_missing(): void
    {
        Storage::fake('local');
        Log::spy();
        $uploader = User::factory()->create(['role' => 'driver']);

        Storage::disk('local')->put('media/video-original.mp4', 'not-a-real-video-file');

        $media = MediaAsset::query()->create([
            'original_filename' => 'clip.mp4',
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
            'size_bytes' => 200,
            'sha256_hash' => hash('sha256', 'video-content-'.microtime(true)),
            'disk_path' => 'media/video-original.mp4',
            'compressed_path' => null,
            'uploaded_by' => $uploader->id,
            'created_at' => now(),
        ]);

        (new ProcessMediaAsset($media->id))->handle();

        $this->assertNull($media->fresh()->compressed_path);
        Log::shouldHaveReceived('channel')->with('app')->atLeast()->once();
    }
}
