<?php

namespace Tests\Feature\Capital;

use App\Domain\Capital\CapitalAllocationService;
use App\Domain\Capital\DiscountRateConsequenceService;
use App\Domain\Economics\Resolution\WeekResolutionService;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\CapitalProject;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DiscountRateConsequence;
use App\Models\DiscountRateSchedule;
use App\Models\EconomicResolution;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class CapitalAllocationFrameworkTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_capital_projects_load_from_catalog(): void
    {
        $this->seedProjects();

        $projects = app(CapitalAllocationService::class)->activeProjects();

        $this->assertCount(3, $projects);
        $this->assertSame(['helix', 'tamar', 'valve'], $projects->pluck('key')->sort()->values()->all());
    }

    public function test_allocation_decision_saves_selected_and_rejected_alternatives_with_discount_context(): void
    {
        $context = $this->week6ContextWithDiscountRate();
        $this->seedProjects();

        $decision = app(CapitalAllocationService::class)->submitAllocation(
            $context['graph']['student'],
            $context['teamSimulation'],
            $context['week6'],
            ['helix', 'valve'],
            ['tamar'],
            ['memo_submission_id' => 42],
        );

        $this->assertSame($context['discountRateConsequence']->id, $decision->discount_rate_consequence_id);
        $this->assertSame(['helix', 'valve'], collect($decision->selected_projects)->pluck('key')->all());
        $this->assertSame(['tamar'], collect($decision->rejected_projects)->pluck('key')->all());
        $this->assertSame('available', $decision->context_snapshot['status']);
        $this->assertSame('8.500', $decision->context_snapshot['discount_rate_percent']);
        $this->assertSame('1150.000', $decision->context_snapshot['capital_envelope_musd']);
        $this->assertSame(42, $decision->memo_references['memo_submission_id']);
    }

    public function test_missing_discount_rate_context_is_preserved_safely(): void
    {
        $context = $this->week6ContextWithoutDiscountRate();
        $this->seedProjects();

        $decision = app(CapitalAllocationService::class)->submitAllocation(
            $context['graph']['student'],
            $context['teamSimulation'],
            $context['week6'],
            ['helix'],
            ['tamar'],
        );

        $this->assertNull($decision->discount_rate_consequence_id);
        $this->assertSame('missing_discount_rate_consequence', $decision->context_snapshot['status']);
    }

    public function test_unresolved_discount_rate_context_is_preserved_without_guessing(): void
    {
        $context = $this->week6ContextWithDiscountRate(resolve: false);
        $this->seedProjects();

        $decision = app(CapitalAllocationService::class)->submitAllocation(
            $context['graph']['student'],
            $context['teamSimulation'],
            $context['week6'],
            ['helix'],
            ['tamar'],
        );

        $this->assertSame($context['discountRateConsequence']->id, $decision->discount_rate_consequence_id);
        $this->assertSame('unavailable_discount_rate', $decision->context_snapshot['status']);
        $this->assertNull($decision->context_snapshot['classification']);
    }

    public function test_capital_allocation_decision_is_immutable(): void
    {
        $context = $this->week6ContextWithDiscountRate();
        $this->seedProjects();
        $decision = app(CapitalAllocationService::class)->submitAllocation(
            $context['graph']['student'],
            $context['teamSimulation'],
            $context['week6'],
            ['helix'],
            ['tamar'],
        );

        $this->expectException(InvalidArgumentException::class);

        $decision->update(['selected_projects' => []]);
    }

    public function test_team_cannot_submit_allocation_for_another_tenant(): void
    {
        $first = $this->week6ContextWithDiscountRate(suffix: 'A');
        $second = $this->week6ContextWithDiscountRate(suffix: 'B');
        $this->seedProjects();

        $this->expectException(InvalidArgumentException::class);

        app(CapitalAllocationService::class)->submitAllocation(
            $first['graph']['student'],
            $second['teamSimulation'],
            $second['week6'],
            ['helix'],
            ['tamar'],
        );
    }

    public function test_duplicate_allocation_submission_is_rejected(): void
    {
        $context = $this->week6ContextWithDiscountRate();
        $this->seedProjects();
        $service = app(CapitalAllocationService::class);

        $service->submitAllocation(
            $context['graph']['student'],
            $context['teamSimulation'],
            $context['week6'],
            ['helix'],
            ['tamar'],
        );

        $this->expectException(InvalidArgumentException::class);

        $service->submitAllocation(
            $context['graph']['student'],
            $context['teamSimulation'],
            $context['week6'],
            ['valve'],
            ['helix'],
        );
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     week4: SectionSimulationWeek,
     *     week6: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation,
     *     resolution: EconomicResolution,
     *     discountRateConsequence: DiscountRateConsequence
     * }
     */
    private function week6ContextWithDiscountRate(bool $resolve = true, string $suffix = 'A'): array
    {
        $context = $this->week6ContextWithoutDiscountRate($suffix);
        $schedule = $this->schedule($resolve);
        $discountRateConsequence = app(DiscountRateConsequenceService::class)->resolve(
            $context['resolution'],
            $schedule,
            $context['graph']['faculty'],
        );

        return array_merge($context, compact('discountRateConsequence'));
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     week4: SectionSimulationWeek,
     *     week6: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation,
     *     resolution: EconomicResolution
     * }
     */
    private function week6ContextWithoutDiscountRate(string $suffix = 'A'): array
    {
        $graph = $this->tenantGraph($suffix);
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
        $week6 = $lifecycle->transitionWeek($week6, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $week6 = $lifecycle->transitionWeek($week6->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

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
        $submission = app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $week4,
            $teamSimulation,
            $decisionDefinition,
            ['transfer_price' => '46.20'],
        );
        $resolution = app(WeekResolutionService::class)->resolveSubmittedDecision($submission, $graph['student']);

        return compact('graph', 'sectionSimulation', 'week4', 'week6', 'teamSimulation', 'resolution');
    }

    private function seedProjects(): void
    {
        foreach ([
            ['key' => 'helix', 'name' => 'Project Helix', 'category' => 'upstream', 'risk_class' => 'medium'],
            ['key' => 'tamar', 'name' => 'Project Tamar', 'category' => 'refining', 'risk_class' => 'high'],
            ['key' => 'valve', 'name' => 'Project Valve', 'category' => 'reliability', 'risk_class' => 'low'],
        ] as $project) {
            CapitalProject::query()->create([
                ...$project,
                'version' => 'week6_fixture_v1',
                'cash_flow_reference' => 'deferred_until_week6_package',
                'required_inputs' => ['requires_week6_package' => true],
                'metadata' => ['fixture' => true],
                'is_active' => true,
            ]);
        }
    }

    private function schedule(bool $resolve): DiscountRateSchedule
    {
        return DiscountRateSchedule::query()->create([
            'key' => 'week4_to_week6_discount_rate',
            'name' => 'Week 4 to Week 6 discount rate',
            'version' => 'discount_rate_v1_'.uniqid(),
            'source_week_number' => 4,
            'target_week_number' => 6,
            'classification_rules' => $resolve
                ? [['field' => 'geneva_capture_per_bbl', 'operator' => '<=', 'value' => '9.625', 'classification' => 'base']]
                : [],
            'classification_outcomes' => [
                'base' => [
                    'discount_rate_percent' => '8.5',
                    'capital_envelope_musd' => '1150',
                ],
            ],
            'is_active' => true,
        ]);
    }
}
