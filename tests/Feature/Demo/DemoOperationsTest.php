<?php

namespace Tests\Feature\Demo;

use App\Domain\Demo\DemoHealthCheckService;
use App\Enums\SubmissionStatus;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\KpiDefinition;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationContentPackage;
use App\Models\TeamSimulation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_health_passes_after_database_seed(): void
    {
        $this->seed();

        $this->artisan('halden:demo-health')
            ->expectsOutputToContain('week4_content_activation: ok')
            ->assertExitCode(0);

        $this->assertSame(7, KpiDefinition::query()->count());
        $this->assertGreaterThan(0, SimulationContentPackage::query()
            ->where('package_type', 'authoritative_week2_reference_package')
            ->where('status', SimulationContentPackage::STATUS_VALIDATED)
            ->count());
    }

    public function test_demo_reset_rebuilds_week4_baseline_and_clears_runtime_submissions(): void
    {
        $this->seed();

        $tenant = Tenant::query()
            ->where('slug', DemoHealthCheckService::DEMO_TENANT_SLUG)
            ->firstOrFail();
        $runtimeWeek = SectionSimulationWeek::query()
            ->where('tenant_id', $tenant->id)
            ->whereHas('definition', fn ($query) => $query->where('week_number', 4))
            ->firstOrFail();
        $teamSimulation = TeamSimulation::query()
            ->where('tenant_id', $tenant->id)
            ->where('section_simulation_id', $runtimeWeek->section_simulation_id)
            ->firstOrFail();
        $decisionDefinition = $runtimeWeek->definition->decisionFormDefinitions()->firstOrFail();

        DecisionSubmission::query()->create([
            'tenant_id' => $tenant->id,
            'section_simulation_id' => $runtimeWeek->section_simulation_id,
            'section_simulation_week_id' => $runtimeWeek->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_form_definition_id' => $decisionDefinition->id,
            'status' => SubmissionStatus::Draft->value,
            'answers' => ['transfer_price' => '46.20'],
            'lock_version' => 1,
        ]);

        $this->assertSame(1, DecisionSubmission::query()->where('tenant_id', $tenant->id)->count());

        $this->artisan('halden:demo-reset')
            ->expectsOutputToContain('Halden demo reset complete.')
            ->assertExitCode(0);

        $rebuiltTenant = Tenant::query()
            ->where('slug', DemoHealthCheckService::DEMO_TENANT_SLUG)
            ->firstOrFail();

        $this->assertSame(0, DecisionSubmission::query()->where('tenant_id', $rebuiltTenant->id)->count());
        $this->assertSame(0, EconomicResolution::query()->where('tenant_id', $rebuiltTenant->id)->count());
        $this->assertSame(7, KpiDefinition::query()->count());
        $this->assertGreaterThan(0, SimulationContentPackage::query()
            ->where('package_type', 'reference_package')
            ->where('version', 'week4_reference_package_v1')
            ->count());
        $this->assertGreaterThan(0, SimulationContentPackage::query()
            ->where('package_type', 'authoritative_week2_reference_package')
            ->where('status', SimulationContentPackage::STATUS_VALIDATED)
            ->count());

        $this->assertTrue(app(DemoHealthCheckService::class)->healthy());
    }
}
