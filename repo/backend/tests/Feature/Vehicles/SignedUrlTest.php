<?php

namespace Tests\Feature\Vehicles;

use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SignedUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_signed_url_returns_file_content_with_correct_type(): void
    {
        Storage::fake('local');
        [$owner, $media] = $this->seedVehicleMedia();
        Sanctum::actingAs($owner);

        $urlResponse = $this->getJson('/api/v1/media/'.$media->id.'/url')->assertStatus(200);
        $url = (string) $urlResponse->json('url');

        $parts = parse_url($url);
        $pathAndQuery = ($parts['path'] ?? '').'?'.($parts['query'] ?? '');

        Sanctum::actingAs($owner);
        $download = $this->get($pathAndQuery);
        $download->assertStatus(200);
        $this->assertSame('image/jpeg', $download->headers->get('Content-Type'));
    }

    public function test_non_owner_is_denied_even_with_valid_signature(): void
    {
        Storage::fake('local');
        [$owner, $media] = $this->seedVehicleMedia();
        $outsider = User::factory()->create(['role' => 'driver']);

        Sanctum::actingAs($owner);
        $url = (string) $this->getJson('/api/v1/media/'.$media->id.'/url')->json('url');
        $parts = parse_url($url);
        $pathAndQuery = ($parts['path'] ?? '').'?'.($parts['query'] ?? '');

        Sanctum::actingAs($outsider);
        $this->get($pathAndQuery)
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');
    }

    public function test_valid_signature_without_authentication_is_denied(): void
    {
        Storage::fake('local');
        [, $media] = $this->seedVehicleMedia();

        $url = URL::temporarySignedRoute('media.download', now()->addMinutes(10), ['media' => $media->id]);
        $parts = parse_url($url);
        $pathAndQuery = ($parts['path'] ?? '').'?'.($parts['query'] ?? '');

        $this->get($pathAndQuery)->assertStatus(401);
    }

    public function test_expired_signed_url_returns_403(): void
    {
        Storage::fake('local');
        [, $media] = $this->seedVehicleMedia();

        $url = URL::temporarySignedRoute('media.download', now()->subMinute(), ['media' => $media->id]);
        $parts = parse_url($url);

        $this->get(($parts['path'] ?? '').'?'.($parts['query'] ?? ''))
            ->assertStatus(403)
            ->assertJsonPath('error', 'link_expired');
    }

    public function test_tampered_url_returns_403(): void
    {
        Storage::fake('local');
        [, $media] = $this->seedVehicleMedia();

        $url = URL::temporarySignedRoute('media.download', now()->addMinutes(10), ['media' => $media->id]);
        $tampered = str_replace('signature=', 'signature=tampered', $url);
        $parts = parse_url($tampered);

        $this->get(($parts['path'] ?? '').'?'.($parts['query'] ?? ''))
            ->assertStatus(403)
            ->assertJsonPath('error', 'link_expired');
    }

    public function test_forged_referer_does_not_bypass_invalid_signature(): void
    {
        Storage::fake('local');
        [, $media] = $this->seedVehicleMedia();
        $user = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($user);

        $url = URL::temporarySignedRoute('media.download', now()->addMinutes(10), ['media' => $media->id]);
        $tampered = str_replace('signature=', 'signature=tampered', $url);
        $parts = parse_url($tampered);

        $this->withHeader('referer', 'http://localhost:3000/dashboard')
            ->get(($parts['path'] ?? '').'?'.($parts['query'] ?? ''))
            ->assertStatus(403)
            ->assertJsonPath('error', 'link_expired');
    }

    public function test_non_owner_cannot_get_signed_url(): void
    {
        Storage::fake('local');
        [, $media] = $this->seedVehicleMedia();
        $outsider = User::factory()->create(['role' => 'driver']);

        Sanctum::actingAs($outsider);

        $this->getJson('/api/v1/media/'.$media->id.'/url')->assertStatus(403);
    }

    /**
     * @return array{User, MediaAsset}
     */
    private function seedVehicleMedia(): array
    {
        $owner = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['owner_id' => $owner->id]);

        $diskPath = 'media/test-file.jpg';
        Storage::disk('local')->put($diskPath, 'fake-jpeg-content');

        $media = MediaAsset::query()->create([
            'original_filename' => 'file.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 100,
            'sha256_hash' => hash('sha256', 'fake-jpeg-content'),
            'disk_path' => $diskPath,
            'compressed_path' => null,
            'uploaded_by' => $owner->id,
            'created_at' => now(),
        ]);

        VehicleMedia::query()->create([
            'vehicle_id' => $vehicle->id,
            'media_asset_id' => $media->id,
            'sort_order' => 0,
            'is_cover' => true,
            'created_at' => now(),
        ]);

        return [$owner, $media];
    }
}
