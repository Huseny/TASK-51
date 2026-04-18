<?php

namespace Tests\Feature\Reports;

use App\Models\RideOrder;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests the export flow against the real local filesystem.
 * Avoids Storage::fake to exercise actual file I/O and validate content.
 */
class ReportExportRealisticTest extends TestCase
{
    use RefreshDatabase;

    private string $testDir = 'exports/test_realistic';

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory($this->testDir);
        parent::tearDown();
    }

    public function test_csv_export_writes_real_file_with_correct_content(): void
    {
        config()->set('reports.export_roots.test_realistic', [
            'label' => 'Test Realistic',
            'relative_path' => $this->testDir,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        RideOrder::factory()->count(3)->create([
            'status' => 'completed',
            'created_at' => now()->toDateString().' 10:00:00',
        ]);
        RideOrder::factory()->count(2)->create([
            'status' => 'cancelled',
            'created_at' => now()->toDateString().' 11:00:00',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/reports/export', [
            'type' => 'distribution',
            'format' => 'csv',
            'directory_id' => 'test_realistic',
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

        $this->assertTrue(
            Storage::disk('local')->exists($export->relative_path),
            'Export file must exist on the real filesystem'
        );

        $content = Storage::disk('local')->get($export->relative_path);
        $this->assertNotNull($content);
        $this->assertStringContainsString('label,value', (string) $content);
        $this->assertStringContainsString('completed', (string) $content);
    }

    public function test_xlsx_export_writes_real_file_with_valid_zip_structure(): void
    {
        config()->set('reports.export_roots.test_realistic', [
            'label' => 'Test Realistic',
            'relative_path' => $this->testDir,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        RideOrder::factory()->create(['status' => 'completed', 'created_at' => '2026-03-01 10:00:00']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/reports/export', [
            'type' => 'trends',
            'format' => 'xlsx',
            'directory_id' => 'test_realistic',
            'filters' => ['grouping' => 'day'],
        ])->assertStatus(200);

        $url = (string) $response->json('url');
        $path = parse_url($url, PHP_URL_PATH);
        $exportId = $path ? (int) basename($path) : 0;
        $export = ReportExport::query()->findOrFail($exportId);

        $absolutePath = Storage::disk('local')->path($export->relative_path);
        $this->assertTrue(file_exists($absolutePath), 'XLSX file must exist on real filesystem');

        $zip = new \ZipArchive;
        $opened = $zip->open($absolutePath);
        $this->assertTrue($opened === true, 'XLSX file must be a valid ZIP archive');
        $this->assertNotFalse($zip->statName('xl/worksheets/sheet1.xml'), 'XLSX must contain worksheet');
        $zip->close();
    }

    public function test_csv_file_content_matches_regions_data(): void
    {
        config()->set('reports.export_roots.test_realistic', [
            'label' => 'Test Realistic',
            'relative_path' => $this->testDir,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        RideOrder::factory()->create([
            'origin_address' => '1 Main St, City',
            'created_at' => now()->toDateString().' 10:00:00',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/reports/export', [
            'type' => 'regions',
            'format' => 'csv',
            'directory_id' => 'test_realistic',
            'filters' => [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
            ],
        ])->assertStatus(200);

        $url = (string) $response->json('url');
        $path = parse_url($url, PHP_URL_PATH);
        $exportId = $path ? (int) basename($path) : 0;
        $export = ReportExport::query()->findOrFail($exportId);

        $content = (string) Storage::disk('local')->get($export->relative_path);
        $lines = array_filter(explode("\n", trim($content)));
        $this->assertGreaterThanOrEqual(2, count($lines), 'CSV should have header + at least one data row');

        $headerLine = reset($lines);
        $this->assertStringContainsString('region', $headerLine);
        $this->assertStringContainsString('total', $headerLine);
    }
}
