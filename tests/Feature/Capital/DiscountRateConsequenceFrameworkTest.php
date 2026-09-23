<?php

namespace Tests\Feature\Capital;

use App\Domain\Capital\DiscountRateConsequenceService;
use App\Domain\Economics\Resolution\WeekResolutionService;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\CohortDecisionAggregate;
use App\Models\CohortFeedbackEffect;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\DiscountRateConsequence;
use App\Models\DiscountRateSchedule;
use App\Models\EconomicResolution;
use App\Models\KpiSnapshot;
use App\Models\RankingSnapshot;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class DiscountRateConsequenceFrameworkTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week4_resolution_creates_discount_rate_consequence_from_configured_classification(): void
    {
        $context = $this->resolvedWeek4Context('46.20');
        $schedule = $this->schedule([
            ['field' => 'geneva_capture_per_bbl', 'operator' => '<=', 'value' => '9.625', 'classification' => 'base'],
        ]);

        $consequence = app(DiscountRateConsequenceService::class)->resolve(
            $context['resolution'],
            $schedule,
            $context['graph']['faculty'],
        );

        $this->assertSame(DiscountRateConsequence::STATUS_RESOLVED, $consequence->status);
        $this->assertSame('base', $consequence->classification);
        $this->assertSame('8.500', $consequence->discount_rate_percent);
        $this->assertSame('1150.000', $consequence->capital_envelope_musd);
        $this->assertSame(6, $consequence->input_snapshot['target_week_number']);
        $this->assertSame('base', $consequence->result_snapshot['classification']);
        $this->assertSame($context['week6']->id, $consequence->target_section_simulation_week_id);
    }

    public function test_unresolved_classification_rules_are_stored_safely_without_guessing(): void
    {
        $context = $this->resolvedWeek4Context('46.20');
        $schedule = $this->schedule([]);

        $consequence = app(DiscountRateConsequenceService::class)->resolve(
            $context['resolution'],
            $schedule,
            $context['graph']['faculty'],
        );

        $this->assertSame(DiscountRateConsequence::STATUS_UNRESOLVED, $consequence->status);
        $this->assertNull($consequence->classification);
        $this->assertNull($consequence->discount_rate_percent);
        $this->assertNull($consequence->capital_envelope_musd);
        $this->assertSame('classification rules are not configured', $consequence->result_snapshot['reason']);
    }

    public function test_discount_rate_consequence_is_separate_from_cohort_kpi_and_ranking_records(): void
    {
        $context = $this->resolvedWeek4Context('46.20');
        $schedule = $this->schedule([
            ['field' => 'geneva_capture_per_bbl', 'operator' => '<=', 'value' => '9.625', 'classification' => 'base'],
        ]);

        app(DiscountRateConsequenceService::class)->resolve(
            $context['resolution'],
            $schedule,
            $context['graph']['faculty'],
        );

        $this->assertSame(0, CohortDecisionAggregate::query()->count());
        $this->assertSame(0, CohortFeedbackEffect::query()->count());
        $this->assertSame(0, KpiSnapshot::query()->count());
        $this->assertSame(0, RankingSnapshot::query()->count());
    }

    public function test_discount_rate_consequence_is_idempotent_and_immutable(): void
    {
        $context = $this->resolvedWeek4Context('46.20');
        $schedule = $this->schedule([
            ['field' => 'geneva_capture_per_bbl', 'operator' => '<=', 'value' => '9.625', 'classification' => 'base'],
        ]);
        $service = app(DiscountRateConsequenceService::class);

        $first = $service->resolve($context['resolution'], $schedule, $context['graph']['faculty']);
        $second = $service->resolve($context['resolution'], $schedule, $context['graph']['faculty']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, DiscountRateConsequence::query()->count());

        $this->expectException(InvalidArgumentException::class);

        $first->update(['classification' => 'lax']);
    }

    public function test_cross_tenant_faculty_cannot_resolve_discount_rate_consequence(): void
    {
        $first = $this->resolvedWeek4Context('46.20', 'A');
        $second = $this->resolvedWeek4Context('46.20', 'B');
        $schedule = $this->schedule([
            ['field' => 'geneva_capture_per_bbl', 'operator' => '<=', 'value' => '9.625', 'classification' => 'base'],
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(DiscountRateConsequenceService::class)->resolve(
            $second['resolution'],
            $schedule,
            $first['graph']['faculty'],
        );
    }

    public function test_schedule_source_week_must_match_week4_resolution(): void
    {
        $context = $this->resolvedWeek4Context('46.20');
        $schedule = $this->schedule([], sourceWeek: 3);

        $this->expectException(InvalidArgumentException::class);

        app(DiscountRateConsequenceService::class)->resolve(
            $context['resolution'],
            $schedule,
            $context['graph']['faculty'],
        );
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     week4: SectionSimulationWeek,
     *     week6: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation,
     *     submission: DecisionSubmission,
     *     resolution: EconomicResolution
     * }
     */
    private function resolvedWeek4Context(string $transferPrice, string $suffix = 'A'): array
    {
        $graph = $this->tenantGraph($suffix);
        $context = $this->openWeek4Context($graph);
        $submission = app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $context['week4'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['transfer_price' => $transferPrice],
        );
        $resolution = app(WeekResolutionService::class)->resolveSubmittedDecision($submission, $graph['student']);
        $sectionSimulation = $context['sectionSimulation'];
        $week4 = $context['week4'];
        $week6 = $context['week6'];
        $teamSimulation = $context['teamSimulation'];

        return compact('graph', 'sectionSimulation', 'week4', 'week6', 'teamSimulation', 'submission', 'resolution');
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{sectionSimulation: SectionSimulation, week4: SectionSimulationWeek, week6: SectionSimulationWeek, decisionDefinition: DecisionFormDefinition, teamSimulation: TeamSimulation}
     */
    private function openWeek4Context(array $graph): array
    {
        $structure = $this->simulationStructure(6);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week4Definition */
        $week4Definition = $structure['simulationWeeks']->firstWhere('week_number', 4);
        /** @var SimulationWeek $week6Definition */
        $week6Definition = $structure['simulationWeeks']->firstWhere('week_number', 6);
        $week4 = $sectionSimulation->weeks()->where('simulation_week_id', $week4Definition->id)->firstOrFail();
        $week6 = $sectionSimulation->weeks()->where('simulation_week_id', $week6Definition->id)->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $week4 = $lifecycle->transitionWeek($week4, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $week4 = $lifecycle->transitionWeek($week4->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $week4->simulation_version_id,
            'simulation_week_id' => $week4->simulation_week_id,
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

        return compact('sectionSimulation', 'week4', 'week6', 'decisionDefinition', 'teamSimulation');
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     */
    private function schedule(array $rules, int $sourceWeek = 4): DiscountRateSchedule
    {
        return DiscountRateSchedule::query()->create([
            'key' => 'week4_to_week6_discount_rate',
            'name' => 'Week 4 to Week 6 discount rate',
            'description' => 'Fixture schedule for Week 4 transfer pricing discipline effects.',
            'version' => 'discount_rate_v1_'.uniqid(),
            'source_week_number' => $sourceWeek,
            'target_week_number' => 6,
            'classification_rules' => $rules,
            'classification_outcomes' => [
                'disciplined' => [
                    'discount_rate_percent' => '6.5',
                    'capital_envelope_musd' => '1520',
                ],
                'base' => [
                    'discount_rate_percent' => '8.5',
                    'capital_envelope_musd' => '1150',
                ],
                'lax' => [
                    'discount_rate_percent' => '11.0',
                    'capital_envelope_musd' => '950',
                ],
            ],
            'is_active' => true,
        ]);
    }
}
