<?php

namespace Tests\Feature\Scoring;

use App\Domain\Consequences\KpiConsequenceDefinitionCatalog;
use App\Domain\Consequences\KpiConsequenceReferencePackage;
use App\Domain\Ranking\RankingCalculationService;
use App\Domain\Scoring\KpiCalculationContext;
use App\Domain\Scoring\KpiCalculationService;
use App\Domain\Scoring\KpiDefinitionCatalog;
use App\Domain\Scoring\KpiFinancialStateService;
use App\Enums\KpiSnapshotStatus;
use App\Enums\RankingSnapshotStatus;
use App\Models\KpiSnapshot;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\Team;
use App\Models\TeamSimulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class KpiConsequencePackageIntegrationTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_kpi_consequence_package_provenance_and_active_catalog_load(): void
    {
        $package = app(KpiConsequenceReferencePackage::class);

        $package->validateProvenance();
        $this->assertSame('1.0.1', $package->version());
        $this->assertCount(7, $package->kpiDefinitions());
        $this->assertNotEmpty($package->kpiRules());
        $this->assertSame('active', $package->catalogRow('week4_cohort_state')['status']);

        $definitions = app(KpiConsequenceDefinitionCatalog::class)->registerActiveDefinitions();

        $this->assertNotEmpty($definitions);
        $this->assertTrue(collect($definitions)->contains(fn ($definition): bool => $definition->key === 'week4_cohort_state'));
        $this->assertFalse(collect($definitions)->contains(fn ($definition): bool => str_starts_with($definition->key, 'week6_kessana')));
    }

    public function test_opening_state_reproduces_package_golden_kpis(): void
    {
        $package = app(KpiConsequenceReferencePackage::class);
        $state = app(KpiFinancialStateService::class)->fromInputs([], throughWeek: 1);
        $definitions = app(KpiDefinitionCatalog::class)->publishHaldenV1();
        $results = collect(app(KpiCalculationService::class)->calculate($this->context($state->state, 1), $definitions))
            ->keyBy(fn ($result) => $result->definition->key);
        $golden = $package->goldenFixture()['results'];

        foreach ([
            'integrated_margin_per_boe',
            'roace',
            'free_cash_flow',
            'refining_net_margin_vs_benchmark',
            'retail_non_fuel_margin_per_site',
            'net_debt_to_ebitda',
            'asset_health_index',
        ] as $key) {
            $this->assertSame(KpiSnapshotStatus::Available, $results[$key]->status);
            $this->assertEqualsWithDelta(
                (float) $golden["opening_{$key}"],
                (float) (string) $results[$key]->value,
                0.0001,
            );
        }
    }

    public function test_reference_team_inputs_reproduce_final_golden_kpis(): void
    {
        $package = app(KpiConsequenceReferencePackage::class);
        $inputs = collect($package->referenceTeamInputs())
            ->where('team', 'steady')
            ->mapWithKeys(fn (array $row): array => [$row['input_key'] => $row['value']])
            ->all();
        $state = app(KpiFinancialStateService::class)->fromInputs($inputs, throughWeek: 13);
        $definitions = app(KpiDefinitionCatalog::class)->publishHaldenV1();
        $results = collect(app(KpiCalculationService::class)->calculate($this->context($state->state, 13), $definitions))
            ->keyBy(fn ($result) => $result->definition->key);
        $golden = $package->goldenFixture()['results'];

        foreach ([
            'integrated_margin_per_boe',
            'roace',
            'free_cash_flow',
            'refining_net_margin_vs_benchmark',
            'retail_non_fuel_margin_per_site',
            'net_debt_to_ebitda',
            'asset_health_index',
        ] as $key) {
            $this->assertEqualsWithDelta(
                (float) $golden["steady_w13_{$key}"],
                (float) (string) $results[$key]->value,
                0.001,
            );
        }
    }

    public function test_ranking_uses_min_max_normalization_and_reverses_debt_metric(): void
    {
        $context = $this->rankingContext();
        $definitions = app(KpiDefinitionCatalog::class)->publishHaldenV1();

        foreach ($definitions as $definition) {
            $firstValue = $definition->key === 'net_debt_to_ebitda' ? '1.0000' : '100.0000';
            $secondValue = $definition->key === 'net_debt_to_ebitda' ? '2.0000' : '0.0000';
            $this->storeKpi($context['runtimeWeek'], $context['teamSimulation'], $definition->id, $firstValue);
            $this->storeKpi($context['runtimeWeek'], $context['otherTeamSimulation'], $definition->id, $secondValue);
        }

        $rankings = app(RankingCalculationService::class)->calculateForSectionWeek($context['runtimeWeek']);
        $first = collect($rankings)->firstWhere('team_simulation_id', $context['teamSimulation']->id);
        $second = collect($rankings)->firstWhere('team_simulation_id', $context['otherTeamSimulation']->id);

        $this->assertSame(RankingSnapshotStatus::Complete, $first->statusEnum());
        $this->assertSame('100.000000', $first->composite_score);
        $this->assertSame(1, $first->rank);
        $this->assertSame('0.000000', $second->composite_score);
        $this->assertSame(2, $second->rank);
        $this->assertSame('lower', $first->input_snapshot['normalization'][$definitions->firstWhere('key', 'net_debt_to_ebitda')->id]['direction']);
    }

    /**
     * @param  array<string, string>  $state
     */
    private function context(array $state, int $week): KpiCalculationContext
    {
        return new KpiCalculationContext(
            tenantId: 1,
            sectionSimulationId: 1,
            sectionSimulationWeekId: $week,
            teamSimulationId: 1,
            teamId: 1,
            sourceType: 'fixture',
            sourceId: null,
            availableInputs: $state,
            inputSnapshot: ['fixture_week' => $week],
        );
    }

    /**
     * @return array{runtimeWeek: SectionSimulationWeek, teamSimulation: TeamSimulation, otherTeamSimulation: TeamSimulation}
     */
    private function rankingContext(): array
    {
        $graph = $this->tenantGraph('A');
        $structure = $this->simulationStructure(4);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week4 */
        $week4 = $structure['simulationWeeks']->firstWhere('week_number', 4);
        $runtimeWeek = $sectionSimulation->weeks()->where('simulation_week_id', $week4->id)->firstOrFail();
        $teamSimulation = $sectionSimulation->teamSimulations()->where('team_id', $graph['team']->id)->firstOrFail();
        $otherTeam = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'name' => 'Package ranking peer',
            'slug' => 'package-ranking-peer',
        ]);
        $otherTeamSimulation = TeamSimulation::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_simulation_id' => $sectionSimulation->id,
            'section_id' => $graph['section']->id,
            'team_id' => $otherTeam->id,
        ]);

        return compact('runtimeWeek', 'teamSimulation', 'otherTeamSimulation');
    }

    private function storeKpi(SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation, int $definitionId, string $value): void
    {
        KpiSnapshot::query()->create([
            'tenant_id' => $runtimeWeek->tenant_id,
            'section_simulation_id' => $runtimeWeek->section_simulation_id,
            'section_simulation_week_id' => $runtimeWeek->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'kpi_definition_id' => $definitionId,
            'status' => KpiSnapshotStatus::Available->value,
            'value' => $value,
            'unit' => 'fixture',
            'precision' => 4,
            'calculation_version' => 'fixture',
            'input_snapshot' => ['fixture' => true],
            'calculated_at' => now(),
        ]);
    }
}
