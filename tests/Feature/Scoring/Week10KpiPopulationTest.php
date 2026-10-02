<?php

namespace Tests\Feature\Scoring;

use App\Domain\Economics\Week10\Week10ConvergenceEconomicEngine;
use App\Domain\Ranking\RankingCalculationService;
use App\Domain\Scoring\KpiSnapshotService;
use App\Domain\Scoring\Week10KpiPopulationService;
use App\Enums\KpiSnapshotStatus;
use App\Enums\RankingSnapshotStatus;
use App\Enums\SubmissionStatus;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\Enrollment;
use App\Models\KpiSnapshot;
use App\Models\RankingSnapshot;
use App\Models\SectionSimulationWeek;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use App\Models\Week10EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week10KpiPopulationTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week10_evaluation_creates_unavailable_kpi_snapshots_with_provenance(): void
    {
        $context = $this->evaluatedWeek10Decision();

        $snapshots = app(Week10KpiPopulationService::class)->populate($context['evaluation']);
        $integrated = collect($snapshots)->firstOrFail(fn (KpiSnapshot $snapshot): bool => $snapshot->definition->key === 'integrated_margin_per_boe');
        $refining = collect($snapshots)->firstOrFail(fn (KpiSnapshot $snapshot): bool => $snapshot->definition->key === 'refining_net_margin_vs_benchmark');
        $roace = collect($snapshots)->firstOrFail(fn (KpiSnapshot $snapshot): bool => $snapshot->definition->key === 'roace');

        $this->assertCount(7, $snapshots);
        $this->assertTrue(collect($snapshots)->every(fn (KpiSnapshot $snapshot): bool => $snapshot->statusEnum() === KpiSnapshotStatus::Unavailable));
        $this->assertTrue(collect($snapshots)->every(fn (KpiSnapshot $snapshot): bool => $snapshot->value === null));
        $this->assertSame(KpiSnapshotStatus::Unavailable, $integrated->statusEnum());
        $this->assertSame('requires integrated_margin_per_boe input', $integrated->unavailable_reason);
        $this->assertSame(KpiSnapshotStatus::Unavailable, $refining->statusEnum());
        $this->assertSame('requires refining benchmark state', $refining->unavailable_reason);
        $this->assertSame(KpiSnapshotStatus::Unavailable, $roace->statusEnum());
        $this->assertSame('requires capital base state', $roace->unavailable_reason);

        $this->assertNull($integrated->economic_resolution_id);
        $this->assertSame($context['evaluation']->id, $integrated->input_snapshot['source_snapshot']['week10_economic_evaluation_id']);
        $this->assertSame(Week10ConvergenceEconomicEngine::ENGINE_VERSION, $integrated->input_snapshot['source_snapshot']['engine_version']);
        $this->assertSame('1.0.0-draft', $integrated->input_snapshot['source_snapshot']['package_version']);
        $this->assertSame(5, $integrated->input_snapshot['source_snapshot']['economic_outputs']['binding_constraint_count']);
        $this->assertSame('Singapore', $integrated->input_snapshot['source_snapshot']['economic_outputs']['hardest_hit_refinery']);
        $this->assertSame('0.45', $integrated->input_snapshot['source_snapshot']['inherited_state_snapshot']['values']['crude_hedge_coverage']);
        $this->assertSame('week5_economic_evaluation', $integrated->input_snapshot['source_snapshot']['inherited_state_snapshot']['dependencies']['crude_hedge_coverage']['source_entity']);
    }

    public function test_week10_kpi_population_is_idempotent(): void
    {
        $context = $this->evaluatedWeek10Decision();
        $service = app(Week10KpiPopulationService::class);

        $first = $service->populate($context['evaluation']);
        $second = $service->populate($context['evaluation']);

        $this->assertCount(7, $first);
        $this->assertCount(7, $second);
        $this->assertSame(7, KpiSnapshot::query()->count());
        $this->assertSame(
            collect($first)->pluck('id')->all(),
            collect($second)->pluck('id')->all(),
        );
    }

    public function test_week10_unresolved_evaluation_does_not_create_kpi_snapshots(): void
    {
        $context = $this->evaluatedWeek10Decision(Week10EconomicEvaluation::STATUS_UNRESOLVED_DEPENDENCY);

        $snapshots = app(Week10KpiPopulationService::class)->populate($context['evaluation']);

        $this->assertSame([], $snapshots);
        $this->assertSame(0, KpiSnapshot::query()->count());
    }

    public function test_week10_kpis_create_incomplete_ranking_without_fake_zeroes(): void
    {
        $context = $this->evaluatedWeek10Decision();

        app(Week10KpiPopulationService::class)->populate($context['evaluation']);
        $rankings = app(RankingCalculationService::class)->calculateForSectionWeek($context['runtimeWeek']);
        $ranking = collect($rankings)->firstOrFail(fn (RankingSnapshot $snapshot): bool => $snapshot->team_simulation_id === $context['teamSimulation']->id);

        $this->assertSame(RankingSnapshotStatus::Incomplete, $ranking->statusEnum());
        $this->assertNull($ranking->composite_score);
        $this->assertNull($ranking->rank);
        $this->assertStringContainsString('integrated_margin_per_boe: requires integrated_margin_per_boe input', (string) $ranking->incomplete_reason);
        $this->assertStringContainsString('refining_net_margin_vs_benchmark: requires refining benchmark state', (string) $ranking->incomplete_reason);
        $this->assertSame('unavailable', $ranking->input_snapshot['kpi_snapshots'][0]['status']);
        $this->assertNull(KpiSnapshot::query()->where('team_simulation_id', $context['teamSimulation']->id)->whereNotNull('value')->first());
    }

    public function test_week10_kpi_and_ranking_visibility_remains_team_isolated(): void
    {
        $context = $this->evaluatedWeek10Decision(createOtherTeam: true);

        $snapshot = app(Week10KpiPopulationService::class)->populate($context['evaluation'])[0];
        $ranking = collect(app(RankingCalculationService::class)->calculateForSectionWeek($context['runtimeWeek']))
            ->firstOrFail(fn (RankingSnapshot $candidate): bool => $candidate->team_simulation_id === $context['teamSimulation']->id);

        app(KpiSnapshotService::class)->assertCanView($context['graph']['student'], $snapshot);
        app(RankingCalculationService::class)->assertCanView($context['graph']['student'], $ranking);

        try {
            app(KpiSnapshotService::class)->assertCanView($context['otherStudent'], $snapshot);
            $this->fail('Other-team student should not access this KPI snapshot.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Actor cannot access this team KPI snapshot.', $exception->getMessage());
        }

        try {
            app(RankingCalculationService::class)->assertCanView($context['otherStudent'], $ranking);
            $this->fail('Other-team student should not access this ranking snapshot.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Actor cannot access this team ranking snapshot.', $exception->getMessage());
        }
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     runtimeWeek: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation,
     *     evaluation: Week10EconomicEvaluation,
     *     otherStudent?: User
     * }
     */
    private function evaluatedWeek10Decision(string $status = Week10EconomicEvaluation::STATUS_CALCULATED, bool $createOtherTeam = false): array
    {
        $graph = $this->tenantGraph(uniqid('W10Kpi'));
        $otherStudent = null;

        if ($createOtherTeam) {
            $otherStudent = User::factory()->student()->create([
                'tenant_id' => $graph['tenant']->id,
                'email' => 'other-week10-kpi-'.uniqid().'@example.test',
            ]);
            $otherTeam = Team::factory()->create([
                'tenant_id' => $graph['tenant']->id,
                'section_id' => $graph['section']->id,
                'name' => 'Other Week 10 KPI Team',
                'slug' => 'other-week10-kpi-team-'.uniqid(),
            ]);
            Enrollment::query()->create([
                'tenant_id' => $graph['tenant']->id,
                'section_id' => $graph['section']->id,
                'user_id' => $otherStudent->id,
                'status' => 'active',
            ]);
            TeamMember::query()->create([
                'tenant_id' => $graph['tenant']->id,
                'team_id' => $otherTeam->id,
                'user_id' => $otherStudent->id,
            ]);
        }

        $structure = $this->simulationStructure(10);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        $weekDefinition = $structure['simulationWeeks']->firstWhere('week_number', 10);
        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $weekDefinition->id)
            ->firstOrFail();
        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week10_convergence_plan',
            'name' => 'Week 10 convergence plan',
            'version' => Week10ConvergenceEconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week10ConvergenceEconomicEngine::ENGINE_IDENTIFIER],
        ]);

        $submission = DecisionSubmission::query()->create([
            'tenant_id' => $runtimeWeek->tenant_id,
            'section_simulation_id' => $runtimeWeek->section_simulation_id,
            'section_simulation_week_id' => $runtimeWeek->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_form_definition_id' => $definition->id,
            'status' => SubmissionStatus::Submitted->value,
            'answers' => ['operating_posture' => 'liquidity_first'],
            'lock_version' => 1,
            'updated_by_user_id' => $graph['student']->id,
            'submitted_by_user_id' => $graph['student']->id,
            'draft_saved_at' => now(),
            'submitted_at' => now(),
        ]);

        $evaluation = Week10EconomicEvaluation::query()->create([
            'tenant_id' => $runtimeWeek->tenant_id,
            'section_simulation_id' => $runtimeWeek->section_simulation_id,
            'section_simulation_week_id' => $runtimeWeek->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week10ConvergenceEconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week10ConvergenceEconomicEngine::ENGINE_VERSION,
            'package_version' => '1.0.0-draft',
            'status' => $status,
            'gasoline_demand_hit' => '-0.010500',
            'diesel_demand_hit' => '-0.025500',
            'jet_demand_hit' => '-0.048000',
            'blended_demand_hit' => '-0.023700',
            'baton_rouge_demand_hit' => '-0.019920',
            'rotterdam_demand_hit' => '-0.022260',
            'singapore_demand_hit' => '-0.026220',
            'hardest_hit_refinery' => 'Singapore',
            'binding_constraint_count' => $status === Week10EconomicEvaluation::STATUS_CALCULATED ? 5 : null,
            'unresolved_dependencies' => $status === Week10EconomicEvaluation::STATUS_CALCULATED ? [] : ['cash_cushion_musd'],
            'inherited_state_snapshot' => [
                'values' => [
                    'cancellable_capex_musd' => '120.0',
                    'crude_hedge_coverage' => '0.45',
                    'br_reported_margin_strong' => true,
                    'straits_pacific_standing' => 'strained',
                    'cash_cushion_musd' => $status === Week10EconomicEvaluation::STATUS_CALCULATED ? '85.0' : null,
                ],
                'dependencies' => [
                    'crude_hedge_coverage' => [
                        'status' => 'available',
                        'source_week' => '5',
                        'source_entity' => 'week5_economic_evaluation',
                        'source_version' => 'week5_currency_exposure_v1',
                    ],
                ],
            ],
            'input_snapshot' => [
                'decision_submission' => [
                    'id' => $submission->id,
                    'answers' => ['operating_posture' => 'liquidity_first'],
                ],
            ],
            'output_snapshot' => [
                'status' => $status,
                'demand_hits' => [
                    'blended' => '-0.023700',
                ],
                'refinery_hits' => [
                    'Singapore' => '-0.026220',
                ],
                'binding_constraint_count' => $status === Week10EconomicEvaluation::STATUS_CALCULATED ? 5 : null,
            ],
            'unavailable_reason' => $status === Week10EconomicEvaluation::STATUS_CALCULATED ? null : 'Week 10 inherited state is incomplete.',
            'evaluated_by_user_id' => $graph['faculty']->id,
            'evaluated_by_process' => 'test_fixture',
            'evaluated_at' => now(),
        ]);

        $context = compact('graph', 'runtimeWeek', 'teamSimulation', 'evaluation');

        if ($otherStudent instanceof User) {
            $context['otherStudent'] = $otherStudent;
        }

        return $context;
    }
}
