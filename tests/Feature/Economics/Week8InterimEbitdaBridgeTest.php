<?php

namespace Tests\Feature\Economics;

use App\Domain\Consequences\DerivedWeek10ConstraintService;
use App\Domain\Economics\Week8\Week8InterimEbitdaBridge;
use App\Domain\Scoring\KpiFinancialStateService;
use App\Enums\SubmissionStatus;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\Week8EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week8InterimEbitdaBridgeTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_interim_bridge_calculates_positive_wti_delta_with_provenance(): void
    {
        $context = $this->teamContext();
        $evaluation = $this->week8Evaluation($context, deltaWti: '7', realizedWti: '88.00');

        $result = app(Week8InterimEbitdaBridge::class)->calculate($evaluation);

        $this->assertNotNull($result);
        $this->assertSame('7', (string) $result->deltaWti);
        $this->assertSame('415.1875', (string) $result->ebitdaEffectMusd);
        $this->assertSame(Week8InterimEbitdaBridge::IDENTIFIER, $result->provenance['bridge_identifier']);
        $this->assertSame('interim', $result->provenance['bridge_status']);
        $this->assertTrue($result->provenance['future_authoritative_mapping_pending']);
        $this->assertSame($evaluation->id, $result->provenance['week8_economic_evaluation_id']);
    }

    public function test_interim_bridge_calculates_negative_wti_delta(): void
    {
        $context = $this->teamContext();
        $evaluation = $this->week8Evaluation($context, deltaWti: '-4', realizedWti: '70.00');

        $result = app(Week8InterimEbitdaBridge::class)->calculate($evaluation);

        $this->assertNotNull($result);
        $this->assertSame('-4', (string) $result->deltaWti);
        $this->assertSame('-237.2500', (string) $result->ebitdaEffectMusd);
    }

    public function test_interim_bridge_does_not_manufacture_missing_wti_delta(): void
    {
        $context = $this->teamContext();
        $evaluation = $this->week8Evaluation($context, deltaWti: null, realizedWti: null);

        $this->assertNull(app(Week8InterimEbitdaBridge::class)->calculate($evaluation));
        $this->assertNull(app(DerivedWeek10ConstraintService::class)->resolveCashCushion($context['teamSimulation'], $context['graph']['faculty']));
    }

    public function test_week10_cash_cushion_and_kpi_state_share_the_same_interim_bridge_value(): void
    {
        $context = $this->teamContext();
        $week8 = $this->runtimeWeek($context['sectionSimulation'], 8);
        $this->week8Evaluation($context, deltaWti: '7', realizedWti: '88.00');

        $link = app(DerivedWeek10ConstraintService::class)->resolveCashCushion($context['teamSimulation'], $context['graph']['faculty']);
        $state = app(KpiFinancialStateService::class)->forTeamWeek($context['teamSimulation'], $week8);

        $this->assertSame('415.188', $link?->metadata['week8_ebitda_effect_musd']);
        $this->assertSame(Week8InterimEbitdaBridge::IDENTIFIER, $link?->metadata['week8_ebitda_bridge']['bridge_identifier']);
        $this->assertSame('415.1875', $state->inputs['i8_ebitda_effect_musd']);
        $this->assertSame($link?->metadata['week8_ebitda_bridge']['ebitda_effect_musd'], $state->inputs['i8_ebitda_effect_musd']);
        $this->assertSame(Week8InterimEbitdaBridge::IDENTIFIER, $state->inputs['i8_ebitda_bridge_identifier']);
    }

    /**
     * @return array<string, mixed>
     */
    private function teamContext(): array
    {
        $graph = $this->tenantGraph('Week8Bridge'.uniqid());
        $structure = $this->simulationStructure(10);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        $teamSimulation = $sectionSimulation->teamSimulations()->where('team_id', $graph['team']->id)->firstOrFail();

        return compact('graph', 'structure', 'sectionSimulation', 'teamSimulation');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function week8Evaluation(array $context, ?string $deltaWti, ?string $realizedWti): Week8EconomicEvaluation
    {
        $week8 = $this->runtimeWeek($context['sectionSimulation'], 8);
        $submission = $this->submission($context, $week8);

        return Week8EconomicEvaluation::query()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'section_simulation_id' => $context['sectionSimulation']->id,
            'section_simulation_week_id' => $week8->id,
            'team_simulation_id' => $context['teamSimulation']->id,
            'team_id' => $context['teamSimulation']->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => 'week8_opec_scenario',
            'engine_version' => 'test',
            'package_version' => 'test',
            'status' => Week8EconomicEvaluation::STATUS_CALCULATED,
            'expected_wti' => '80.70',
            'expected_upstream_impact_per_bbl' => '6.70',
            'expected_refining_crack' => '19.16',
            'realized_scenario_key' => $deltaWti === '-4' ? 'fails' : 'holds_partial',
            'realized_wti' => $realizedWti,
            'realized_upstream_impact_per_bbl' => $deltaWti,
            'realized_refining_crack' => '19.16',
            'realized_retail_volume_percent' => '0.000',
            'realization_snapshot' => $deltaWti === null ? [] : ['delta_wti' => $deltaWti],
            'input_snapshot' => [],
            'output_snapshot' => [],
            'evaluated_by_user_id' => $context['graph']['faculty']->id,
            'evaluated_by_process' => 'test',
            'evaluated_at' => now(),
        ]);
    }

    private function runtimeWeek(SectionSimulation $sectionSimulation, int $weekNumber): SectionSimulationWeek
    {
        return $sectionSimulation->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', $weekNumber))
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function submission(array $context, SectionSimulationWeek $week): DecisionSubmission
    {
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $week->simulation_version_id,
            'simulation_week_id' => $week->simulation_week_id,
        ]);

        return DecisionSubmission::query()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'section_simulation_id' => $context['sectionSimulation']->id,
            'section_simulation_week_id' => $week->id,
            'team_simulation_id' => $context['teamSimulation']->id,
            'team_id' => $context['teamSimulation']->team_id,
            'decision_form_definition_id' => $definition->id,
            'status' => SubmissionStatus::Submitted->value,
            'answers' => [],
            'lock_version' => 1,
            'submitted_at' => now(),
        ]);
    }
}
