<?php

namespace Tests\Feature\Reports;

use App\Models\RideOrder;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_export_generates_file_and_signed_url(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        RideOrder::factory()->create(['status' => 'completed']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/reports/export', [
            'type' => 'distribution',
            'format' => 'csv',
            'destination' => 'ops',
            'filters' => [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
            ],
        ])->assertStatus(200);

        $url = (string) $response->json('url');
        $this->assertNotEmpty($url);

        $path = parse_url($url, PHP_URL_PATH);
        $exportId = $path ? (int) basename($path) : 0;

        $export = ReportExport::query()->findOrFail($exportId);
        $filename = basename($export->relative_path);

        $this->assertTrue(Storage::disk('local')->exists('exports/ops/'.$filename));

        $requestPath = ($path ?? '').'?'.(parse_url($url, PHP_URL_QUERY) ?? '');
        $this->get($requestPath)->assertStatus(200);
    }

    public function test_xlsx_export_generates_file_and_signed_url(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        RideOrder::factory()->create(['status' => 'completed']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/reports/export', [
            'type' => 'trends',
            'format' => 'xlsx',
            'destination' => 'finance',
            'filters' => [
                'grouping' => 'day',
            ],
        ])->assertStatus(200);

        $url = (string) $response->json('url');
        $path = parse_url($url, PHP_URL_PATH);
        $exportId = $path ? (int) basename($path) : 0;
        $export = ReportExport::query()->findOrFail($exportId);
        $filename = basename($export->relative_path);

        $this->assertStringEndsWith('.xlsx', $filename);
        $this->assertTrue(Storage::disk('local')->exists('exports/finance/'.$filename));
    }

    public function test_invalid_destination_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/reports/export', [
            'type' => 'trends',
            'format' => 'csv',
            'destination' => '../unsafe',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'validation_error');
    }

    public function test_invalid_destination_characters_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/reports/export', [
            'type' => 'trends',
            'format' => 'csv',
            'destination' => 'ops/reports!',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'validation_error');
    }

    public function test_driver_cannot_export_reports(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);

        $this->postJson('/api/v1/reports/export', [
            'type' => 'trends',
            'format' => 'csv',
        ])->assertStatus(403);
    }

    public function test_fleet_manager_can_download_own_export_but_not_others(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create(['role' => 'fleet_manager']);
        $other = User::factory()->create(['role' => 'fleet_manager']);

        Sanctum::actingAs($owner);

        $url = (string) $this->postJson('/api/v1/reports/export', [
            'type' => 'regions',
            'format' => 'csv',
            'destination' => 'ops',
        ])->assertStatus(200)->json('url');

        $parts = parse_url($url);
        $requestPath = ($parts['path'] ?? '').'?'.($parts['query'] ?? '');

        Sanctum::actingAs($owner);
        $this->get($requestPath)->assertStatus(200);

        Sanctum::actingAs($other);
        $this->get($requestPath)->assertStatus(403);
    }

    public function test_rider_and_unauthenticated_are_denied_even_with_valid_signature(): void
    {
        Storage::fake('local');

        $manager = User::factory()->create(['role' => 'fleet_manager']);
        $rider = User::factory()->create(['role' => 'rider']);

        Sanctum::actingAs($manager);
        $url = (string) $this->postJson('/api/v1/reports/export', [
            'type' => 'trends',
            'format' => 'csv',
        ])->assertStatus(200)->json('url');

        $parts = parse_url($url);
        $requestPath = ($parts['path'] ?? '').'?'.($parts['query'] ?? '');

        Sanctum::actingAs($rider);
        $this->get($requestPath)->assertStatus(403);

        $this->app['auth']->guard('sanctum')->forgetUser();
        $this->get($requestPath)->assertStatus(401);
    }

    public function test_tampered_and_expired_signature_are_denied(): void
    {
        Storage::fake('local');

        $manager = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($manager);

        $url = (string) $this->postJson('/api/v1/reports/export', [
            'type' => 'distribution',
            'format' => 'csv',
        ])->assertStatus(200)->json('url');

        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? '';

        $tampered = $path.'?'.str_replace('signature=', 'signature=bad', $query);
        $this->get($tampered)->assertStatus(403);

        $exportId = (int) basename($path);
        $expired = URL::temporarySignedRoute('reports.exports.download', now()->subMinute(), ['reportExport' => $exportId]);
        $expiredParts = parse_url($expired);

        $this->get(($expiredParts['path'] ?? '').'?'.($expiredParts['query'] ?? ''))
            ->assertStatus(403);
    }
}
