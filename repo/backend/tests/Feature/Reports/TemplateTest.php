<?php

namespace Tests\Feature\Reports;

use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_crud(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        Sanctum::actingAs($manager);

        $create = $this->postJson('/api/v1/reports/templates', [
            'name' => 'Weekly Overview',
            'config' => [
                'grouping' => 'day',
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-07',
            ],
        ])->assertStatus(201);

        $templateId = $create->json('template.id');

        $this->getJson('/api/v1/reports/templates')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $templateId);

        $this->patchJson('/api/v1/reports/templates/'.$templateId, [
            'name' => 'Weekly Snapshot',
        ])->assertStatus(200)
            ->assertJsonPath('template.name', 'Weekly Snapshot');

        $this->deleteJson('/api/v1/reports/templates/'.$templateId)
            ->assertStatus(200);

        $this->assertDatabaseMissing('report_templates', ['id' => $templateId]);
    }

    public function test_user_cannot_modify_another_users_template(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);

        $template = ReportTemplate::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($other);

        $this->patchJson('/api/v1/reports/templates/'.$template->id, ['name' => 'Blocked'])
            ->assertStatus(403);
    }
}
