<?php

namespace Tests\Feature\Submissions;

use App\Domain\CausalTrace\CausalTraceService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class DecisionDefinitionVersioningTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_submission_captures_definition_version_and_available_alternatives(): void
    {
        $context = $this->versioningContext();

        $submission = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['definition'],
            [
                'operating_mode' => 'discipline',
                'capacity_shift' => '12.5',
            ],
        );

        $snapshot = $submission->definition_snapshot;

        $this->assertSame('test_definition_v1', $snapshot['definition']['version']);
        $this->assertSame('week-versioning-test', $snapshot['definition']['key']);
        $this->assertSame('discipline', $snapshot['available_alternatives']['operating_mode']['selected']);
        $this->assertSame('capacity_shift', $snapshot['available_alternatives']['capacity_shift']['field_key']);
        $this->assertEquals(['min' => 0, 'max' => 100], $snapshot['available_alternatives']['capacity_shift']['validation']);
        $this->assertSame(
            ['growth', 'stress'],
            collect($snapshot['available_alternatives']['operating_mode']['not_selected_options'])->pluck('value')->all(),
        );

        $revision = $submission->revisions()->firstOrFail();
        $this->assertSame($snapshot, $revision->definition_snapshot);
    }

    public function test_historical_snapshot_survives_later_definition_change(): void
    {
        $context = $this->versioningContext();

        $submission = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['definition'],
            [
                'operating_mode' => 'growth',
                'capacity_shift' => '25',
            ],
        );

        DB::table('decision_field_definitions')
            ->where('id', $context['optionField']->id)
            ->update([
                'label' => 'Updated operating mode',
                'options' => json_encode([
                    ['value' => 'retrench', 'label' => 'Retrenchment case'],
                    ['value' => 'acquire', 'label' => 'Acquisition case'],
                ]),
            ]);

        $submission->refresh();
        $snapshot = $submission->definition_snapshot;

        $this->assertSame('Operating mode', $snapshot['available_alternatives']['operating_mode']['label']);
        $this->assertSame(
            ['discipline', 'growth', 'stress'],
            collect($snapshot['available_alternatives']['operating_mode']['options'])->pluck('value')->all(),
        );
        $this->assertSame('growth', $snapshot['available_alternatives']['operating_mode']['selected']);
    }

    public function test_distinct_definition_versions_preserve_distinct_alternative_sets(): void
    {
        $context = $this->versioningContext();
        $v2 = $this->createDefinitionVersion($context, 'test_definition_v2', [
            ['value' => 'maintain', 'label' => 'Maintain case'],
            ['value' => 'accelerate', 'label' => 'Accelerate case'],
        ]);

        $submissionV1 = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['definition'],
            [
                'operating_mode' => 'stress',
                'capacity_shift' => '9',
            ],
        );

        $submissionV2 = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $v2,
            [
                'operating_mode' => 'accelerate',
                'capacity_shift' => '30',
            ],
        );

        $this->assertSame('test_definition_v1', $submissionV1->definition_snapshot['definition']['version']);
        $this->assertSame('test_definition_v2', $submissionV2->definition_snapshot['definition']['version']);
        $this->assertSame(
            ['discipline', 'growth', 'stress'],
            collect($submissionV1->definition_snapshot['available_alternatives']['operating_mode']['options'])->pluck('value')->all(),
        );
        $this->assertSame(
            ['maintain', 'accelerate'],
            collect($submissionV2->definition_snapshot['available_alternatives']['operating_mode']['options'])->pluck('value')->all(),
        );
    }

    public function test_causal_trace_uses_historical_snapshot_for_decision_context(): void
    {
        $context = $this->versioningContext();

        $submission = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['definition'],
            [
                'operating_mode' => 'discipline',
                'capacity_shift' => '44',
            ],
        );

        DB::table('decision_field_definitions')
            ->where('id', $context['optionField']->id)
            ->update([
                'options' => json_encode([
                    ['value' => 'late_change', 'label' => 'Late changed option'],
                ]),
            ]);

        $trace = app(CausalTraceService::class)->forwardFromDecision($context['graph']['faculty'], $submission->refresh());
        $decisionNode = collect($trace->nodes)->firstWhere('type', 'decision');

        $this->assertNotNull($decisionNode);
        $this->assertSame('test_definition_v1', $decisionNode->payload['definition_version']);
        $this->assertSame('discipline', $decisionNode->payload['available_alternatives']['operating_mode']['selected']);
        $this->assertSame(
            ['discipline', 'growth', 'stress'],
            collect($decisionNode->payload['available_alternatives']['operating_mode']['options'])->pluck('value')->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function versioningContext(): array
    {
        $graph = $this->tenantGraph('versioning');
        $context = $this->openRuntimeWeekWithDefinitions($graph);
        $definition = $this->createDefinitionVersion($context, 'test_definition_v1', [
            ['value' => 'discipline', 'label' => 'Discipline case'],
            ['value' => 'growth', 'label' => 'Growth case'],
            ['value' => 'stress', 'label' => 'Stress case'],
        ]);

        return [
            ...$context,
            'graph' => $graph,
            'definition' => $definition,
            'optionField' => $definition->fields()->where('field_key', 'operating_mode')->firstOrFail(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<array{value: string, label: string}>  $options
     */
    private function createDefinitionVersion(array $context, string $version, array $options): DecisionFormDefinition
    {
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $context['runtimeWeek']->simulation_version_id,
            'simulation_week_id' => $context['runtimeWeek']->simulation_week_id,
            'key' => 'week-versioning-test',
            'name' => 'Week versioning test',
            'version' => $version,
            'metadata' => ['test_context' => 'definition-versioning'],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $definition->id,
            'field_key' => 'operating_mode',
            'label' => 'Operating mode',
            'field_type' => DecisionFieldType::Radio,
            'is_required' => true,
            'display_order' => 1,
            'options' => $options,
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $definition->id,
            'field_key' => 'capacity_shift',
            'label' => 'Capacity shift',
            'field_type' => DecisionFieldType::Decimal,
            'is_required' => true,
            'display_order' => 2,
            'unit' => 'percent',
            'validation' => ['min' => 0, 'max' => 100],
        ]);

        return $definition->load('fields');
    }
}
