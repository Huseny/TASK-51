<?php

namespace Tests\Feature\Reports;

use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_fleet_manager_can_create_report_template(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/reports/templates', [
            'name' => 'Weekly Trends',
            'config' => ['grouping' => 'week', 'type' => 'trends'],
        ])->assertStatus(201)
            ->assertJsonPath('template.name', 'Weekly Trends')
            ->assertJsonPath('template.user_id', $manager->id);

        $this->assertDatabaseHas('report_templates', [
            'name' => 'Weekly Trends',
            'user_id' => $manager->id,
        ]);
    }

    public function test_admin_can_create_report_template(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/reports/templates', [
            'name' => 'Monthly Distribution',
            'config' => ['grouping' => 'month'],
        ])->assertStatus(201)
            ->assertJsonPath('template.user_id', $admin->id);
    }

    public function test_template_creation_requires_name(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/reports/templates', [
            'config' => ['grouping' => 'day'],
        ])->assertStatus(422);
    }

    public function test_template_creation_requires_config(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/reports/templates', [
            'name' => 'Missing Config',
        ])->assertStatus(422);
    }

    public function test_user_can_list_own_templates(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        ReportTemplate::factory()->count(3)->create(['user_id' => $manager->id]);

        $other = User::factory()->create(['role' => 'fleet_manager']);
        ReportTemplate::factory()->count(2)->create(['user_id' => $other->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/reports/templates')
            ->assertStatus(200);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_templates_list_does_not_include_other_users(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        $other = User::factory()->create(['role' => 'admin']);

        ReportTemplate::factory()->count(5)->create(['user_id' => $other->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/reports/templates')
            ->assertStatus(200);

        $this->assertEmpty($response->json('data'));
    }

    public function test_user_can_update_own_template(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        $template = ReportTemplate::factory()->create([
            'user_id' => $manager->id,
            'name' => 'Original Name',
        ]);

        Sanctum::actingAs($manager);

        $this->patchJson('/api/v1/reports/templates/'.$template->id, [
            'name' => 'Updated Name',
        ])->assertStatus(200)
            ->assertJsonPath('template.name', 'Updated Name');

        $this->assertDatabaseHas('report_templates', [
            'id' => $template->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_user_can_update_template_config(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        $template = ReportTemplate::factory()->create([
            'user_id' => $manager->id,
            'config_json' => ['grouping' => 'day'],
        ]);

        Sanctum::actingAs($manager);

        $this->patchJson('/api/v1/reports/templates/'.$template->id, [
            'config' => ['grouping' => 'week', 'type' => 'trends'],
        ])->assertStatus(200)
            ->assertJsonPath('template.config_json.grouping', 'week');
    }

    public function test_user_cannot_update_other_users_template(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        $other = User::factory()->create(['role' => 'admin']);
        $template = ReportTemplate::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($manager);

        $this->patchJson('/api/v1/reports/templates/'.$template->id, [
            'name' => 'Hijacked Name',
        ])->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');
    }

    public function test_user_can_delete_own_template(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        $template = ReportTemplate::factory()->create(['user_id' => $manager->id]);

        Sanctum::actingAs($manager);

        $this->deleteJson('/api/v1/reports/templates/'.$template->id)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Template deleted');

        $this->assertDatabaseMissing('report_templates', ['id' => $template->id]);
    }

    public function test_user_cannot_delete_other_users_template(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        $other = User::factory()->create(['role' => 'admin']);
        $template = ReportTemplate::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($manager);

        $this->deleteJson('/api/v1/reports/templates/'.$template->id)
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');

        $this->assertDatabaseHas('report_templates', ['id' => $template->id]);
    }

    public function test_delete_nonexistent_template_returns_404(): void
    {
        $manager = User::factory()->create(['role' => 'fleet_manager']);
        Sanctum::actingAs($manager);

        $this->deleteJson('/api/v1/reports/templates/99999')
            ->assertStatus(404);
    }

    public function test_rider_cannot_access_report_templates(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        Sanctum::actingAs($rider);

        $this->getJson('/api/v1/reports/templates')
            ->assertStatus(403);

        $this->postJson('/api/v1/reports/templates', ['name' => 'x', 'config' => []])
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_templates(): void
    {
        $this->getJson('/api/v1/reports/templates')
            ->assertStatus(401);
    }
}
