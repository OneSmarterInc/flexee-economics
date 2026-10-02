<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\MemoSubmission;
use App\Models\MemoSubmissionRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoSubmissionRevision>
 */
class MemoSubmissionRevisionFactory extends Factory
{
    public function definition(): array
    {
        $submission = MemoSubmission::factory()->create();

        return [
            'tenant_id' => $submission->tenant_id,
            'memo_submission_id' => $submission->id,
            'memo_definition_id' => $submission->memo_definition_id,
            'revision_number' => 1,
            'status' => SubmissionStatus::Draft,
            'body' => '',
            'word_count' => 0,
            'character_count' => 0,
            'actor_user_id' => null,
            'submitted_at' => null,
        ];
    }
}
