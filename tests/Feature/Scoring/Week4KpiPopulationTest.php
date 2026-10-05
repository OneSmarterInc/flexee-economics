<?php

namespace Tests\Feature\Scoring;

use App\Domain\Economics\Resolution\WeekResolutionService;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Scoring\KpiCalculationService;
use App\Domain\Scoring\KpiDefinitionCatalog;
use App\Domain\Scoring\KpiSnapshotService;
use App\Domain\Scoring\Week4KpiPopulationService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\KpiDefinitionStatus;
use App\Enums\KpiSnapshotStatus;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\Enrollment;
use App\Models\KpiDefinition;
use App\Models\KpiSnapshot;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week4KpiPopulationTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_resolved_week4_decision_creates_kpi_snapshots(): void
    {
        $resolution = $this->resolvedWeek4Decision('46.20');

        $snapshots = app(Week4KpiPopulationService::class)->populate($resolution);
        $integrated = collect($snapshots)->first(fn (KpiSnapshot $snapshot) => $snapshot->definition->key === 'integrated_margin_per_boe');

        $this->assertCount(7, $snapshots);
        $this->assertSame($resolution->id, $integrated->economic_resolution_id);
        $this->assertSame(KpiSnapshotStatus::Available, $integrated->statusEnum());
        $this->assertSame('67.1250', $integrated->value);
        $this->assertSame('usd_boe', $integrated->unit);
        $this->assertSame(KpiCalculationService::CALCULATION_VERSION, $integrated->calculation_version);
        $this->assertSame($resolution->id, $integrated->input_snapshot['source_id']);
    }

    public function test_unresolved_submission_creates_no_kpi_snapshots(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openWeek4Context($graph);
        $draft = app(SubmissionService::class)->saveDecisionDraft(
            $graph['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['transfer_price' => '46.20'],
        );

        try {
            app(WeekResolutionService::class)->resolveSubmittedDecision($draft, $graph['student']);
            $this->fail('Draft submissions should not resolve.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, EconomicResolution::query()->count());
            $this->assertSame(0, KpiSnapshot::query()->count());
        }
    }

    public function test_duplicate_resolution_population_does_not_create_duplicate_kpi_snapshots(): void
    {
        $resolution = $this->resolvedWeek4Decision('46.20');
        $service = app(Week4KpiPopulationService::class);

        $first = $service->populate($resolution);
        $second = $service->populate($resolution);

        $this->assertCount(7, $first);
        $this->assertCount(7, $second);
        $this->assertSame(7, KpiSnapshot::query()->count());
        $this->assertSame(
            collect($first)->pluck('id')->all(),
            collect($second)->pluck('id')->all(),
        );
    }

    public function test_later_kpi_definition_version_does_not_change_previous_snapshot(): void
    {
        $resolution = $this->resolvedWeek4Decision('46.20');
        $snapshot = collect(app(Week4KpiPopulationService::class)->populate($resolution))
            ->first(fn (KpiSnapshot $snapshot) => $snapshot->definition->key === 'integrated_margin_per_boe');

        KpiDefinition::query()->create([
            'key' => 'integrated_margin_per_boe',
            'name' => 'Integrated margin per BOE',
            'description' => 'Later version fixture',
            'weight' => '0.250000',
            'calculation_source' => 'economic_resolution.integrated_margin_per_boe',
            'version' => 'halden_kpi_v2',
            'status' => KpiDefinitionStatus::Published->value,
            'effective_from' => now()->addDay()->toDateString(),
            'metadata' => ['fixture' => true],
        ]);

        $this->assertSame(KpiDefinitionCatalog::HALDEN_KPI_VERSION, $snapshot->definition->version);
        $this->assertSame('0.300000', $snapshot->input_snapshot['kpi_definition']['weight']);
        $this->assertSame('67.1250', $snapshot->refresh()->value);
    }

    public function test_student_cannot_view_another_team_population_snapshots(): void
    {
        $graph = $this->tenantGraph('A');
        [$otherStudent] = $this->addSecondTeam($graph);
        $resolution = $this->resolvedWeek4Decision('46.20', $graph, $otherStudent);
        $snapshot = app(Week4KpiPopulationService::class)->populate($resolution)[0];

        $this->expectException(InvalidArgumentException::class);

        app(KpiSnapshotService::class)->assertCanView($graph['student'], $snapshot);
    }

    public function test_week4_population_creates_package_backed_available_kpis(): void
    {
        $resolution = $this->resolvedWeek4Decision('46.20');

        $snapshots = app(Week4KpiPopulationService::class)->populate($resolution);
        $roace = collect($snapshots)->first(fn (KpiSnapshot $snapshot) => $snapshot->definition->key === 'roace');
        $debt = collect($snapshots)->first(fn (KpiSnapshot $snapshot) => $snapshot->definition->key === 'net_debt_to_ebitda');

        $this->assertSame(7, collect($snapshots)->where('status', KpiSnapshotStatus::Available->value)->whereNotNull('value')->count());
        $this->assertSame(KpiSnapshotStatus::Available, $roace->statusEnum());
        $this->assertNotNull($roace->value);
        $this->assertSame(KpiSnapshotStatus::Available, $debt->statusEnum());
        $this->assertNotNull($debt->value);
        $this->assertTrue($roace->input_snapshot['source_snapshot']['package_backed_kpi_state']);
    }

    /**
     * @param  array<string, mixed>|null  $graph
     */
    private function resolvedWeek4Decision(string $transferPrice, ?array $graph = null, ?User $student = null): EconomicResolution
    {
        $graph ??= $this->tenantGraph('A');
        $student ??= $graph['student'];
        $context = $this->openWeek4Context($graph);
        $teamSimulation = TeamSimulation::query()
            ->where('tenant_id', $graph['tenant']->id)
            ->where('section_simulation_id', $context['sectionSimulation']->id)
            ->whereHas('team.members', fn ($query) => $query->whereKey($student->id))
            ->firstOrFail();
        $submission = $this->submitWeek4Decision($student, $context['runtimeWeek'], $teamSimulation, $context['decisionDefinition'], $transferPrice);

        return app(WeekResolutionService::class)->resolveSubmittedDecision($submission, $student);
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{sectionSimulation: SectionSimulation, runtimeWeek: SectionSimulationWeek, decisionDefinition: DecisionFormDefinition, teamSimulation: TeamSimulation}
     */
    private function openWeek4Context(array $graph): array
    {
        $structure = $this->simulationStructure(4);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week4 */
        $week4 = $structure['simulationWeeks']->firstWhere('week_number', 4);
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week4->id)
            ->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week4_transfer_pricing',
            'name' => 'Week 4 transfer pricing',
            'version' => Week4EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week4EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'transfer_price',
            'label' => 'Transfer price',
            'field_type' => DecisionFieldType::Currency,
            'is_required' => true,
            'display_order' => 1,
            'validation' => ['min' => 0, 'max' => 250],
        ]);

        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('sectionSimulation', 'runtimeWeek', 'decisionDefinition', 'teamSimulation');
    }

    private function submitWeek4Decision(User $student, SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation, DecisionFormDefinition $definition, string $transferPrice): DecisionSubmission
    {
        return app(SubmissionService::class)->submitDecision(
            $student,
            $runtimeWeek,
            $teamSimulation,
            $definition,
            ['transfer_price' => $transferPrice],
        );
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{User, Team}
     */
    private function addSecondTeam(array $graph): array
    {
        $student = User::factory()->student()->create([
            'tenant_id' => $graph['tenant']->id,
            'email' => 'student-second-batch5b@example.test',
        ]);
        $team = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'name' => 'Team second batch 5b',
            'slug' => 'team-second-batch-5b',
        ]);

        Enrollment::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'user_id' => $student->id,
            'status' => 'active',
        ]);

        TeamMember::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'team_id' => $team->id,
            'user_id' => $student->id,
        ]);

        return [$student, $team];
    }
}
