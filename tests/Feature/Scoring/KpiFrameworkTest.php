<?php

namespace Tests\Feature\Scoring;

use App\Domain\Economics\Resolution\WeekResolutionService;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Scoring\KpiCalculationContext;
use App\Domain\Scoring\KpiCalculationService;
use App\Domain\Scoring\KpiDefinitionCatalog;
use App\Domain\Scoring\KpiSnapshotService;
use App\Domain\Scoring\Week4KpiInputAggregator;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
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

class KpiFrameworkTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_halden_kpi_definitions_load_with_published_weights(): void
    {
        $definitions = app(KpiDefinitionCatalog::class)->publishHaldenV1();

        $this->assertCount(7, $definitions);
        $this->assertSame('0.300000', $definitions->firstWhere('key', 'integrated_margin_per_boe')->weight);
        $this->assertSame('halden_kpi_v1', $definitions->first()->version);
        $this->assertSame(7, KpiDefinition::query()->count());
    }

    public function test_weight_validation_requires_sum_of_one(): void
    {
        $catalog = app(KpiDefinitionCatalog::class);
        $definitions = $catalog->haldenSevenKpis();
        $definitions[0]['weight'] = '0.250000';

        $this->expectException(InvalidArgumentException::class);

        $catalog->assertWeightsSumToOne($definitions);
    }

    public function test_week4_available_kpi_calculates_from_economic_resolution(): void
    {
        $resolution = $this->resolvedWeek4Decision('46.20');
        $definitions = app(KpiDefinitionCatalog::class)->publishHaldenV1();
        $context = app(Week4KpiInputAggregator::class)->fromEconomicResolution($resolution);

        $results = app(KpiCalculationService::class)->calculate($context, $definitions);
        $integrated = collect($results)->first(fn ($result) => $result->definition->key === 'integrated_margin_per_boe');

        $this->assertSame(KpiSnapshotStatus::Available, $integrated->status);
        $this->assertSame('76.750', (string) $integrated->value);
        $this->assertSame('usd_boe', $integrated->unit);
        $this->assertSame($resolution->id, $integrated->inputSnapshot['source_id']);
    }

    public function test_unavailable_kpis_are_recorded_without_fake_values(): void
    {
        $resolution = $this->resolvedWeek4Decision('46.20');
        $definitions = app(KpiDefinitionCatalog::class)->publishHaldenV1();
        $context = app(Week4KpiInputAggregator::class)->fromEconomicResolution($resolution);

        $results = app(KpiCalculationService::class)->calculate($context, $definitions);
        $roace = collect($results)->first(fn ($result) => $result->definition->key === 'roace');
        $refiningBenchmark = collect($results)->first(fn ($result) => $result->definition->key === 'refining_net_margin_vs_benchmark');

        $this->assertSame(KpiSnapshotStatus::Unavailable, $roace->status);
        $this->assertNull($roace->value);
        $this->assertSame('requires capital base state', $roace->unavailableReason);
        $this->assertSame(KpiSnapshotStatus::Unavailable, $refiningBenchmark->status);
        $this->assertSame('requires refining benchmark state', $refiningBenchmark->unavailableReason);
    }

    public function test_kpi_snapshots_are_immutable(): void
    {
        $snapshot = $this->storeWeek4KpiSnapshots('46.20')[0];

        $this->expectException(InvalidArgumentException::class);

        $snapshot->update(['value' => '1.0000']);
    }

    public function test_historical_snapshots_are_preserved_instead_of_overwritten(): void
    {
        $resolution = $this->resolvedWeek4Decision('46.20');
        $definition = app(KpiDefinitionCatalog::class)->publishHaldenV1()->firstWhere('key', 'integrated_margin_per_boe');
        $service = app(KpiSnapshotService::class);

        $firstContext = app(Week4KpiInputAggregator::class)->fromEconomicResolution($resolution);
        $firstResult = app(KpiCalculationService::class)->calculate($firstContext, collect([$definition]));
        $first = $service->storeSnapshots($firstContext, $firstResult)[0];

        $secondContext = new KpiCalculationContext(
            tenantId: $firstContext->tenantId,
            sectionSimulationId: $firstContext->sectionSimulationId,
            sectionSimulationWeekId: $firstContext->sectionSimulationWeekId,
            teamSimulationId: $firstContext->teamSimulationId,
            teamId: $firstContext->teamId,
            sourceType: $firstContext->sourceType,
            sourceId: $firstContext->sourceId,
            availableInputs: ['integrated_margin_per_boe' => '77.000'],
            inputSnapshot: ['manual_verification_fixture' => true],
        );
        $secondResult = app(KpiCalculationService::class)->calculate($secondContext, collect([$definition]));
        $second = $service->storeSnapshots($secondContext, $secondResult)[0];

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, KpiSnapshot::query()->count());
        $this->assertSame('76.7500', $first->refresh()->value);
        $this->assertSame('77.0000', $second->refresh()->value);
    }

    public function test_student_cannot_view_another_team_kpi_snapshot(): void
    {
        $graph = $this->tenantGraph('A');
        [$otherStudent] = $this->addSecondTeam($graph);
        $resolution = $this->resolvedWeek4Decision('46.20', $graph, $otherStudent);
        $snapshot = $this->storeWeek4KpiSnapshotsForResolution($resolution)[0];

        $this->expectException(InvalidArgumentException::class);

        app(KpiSnapshotService::class)->assertCanView($graph['student'], $snapshot);
    }

    /**
     * @return list<KpiSnapshot>
     */
    private function storeWeek4KpiSnapshots(string $transferPrice): array
    {
        return $this->storeWeek4KpiSnapshotsForResolution($this->resolvedWeek4Decision($transferPrice));
    }

    /**
     * @return list<KpiSnapshot>
     */
    private function storeWeek4KpiSnapshotsForResolution(EconomicResolution $resolution): array
    {
        $definitions = app(KpiDefinitionCatalog::class)->publishHaldenV1();
        $context = app(Week4KpiInputAggregator::class)->fromEconomicResolution($resolution);
        $results = app(KpiCalculationService::class)->calculate($context, $definitions);

        return app(KpiSnapshotService::class)->storeSnapshots($context, $results);
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
            'email' => 'student-second@example.test',
        ]);
        $team = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'name' => 'Team second',
            'slug' => 'team-second',
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
