<?php

namespace Tests\Feature\Submissions;

use App\Domain\Submissions\DefinitionInputValidator;
use App\Models\DecisionFieldDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class SubmissionDefinitionTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_definition_fields_have_stable_order_and_reject_unknown_answers(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);

        $fields = $context['decisionDefinition']->fields->pluck('field_key')->all();

        $this->assertSame(['demo_quantity', 'demo_choice'], $fields);

        $this->expectException(ValidationException::class);

        app(DefinitionInputValidator::class)
            ->validateDecisionAnswers($context['decisionDefinition'], [
                'demo_quantity' => 10,
                'unknown_field' => 'nope',
            ], final: false);
    }

    public function test_frozen_decision_definition_and_fields_cannot_change(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);

        $this->expectException(InvalidArgumentException::class);

        $context['decisionDefinition']->update(['name' => 'Changed']);
    }

    public function test_frozen_field_definition_cannot_change(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);
        $field = DecisionFieldDefinition::query()
            ->where('decision_form_definition_id', $context['decisionDefinition']->id)
            ->firstOrFail();

        $this->expectException(InvalidArgumentException::class);

        $field->update(['label' => 'Changed']);
    }
}
