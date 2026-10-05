<?php

namespace Tests\Feature\Capital;

use App\Domain\Capital\DiscountRateConsequenceService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Enums\SubmissionStatus;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\DiscountRateConsequence;
use App\Models\DiscountRateSchedule;
use App\Models\EconomicResolution;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\Team;
use App\Models\TeamSimulation;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week4ToWeek6CohortDiscountRateTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_disciplined_section_state_applies_same_week6_context_to_every_team_at_exact_70_percent_boundary(): void
    {
        $context = $this->sectionContext(teamCount: 10);
        $prices = [...array_fill(0, 7, '18.70'), ...array_fill(0, 3, '46.20')];

        $this->seedWeek4Resolutions($context, $prices);
        $counts = app(DiscountRateConsequenceService::class)->resolveSectionCohort($context['week4'], $this->schedule(), $context['graph']['faculty']);

        $this->assertSame([DiscountRateConsequence::STATUS_RESOLVED => 10], $counts);
        $this->assertSectionResult('disciplined', '6.500', '1520.000');
    }

    public function test_lax_section_state_applies_at_market_based_70_percent_boundary(): void
    {
        $context = $this->sectionContext(teamCount: 10);
        $prices = [...array_fill(0, 7, '73.70'), ...array_fill(0, 3, '46.20')];

        $this->seedWeek4Resolutions($context, $prices);
        app(DiscountRateConsequenceService::class)->resolveSectionCohort($context['week4'], $this->schedule(), $context['graph']['faculty']);

        $this->assertSectionResult('lax', '11.000', '950.000');
    }

    public function test_base_section_state_applies_when_neither_anchor_reaches_70_percent(): void
    {
        $context = $this->sectionContext(teamCount: 10);
        $prices = [...array_fill(0, 6, '18.70'), ...array_fill(0, 3, '73.70'), '46.20'];

        $this->seedWeek4Resolutions($context, $prices);
        app(DiscountRateConsequenceService::class)->resolveSectionCohort($context['week4'], $this->schedule(), $context['graph']['faculty']);

        $this->assertSectionResult('base', '8.500', '1150.000');
    }

    public function test_transfer_price_classification_uses_ten_percent_bands_only(): void
    {
        $service = app(DiscountRateConsequenceService::class);

        $this->assertSame('marginal_cost', $service->classifyTransferPriceChoice(BigDecimal::of('16.83')));
        $this->assertSame('marginal_cost', $service->classifyTransferPriceChoice(BigDecimal::of('20.57')));
        $this->assertSame('market_based', $service->classifyTransferPriceChoice(BigDecimal::of('66.33')));
        $this->assertSame('market_based', $service->classifyTransferPriceChoice(BigDecimal::of('81.07')));
        $this->assertNull($service->classifyTransferPriceChoice(BigDecimal::of('46.20')));
        $this->assertNull($service->classifyTransferPriceChoice(BigDecimal::of('21.00')));
        $this->assertNull($service->classifyTransferPriceChoice(BigDecimal::of('65.00')));
    }

    public function test_section_and_tenant_isolation_are_preserved(): void
    {
        $first = $this->sectionContext('A', 3);
        $second = $this->sectionContext('B', 3);
        $this->seedWeek4Resolutions($first, ['18.70', '18.70', '18.70']);
        $this->seedWeek4Resolutions($second, ['73.70', '73.70', '73.70']);

        app(DiscountRateConsequenceService::class)->resolveSectionCohort($first['week4'], $this->schedule(), $first['graph']['faculty']);
        app(DiscountRateConsequenceService::class)->resolveSectionCohort($second['week4'], $this->schedule(), $second['graph']['faculty']);

        $firstResults = DiscountRateConsequence::query()->where('tenant_id', $first['graph']['tenant']->id)->pluck('classification')->unique()->values()->all();
        $secondResults = DiscountRateConsequence::query()->where('tenant_id', $second['graph']['tenant']->id)->pluck('classification')->unique()->values()->all();

        $this->assertSame(['disciplined'], $firstResults);
        $this->assertSame(['lax'], $secondResults);
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionContext(string $suffix = 'A', int $teamCount = 10): array
    {
        $graph = $this->tenantGraph($suffix);

        for ($i = 2; $i <= $teamCount; $i++) {
            Team::factory()->create([
                'tenant_id' => $graph['tenant']->id,
                'section_id' => $graph['section']->id,
                'name' => 'Team '.$suffix.' '.$i,
                'slug' => 'team-'.strtolower($suffix).'-'.$i,
            ]);
        }

        $structure = $this->simulationStructure(6);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        $week4 = $this->runtimeWeek($sectionSimulation, 4);
        $week6 = $this->runtimeWeek($sectionSimulation, 6);
        $service = app(SimulationLifecycleService::class);
        $service->transitionWeek($week4, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $week4 = $service->transitionWeek($week4->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());
        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $week4->simulation_version_id,
            'simulation_week_id' => $week4->simulation_week_id,
            'key' => 'week4_transfer_pricing',
            'name' => 'Week 4 transfer pricing',
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

        return compact('graph', 'sectionSimulation', 'week4', 'week6', 'decisionDefinition');
    }

    private function runtimeWeek(SectionSimulation $sectionSimulation, int $weekNumber): SectionSimulationWeek
    {
        return $sectionSimulation->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', $weekNumber))
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $prices
     */
    private function seedWeek4Resolutions(array $context, array $prices): void
    {
        $teamSimulations = $context['sectionSimulation']->teamSimulations()->orderBy('id')->get();

        foreach ($prices as $index => $price) {
            /** @var TeamSimulation $teamSimulation */
            $teamSimulation = $teamSimulations[$index];
            $submission = DecisionSubmission::query()->create([
                'tenant_id' => $context['graph']['tenant']->id,
                'section_simulation_id' => $context['sectionSimulation']->id,
                'section_simulation_week_id' => $context['week4']->id,
                'team_simulation_id' => $teamSimulation->id,
                'team_id' => $teamSimulation->team_id,
                'decision_form_definition_id' => $context['decisionDefinition']->id,
                'status' => SubmissionStatus::Submitted->value,
                'answers' => ['transfer_price' => $price],
                'lock_version' => 1,
                'submitted_at' => now(),
            ]);

            EconomicResolution::query()->create([
                'tenant_id' => $context['graph']['tenant']->id,
                'section_simulation_id' => $context['sectionSimulation']->id,
                'section_simulation_week_id' => $context['week4']->id,
                'team_simulation_id' => $teamSimulation->id,
                'team_id' => $teamSimulation->team_id,
                'decision_submission_id' => $submission->id,
                'economic_engine' => 'test',
                'engine_version' => 'test',
                'input_snapshot' => [],
                'output_snapshot' => [],
                'transfer_price' => $price,
                'integrated_margin' => '0',
                'upstream_margin' => '0',
                'refining_margin' => '0',
                'upstream_vs_target' => '0',
                'refining_vs_target' => '0',
                'geneva_gap' => '0',
                'geneva_capture_per_bbl' => '0',
                'geneva_max_volume_bbl_day' => '0',
                'resolved_by_user_id' => $context['graph']['faculty']->id,
                'resolved_by_process' => 'test',
                'resolved_at' => now(),
            ]);
        }
    }

    private function schedule(): DiscountRateSchedule
    {
        return DiscountRateSchedule::query()->firstOrCreate([
            'key' => 'week4_to_week6_discount_rate',
            'version' => 'kpi_consequence_v1_0_1_test',
        ], [
            'name' => 'Week 4 cohort discount-rate consequence',
            'description' => 'test',
            'source_week_number' => 4,
            'target_week_number' => 6,
            'classification_rules' => [
                'marginal_cost_anchor' => '18.70',
                'market_based_anchor' => '73.70',
                'tolerance' => '0.10',
                'section_threshold' => '0.70',
            ],
            'classification_outcomes' => [
                'disciplined' => ['discount_rate_percent' => '6.5', 'capital_envelope_musd' => '1520'],
                'base' => ['discount_rate_percent' => '8.5', 'capital_envelope_musd' => '1150'],
                'lax' => ['discount_rate_percent' => '11.0', 'capital_envelope_musd' => '950'],
            ],
            'is_active' => true,
        ]);
    }

    private function assertSectionResult(string $classification, string $discountRate, string $envelope): void
    {
        $consequences = DiscountRateConsequence::query()->orderBy('id')->get();

        $this->assertNotEmpty($consequences);
        $this->assertTrue($consequences->every(fn (DiscountRateConsequence $consequence): bool => $consequence->classification === $classification));
        $this->assertTrue($consequences->every(fn (DiscountRateConsequence $consequence): bool => $consequence->discountRatePercentValue() === $discountRate));
        $this->assertTrue($consequences->every(fn (DiscountRateConsequence $consequence): bool => $consequence->capitalEnvelopeMusdValue() === $envelope));
    }
}
