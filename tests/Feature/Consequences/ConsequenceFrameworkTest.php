<?php

namespace Tests\Feature\Consequences;

use App\Domain\Consequences\ConsequenceService;
use App\Domain\Standing\StandingService;
use App\Enums\DecisionFieldType;
use App\Enums\StandingValue;
use App\Enums\SubmissionStatus;
use App\Models\ConsequenceDefinition;
use App\Models\ConsequenceLink;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\StandingState;
use App\Models\TeamSimulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class ConsequenceFrameworkTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_consequence_link_creation_snapshots_definition_and_context(): void
    {
        $context = $this->consequenceContext();
        $definition = $this->definition();

        $link = app(ConsequenceService::class)->createLink(
            teamSimulation: $context['teamSimulation'],
            definition: $definition,
            source: $context['decision'],
            target: $context['standing'],
            explanation: 'Transfer price posture may affect later cooperation.',
            sourceWeek: $context['week4'],
            targetWeek: $context['week10'],
            actor: $context['graph']['faculty'],
            metadata: ['severity' => 'qualitative'],
        );

        $this->assertSame($context['graph']['tenant']->id, $link->tenant_id);
        $this->assertSame($context['teamSimulation']->id, $link->team_simulation_id);
        $this->assertSame($context['week4']->id, $link->source_section_simulation_week_id);
        $this->assertSame($context['week10']->id, $link->target_section_simulation_week_id);
        $this->assertSame('week4_transfer_price_partner_resistance', $link->definition_key);
        $this->assertSame('v1', $link->definition_version);
        $this->assertSame('standing_constraint', $link->effect_type);
        $this->assertSame(['severity' => 'qualitative'], $link->metadata);
    }

    public function test_consequence_links_are_immutable_history(): void
    {
        $link = $this->createConsequenceLink();

        $this->expectException(InvalidArgumentException::class);

        $link->update(['explanation' => 'changed']);
    }

    public function test_cross_week_references_are_preserved(): void
    {
        $context = $this->consequenceContext();
        $link = $this->createConsequenceLink($context);

        $this->assertSame($context['week4']->id, $link->source_section_simulation_week_id);
        $this->assertSame($context['week10']->id, $link->target_section_simulation_week_id);
        $this->assertNotSame($link->source_section_simulation_week_id, $link->target_section_simulation_week_id);
    }

    public function test_forward_retrieval_answers_what_a_decision_affected(): void
    {
        $context = $this->consequenceContext();
        $link = $this->createConsequenceLink($context);

        $links = app(ConsequenceService::class)->forwardFrom($context['decision'], $context['teamSimulation']);

        $this->assertCount(1, $links);
        $this->assertTrue($link->is($links->first()));
    }

    public function test_backward_retrieval_answers_why_a_state_is_constrained(): void
    {
        $context = $this->consequenceContext();
        $link = $this->createConsequenceLink($context);

        $links = app(ConsequenceService::class)->backwardTo($context['standing'], $context['teamSimulation']);

        $this->assertCount(1, $links);
        $this->assertTrue($link->is($links->first()));
    }

    public function test_student_cannot_view_another_team_consequence_link(): void
    {
        $first = $this->consequenceContext('A');
        $second = $this->consequenceContext('B');
        $link = $this->createConsequenceLink($second);

        $this->expectException(InvalidArgumentException::class);

        app(ConsequenceService::class)->assertCanView($first['graph']['student'], $link);
    }

    public function test_source_and_target_context_must_match_team_simulation(): void
    {
        $context = $this->consequenceContext('A');
        $other = $this->consequenceContext('B');

        $this->expectException(InvalidArgumentException::class);

        app(ConsequenceService::class)->createLink(
            teamSimulation: $context['teamSimulation'],
            definition: $this->definition(),
            source: $other['decision'],
            target: $context['standing'],
            explanation: 'Invalid cross-tenant source.',
            sourceWeek: $context['week4'],
            targetWeek: $context['week10'],
            actor: $context['graph']['faculty'],
        );
    }

    public function test_definition_types_must_match_source_and_target_entities(): void
    {
        $context = $this->consequenceContext();
        $definition = ConsequenceDefinition::query()->create([
            'key' => 'wrong_types',
            'name' => 'Wrong types',
            'source_type' => StandingState::class,
            'target_type' => DecisionSubmission::class,
            'effect_type' => 'standing_constraint',
            'version' => 'v1',
            'is_active' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(ConsequenceService::class)->createLink(
            teamSimulation: $context['teamSimulation'],
            definition: $definition,
            source: $context['decision'],
            target: $context['standing'],
            explanation: 'Invalid type mapping.',
            sourceWeek: $context['week4'],
            targetWeek: $context['week10'],
            actor: $context['graph']['faculty'],
        );
    }

    private function createConsequenceLink(?array $context = null): ConsequenceLink
    {
        $context ??= $this->consequenceContext();

        return app(ConsequenceService::class)->createLink(
            teamSimulation: $context['teamSimulation'],
            definition: $this->definition(),
            source: $context['decision'],
            target: $context['standing'],
            explanation: 'Transfer price posture may affect later cooperation.',
            sourceWeek: $context['week4'],
            targetWeek: $context['week10'],
            actor: $context['graph']['faculty'],
        );
    }

    private function definition(): ConsequenceDefinition
    {
        return ConsequenceDefinition::query()->firstOrCreate(
            [
                'key' => 'week4_transfer_price_partner_resistance',
                'version' => 'v1',
            ],
            [
                'name' => 'Week 4 transfer-price partner resistance',
                'description' => 'Framework fixture for transfer pricing consequences.',
                'source_type' => DecisionSubmission::class,
                'target_type' => StandingState::class,
                'effect_type' => 'standing_constraint',
                'is_active' => true,
                'metadata' => ['batch' => '6B'],
            ],
        );
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     week4: SectionSimulationWeek,
     *     week10: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation,
     *     decision: DecisionSubmission,
     *     standing: StandingState
     * }
     */
    private function consequenceContext(string $suffix = 'A'): array
    {
        $graph = $this->tenantGraph($suffix);
        $structure = $this->simulationStructure(10);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week4Definition */
        $week4Definition = $structure['simulationWeeks']->firstWhere('week_number', 4);
        /** @var SimulationWeek $week10Definition */
        $week10Definition = $structure['simulationWeeks']->firstWhere('week_number', 10);
        $week4 = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week4Definition->id)
            ->firstOrFail();
        $week10 = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week10Definition->id)
            ->firstOrFail();
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();
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
            'field_type' => DecisionFieldType::Decimal,
            'is_required' => true,
            'display_order' => 1,
        ]);
        $decision = DecisionSubmission::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_simulation_id' => $sectionSimulation->id,
            'section_simulation_week_id' => $week4->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $graph['team']->id,
            'decision_form_definition_id' => $decisionDefinition->id,
            'status' => SubmissionStatus::Submitted,
            'answers' => ['transfer_price' => '76.75'],
            'lock_version' => 0,
            'submitted_by_user_id' => $graph['student']->id,
            'submitted_at' => now(),
        ]);
        $standing = app(StandingService::class)->initializeTeamSimulation(
            teamSimulation: $teamSimulation,
            initialState: StandingValue::Guarded,
            reason: 'Initial test relationship.',
        )[0];

        return compact('graph', 'sectionSimulation', 'week4', 'week10', 'teamSimulation', 'decision', 'standing');
    }
}
