<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\DecisionSubmission;
use App\Models\DecisionSubmissionRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DecisionSubmissionRevision>
 */
class DecisionSubmissionRevisionFactory extends Factory
{
    public function definition(): array
    {
        $submission = DecisionSubmission::factory()->create();

        return [
            'tenant_id' => $submission->tenant_id,
            'decision_submission_id' => $submission->id,
            'decision_form_definition_id' => $submission->decision_form_definition_id,
            'revision_number' => 1,
            'status' => SubmissionStatus::Draft,
            'answers' => [],
            'actor_user_id' => null,
            'submitted_at' => null,
        ];
    }
}
