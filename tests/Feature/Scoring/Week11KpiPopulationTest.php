<?php

namespace Tests\Feature\Scoring;

use App\Domain\Economics\Week11\Week11EconomicEngine;
use App\Domain\Ranking\RankingCalculationService;
use App\Domain\Scoring\KpiSnapshotService;
use App\Domain\Scoring\Week11KpiPopulationService;
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
use App\Models\Week11EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week11KpiPopulationTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week11_evaluation_creates_unavailable_kpi_snapshots_with_provenance(): void
    {
        $context = $this->evaluatedWeek11Decision();

        $snapshots = app(Week11KpiPopulationService::class)->populate($context['evaluation']);
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
        $this->assertSame($context['evaluation']->id, $integrated->input_snapshot['source_snapshot']['week11_economic_evaluation_id']);
        $this->assertSame($context['submission']->id, $integrated->input_snapshot['source_snapshot']['decision_submission_id']);
        $this->assertSame(Week11EconomicEngine::ENGINE_VERSION, $integrated->input_snapshot['source_snapshot']['engine_version']);
        $this->assertSame('1.0.0-draft', $integrated->input_snapshot['source_snapshot']['package_version']);
        $this->assertSame('67.000000', $integrated->input_snapshot['source_snapshot']['economic_outputs']['profit_oil']);
        $this->assertSame('3089.400975', $integrated->input_snapshot['source_snapshot']['economic_outputs']['pv_stay_demanded_musd']);
        $this->assertSame('0.984851', $integrated->input_snapshot['source_snapshot']['economic_outputs']['indifference_take']);
        $this->assertSame(['kessana_position' => 'accept_demanded_take'], $integrated->input_snapshot['source_snapshot']['input_snapshot']['decision_submission']['answers']);
    }

    public function test_week11_kpi_population_is_idempotent(): void
    {
        $context = $this->evaluatedWeek11Decision();
        $service = app(Week11KpiPopulationService::class);

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

    public function test_week11_unavailable_evaluation_does_not_create_kpi_snapshots(): void
    {
        $context = $this->evaluatedWeek11Decision(Week11EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE);

        $snapshots = app(Week11KpiPopulationService::class)->populate($context['evaluation']);

        $this->assertSame([], $snapshots);
        $this->assertSame(0, KpiSnapshot::query()->count());
    }

    public function test_week11_kpis_create_incomplete_ranking_without_fake_zeroes(): void
    {
        $context = $this->evaluatedWeek11Decision();

        app(Week11KpiPopulationService::class)->populate($context['evaluation']);
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

    public function test_week11_kpi_and_ranking_visibility_remains_team_isolated(): void
    {
        $context = $this->evaluatedWeek11Decision(createOtherTeam: true);

        $snapshot = app(Week11KpiPopulationService::class)->populate($context['evaluation'])[0];
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
     *     submission: DecisionSubmission,
     *     evaluation: Week11EconomicEvaluation,
     *     otherStudent?: User
     * }
     */
    private function evaluatedWeek11Decision(string $status = Week11EconomicEvaluation::STATUS_CALCULATED, bool $createOtherTeam = false): array
    {
        $graph = $this->tenantGraph(uniqid('W11Kpi'));
        $otherStudent = null;

        if ($createOtherTeam) {
            $otherStudent = User::factory()->student()->create([
                'tenant_id' => $graph['tenant']->id,
                'email' => 'other-week11-kpi-'.uniqid().'@example.test',
            ]);
            $otherTeam = Team::factory()->create([
                'tenant_id' => $graph['tenant']->id,
                'section_id' => $graph['section']->id,
                'name' => 'Other Week 11 KPI Team',
                'slug' => 'other-week11-kpi-team-'.uniqid(),
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

        $structure = $this->simulationStructure(11);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        $weekDefinition = $structure['simulationWeeks']->firstWhere('week_number', 11);
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
            'key' => 'week11_kessana_position',
            'name' => 'Week 11 Kessana position',
            'version' => Week11EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week11EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        $submission = DecisionSubmission::query()->create([
            'tenant_id' => $runtimeWeek->tenant_id,
            'section_simulation_id' => $runtimeWeek->section_simulation_id,
            'section_simulation_week_id' => $runtimeWeek->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_form_definition_id' => $definition->id,
            'status' => SubmissionStatus::Submitted->value,
            'answers' => ['kessana_position' => 'accept_demanded_take'],
            'lock_version' => 1,
            'updated_by_user_id' => $graph['student']->id,
            'submitted_by_user_id' => $graph['student']->id,
            'draft_saved_at' => now(),
            'submitted_at' => now(),
        ]);

        $evaluation = Week11EconomicEvaluation::query()->create([
            'tenant_id' => $runtimeWeek->tenant_id,
            'section_simulation_id' => $runtimeWeek->section_simulation_id,
            'section_simulation_week_id' => $runtimeWeek->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week11EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week11EconomicEngine::ENGINE_VERSION,
            'package_version' => '1.0.0-draft',
            'status' => $status,
            'realized_price' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '75.000000' : null,
            'profit_oil' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '67.000000' : null,
            'annual_mbbl' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '34.000000' : null,
            'annuity_factor' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '5.216116' : null,
            'margin_current' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '25.460000' : null,
            'pv_stay_current_musd' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '4515.278348' : null,
            'margin_mid' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '21.440000' : null,
            'pv_stay_mid_musd' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '3802.339662' : null,
            'margin_demanded' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '17.420000' : null,
            'pv_stay_demanded_musd' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '3089.400975' : null,
            'margin_harsh' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '13.400000' : null,
            'pv_stay_harsh_musd' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '2376.462288' : null,
            'exit_value_musd' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '180.000000' : null,
            'stay_minus_exit_demanded_musd' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '2909.400975' : null,
            'indifference_take' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '0.984851' : null,
            'comparables_min' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '0.500000' : null,
            'comparables_max' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '0.850000' : null,
            'demanded_take' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '0.740000' : null,
            'demanded_take_inside_comparables' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? true : null,
            'staying_beats_exit_across_take_grid' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? true : null,
            'stay_value_falls_as_take_rises' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? true : null,
            'sunk_invariant' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? true : null,
            'take_results' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? [
                'current' => ['scenario' => 'current', 'pv_stay_musd' => '4515.278348'],
                'demanded' => ['scenario' => 'demanded', 'pv_stay_musd' => '3089.400975'],
            ] : null,
            'worked_example_snapshot' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? [
                'pv_after' => '372.572983',
            ] : null,
            'input_snapshot' => [
                'decision_submission' => [
                    'id' => $submission->id,
                    'answers' => ['kessana_position' => 'accept_demanded_take'],
                ],
            ],
            'output_snapshot' => [
                'status' => $status,
                'take_results' => [
                    'demanded' => [
                        'pv_stay_musd' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? '3089.400975' : null,
                    ],
                ],
            ],
            'unavailable_reason' => $status === Week11EconomicEvaluation::STATUS_CALCULATED ? null : 'Authoritative Week 11 reference package is not available.',
            'evaluated_by_user_id' => $graph['faculty']->id,
            'evaluated_by_process' => 'test_fixture',
            'evaluated_at' => now(),
        ]);

        $context = compact('graph', 'runtimeWeek', 'teamSimulation', 'submission', 'evaluation');

        if ($otherStudent instanceof User) {
            $context['otherStudent'] = $otherStudent;
        }

        return $context;
    }
}
