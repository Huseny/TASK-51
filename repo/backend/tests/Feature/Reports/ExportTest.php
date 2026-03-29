<?php

namespace Tests\Feature\Reports;

use App\Models\RideOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
        $filename = $path ? basename($path) : '';

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
        $filename = $path ? basename($path) : '';

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

    public function test_driver_cannot_export_reports(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);

        $this->postJson('/api/v1/reports/export', [
            'type' => 'trends',
            'format' => 'csv',
        ])->assertStatus(403);
    }
}
