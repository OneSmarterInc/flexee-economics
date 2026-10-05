<?php

namespace Tests\Feature\Ranking;

use App\Domain\Ranking\RankingCalculationService;
use App\Domain\Scoring\KpiDefinitionCatalog;
use App\Enums\KpiDefinitionStatus;
use App\Enums\KpiSnapshotStatus;
use App\Enums\RankingScope;
use App\Enums\RankingSnapshotStatus;
use App\Models\Enrollment;
use App\Models\KpiDefinition;
use App\Models\KpiSnapshot;
use App\Models\RankingSnapshot;
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

class RankingCalculationTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_ranking_calculates_from_complete_kpi_snapshots(): void
    {
        $context = $this->rankingContext();
        $definitions = app(KpiDefinitionCatalog::class)->publishHaldenV1();
        $this->storeAvailableKpis($context['runtimeWeek'], $context['teamSimulation'], $definitions, '100.0000');
        $this->storeAvailableKpis($context['runtimeWeek'], $context['otherTeamSimulation'], $definitions, '80.0000');

        $snapshots = app(RankingCalculationService::class)->calculateForSectionWeek($context['runtimeWeek']);
        $first = collect($snapshots)->firstWhere('team_simulation_id', $context['teamSimulation']->id);
        $second = collect($snapshots)->firstWhere('team_simulation_id', $context['otherTeamSimulation']->id);

        $this->assertSame(RankingSnapshotStatus::Complete, $first->statusEnum());
        $this->assertSame('90.000000', $first->composite_score);
        $this->assertSame(1, $first->rank);
        $this->assertSame(2, $second->rank);
        $this->assertSame(RankingScope::WithinSection, $first->scopeEnum());
    }

    public function test_unavailable_kpis_make_team_incomplete_and_unranked(): void
    {
        $context = $this->rankingContext();
        $definitions = app(KpiDefinitionCatalog::class)->publishHaldenV1();
        $this->storeAvailableKpis($context['runtimeWeek'], $context['teamSimulation'], $definitions, '100.0000');
        $this->storeUnavailableKpis($context['runtimeWeek'], $context['otherTeamSimulation'], $definitions);

        $snapshots = app(RankingCalculationService::class)->calculateForSectionWeek($context['runtimeWeek']);
        $incomplete = collect($snapshots)->firstWhere('team_simulation_id', $context['otherTeamSimulation']->id);

        $this->assertSame(RankingSnapshotStatus::Incomplete, $incomplete->statusEnum());
        $this->assertNull($incomplete->composite_score);
        $this->assertNull($incomplete->rank);
        $this->assertStringContainsString('requires future mechanic', $incomplete->incomplete_reason);
    }

    public function test_historical_rank_snapshots_are_preserved(): void
    {
        $context = $this->rankingContext();
        $definitions = app(KpiDefinitionCatalog::class)->publishHaldenV1();
        $this->storeAvailableKpis($context['runtimeWeek'], $context['teamSimulation'], $definitions, '100.0000');
        $this->storeAvailableKpis($context['runtimeWeek'], $context['otherTeamSimulation'], $definitions, '80.0000');
        $firstRun = app(RankingCalculationService::class)->calculateForSectionWeek($context['runtimeWeek']);
        $firstTeamRank = collect($firstRun)->firstWhere('team_simulation_id', $context['teamSimulation']->id);

        $this->storeAvailableKpis($context['runtimeWeek'], $context['teamSimulation'], $definitions, '70.0000');
        $this->storeAvailableKpis($context['runtimeWeek'], $context['otherTeamSimulation'], $definitions, '120.0000');
        $secondRun = app(RankingCalculationService::class)->calculateForSectionWeek($context['runtimeWeek']);
        $secondTeamRank = collect($secondRun)->where('team_simulation_id', $context['teamSimulation']->id)->last();

        $this->assertSame(1, $firstTeamRank->refresh()->rank);
        $this->assertSame('90.000000', $firstTeamRank->refresh()->composite_score);
        $this->assertSame(2, $secondTeamRank->rank);
        $this->assertSame(4, RankingSnapshot::query()->count());
    }

    public function test_weight_version_is_captured_in_ranking_inputs(): void
    {
        $context = $this->rankingContext();
        $definitions = app(KpiDefinitionCatalog::class)->publishHaldenV1();
        $this->storeAvailableKpis($context['runtimeWeek'], $context['teamSimulation'], $definitions, '100.0000');

        KpiDefinition::query()->create([
            'key' => 'integrated_margin_per_boe',
            'name' => 'Integrated margin per BOE',
            'description' => 'Later version fixture',
            'weight' => '0.250000',
            'calculation_source' => 'economic_resolution.integrated_margin_per_boe',
            'version' => 'halden_kpi_v2',
            'status' => KpiDefinitionStatus::Published->value,
            'effective_from' => now()->addDay()->toDateString(),
        ]);

        $snapshot = app(RankingCalculationService::class)->calculateForSectionWeek($context['runtimeWeek'])[0];

        $this->assertSame(KpiDefinitionCatalog::HALDEN_KPI_VERSION, $snapshot->input_snapshot['kpi_definition_version']);
        $this->assertSame('0.300000', $snapshot->input_snapshot['required_kpis'][0]['weight']);
    }

    public function test_student_cannot_view_another_team_ranking_snapshot(): void
    {
        $context = $this->rankingContext();
        $definitions = app(KpiDefinitionCatalog::class)->publishHaldenV1();
        $this->storeAvailableKpis($context['runtimeWeek'], $context['otherTeamSimulation'], $definitions, '100.0000');
        $snapshot = app(RankingCalculationService::class)->calculateForSectionWeek($context['runtimeWeek'])[1];

        $this->expectException(InvalidArgumentException::class);

        app(RankingCalculationService::class)->assertCanView($context['student'], $snapshot);
    }

    public function test_ranking_calculation_is_deterministic_for_same_inputs(): void
    {
        $context = $this->rankingContext();
        $definitions = app(KpiDefinitionCatalog::class)->publishHaldenV1();
        $this->storeAvailableKpis($context['runtimeWeek'], $context['teamSimulation'], $definitions, '90.0000');
        $this->storeAvailableKpis($context['runtimeWeek'], $context['otherTeamSimulation'], $definitions, '90.0000');

        $firstRun = app(RankingCalculationService::class)->calculateForSectionWeek($context['runtimeWeek']);
        $secondRun = app(RankingCalculationService::class)->calculateForSectionWeek($context['runtimeWeek']);

        $this->assertSame(
            collect($firstRun)->map(fn (RankingSnapshot $snapshot) => [$snapshot->team_simulation_id, $snapshot->composite_score, $snapshot->rank])->all(),
            collect($secondRun)->map(fn (RankingSnapshot $snapshot) => [$snapshot->team_simulation_id, $snapshot->composite_score, $snapshot->rank])->all(),
        );
    }

    public function test_cross_section_scope_is_reserved_for_future_batch(): void
    {
        $context = $this->rankingContext();

        $this->expectException(InvalidArgumentException::class);

        app(RankingCalculationService::class)->calculateForSectionWeek($context['runtimeWeek'], RankingScope::CrossSection);
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     student: User,
     *     otherStudent: User,
     *     sectionSimulation: SectionSimulation,
     *     runtimeWeek: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation,
     *     otherTeamSimulation: TeamSimulation
     * }
     */
    private function rankingContext(): array
    {
        $graph = $this->tenantGraph('A');
        [$otherStudent] = $this->addSecondTeam($graph);
        $structure = $this->simulationStructure(4);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week4 */
        $week4 = $structure['simulationWeeks']->firstWhere('week_number', 4);
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week4->id)
            ->firstOrFail();
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();
        $otherTeamSimulation = TeamSimulation::query()
            ->where('tenant_id', $graph['tenant']->id)
            ->where('section_simulation_id', $sectionSimulation->id)
            ->whereHas('team.members', fn ($query) => $query->whereKey($otherStudent->id))
            ->firstOrFail();

        return [
            'graph' => $graph,
            'student' => $graph['student'],
            'otherStudent' => $otherStudent,
            'sectionSimulation' => $sectionSimulation,
            'runtimeWeek' => $runtimeWeek,
            'teamSimulation' => $teamSimulation,
            'otherTeamSimulation' => $otherTeamSimulation,
        ];
    }

    /**
     * @param  iterable<KpiDefinition>  $definitions
     */
    private function storeAvailableKpis(SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation, iterable $definitions, string $value): void
    {
        foreach ($definitions as $definition) {
            KpiSnapshot::query()->create([
                'tenant_id' => $runtimeWeek->tenant_id,
                'section_simulation_id' => $runtimeWeek->section_simulation_id,
                'section_simulation_week_id' => $runtimeWeek->id,
                'team_simulation_id' => $teamSimulation->id,
                'team_id' => $teamSimulation->team_id,
                'kpi_definition_id' => $definition->id,
                'status' => KpiSnapshotStatus::Available->value,
                'value' => $value,
                'unit' => 'score_points',
                'precision' => 2,
                'calculation_version' => 'fixture_v1',
                'input_snapshot' => ['fixture' => true],
                'calculated_at' => now(),
            ]);
        }
    }

    /**
     * @param  iterable<KpiDefinition>  $definitions
     */
    private function storeUnavailableKpis(SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation, iterable $definitions): void
    {
        foreach ($definitions as $definition) {
            KpiSnapshot::query()->create([
                'tenant_id' => $runtimeWeek->tenant_id,
                'section_simulation_id' => $runtimeWeek->section_simulation_id,
                'section_simulation_week_id' => $runtimeWeek->id,
                'team_simulation_id' => $teamSimulation->id,
                'team_id' => $teamSimulation->team_id,
                'kpi_definition_id' => $definition->id,
                'status' => KpiSnapshotStatus::Unavailable->value,
                'value' => null,
                'unit' => null,
                'precision' => 2,
                'calculation_version' => 'fixture_v1',
                'input_snapshot' => ['fixture' => true],
                'unavailable_reason' => 'requires future mechanic',
                'calculated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{User, Team}
     */
    private function addSecondTeam(array $graph): array
    {
        $student = User::factory()->student()->create([
            'tenant_id' => $graph['tenant']->id,
            'email' => 'student-second-ranking@example.test',
        ]);
        $team = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'name' => 'Team second ranking',
            'slug' => 'team-second-ranking',
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
