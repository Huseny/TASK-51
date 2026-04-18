<?php

namespace Tests\Feature\Health;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_reports_ready_when_schema_is_current(): void
    {
        // After RefreshDatabase all migrations have run, so the column exists.
        $this->assertTrue(
            Schema::hasColumn('notification_frequency_logs', 'type'),
            'Migration must have added the type column before this test runs'
        );

        $this->getJson('/api/v1/readiness')
            ->assertStatus(200)
            ->assertJsonPath('checks.notification_frequency_type_column', true)
            ->assertJsonPath('status', 'ready');
    }

    public function test_readiness_reports_degraded_when_required_column_is_missing(): void
    {
        Schema::table('notification_frequency_logs', function (Blueprint $table): void {
            $table->dropColumn('type');
        });

        $this->assertFalse(
            Schema::hasColumn('notification_frequency_logs', 'type'),
            'Column should be absent after drop'
        );

        $this->getJson('/api/v1/readiness')
            ->assertStatus(503)
            ->assertJsonPath('checks.notification_frequency_type_column', false)
            ->assertJsonPath('status', 'degraded');
    }

    public function test_readiness_includes_required_migration_in_response(): void
    {
        $this->getJson('/api/v1/readiness')
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'checks',
                'required_migrations',
                'pending_required_migrations',
                'message',
            ]);
    }

    public function test_readiness_endpoint_is_public(): void
    {
        $this->getJson('/api/v1/readiness')
            ->assertStatus(200);
    }
}
