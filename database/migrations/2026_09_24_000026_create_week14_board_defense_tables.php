<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_defense_submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_simulation_week_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('submitted_by_user_id')->nullable();
            $table->foreignId('updated_by_user_id')->nullable();
            $table->string('status')->default('draft');
            $table->longText('final_synthesis_memo')->nullable();
            $table->json('artifact_references')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('draft_saved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'section_simulation_week_id', 'team_simulation_id'], 'board_defense_submission_current_unique');
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'section_simulation_week_id'], 'board_subs_tenant_runtime_week_fk')->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'], 'board_subs_tenant_team_sim_team_fk')->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'submitted_by_user_id'])->references(['tenant_id', 'id'])->on('users')->nullOnDelete();
            $table->foreign(['tenant_id', 'updated_by_user_id'])->references(['tenant_id', 'id'])->on('users')->nullOnDelete();
        });

        Schema::create('board_defense_submission_revisions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_defense_submission_id');
            $table->unsignedInteger('revision_number');
            $table->string('status');
            $table->longText('final_synthesis_memo')->nullable();
            $table->json('artifact_references')->nullable();
            $table->foreignId('actor_user_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['board_defense_submission_id', 'revision_number'], 'board_defense_submission_revision_unique');
            $table->foreign(['tenant_id', 'board_defense_submission_id'], 'board_sub_revs_tenant_board_submission_fk')->references(['tenant_id', 'id'])->on('board_defense_submissions')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'actor_user_id'], 'board_sub_revs_tenant_actor_fk')->references(['tenant_id', 'id'])->on('users')->nullOnDelete();
        });

        Schema::create('board_defense_assessments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_simulation_week_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('board_defense_submission_id')->nullable();
            $table->foreignId('reviewer_user_id');
            $table->string('rubric_version')->default('week14-board-defense-v1');
            $table->string('status')->default('draft');
            $table->string('reasoning_outcome_tier')->nullable();
            $table->longText('faculty_private_notes')->nullable();
            $table->json('history_packet_snapshot')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'section_simulation_week_id', 'team_simulation_id', 'rubric_version'], 'board_defense_assessment_current_unique');
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'section_simulation_week_id'], 'board_assessments_tenant_runtime_week_fk')->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'], 'board_assessments_tenant_team_sim_team_fk')->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'board_defense_submission_id'], 'board_assessments_tenant_board_submission_fk')->references(['tenant_id', 'id'])->on('board_defense_submissions')->nullOnDelete();
            $table->foreign(['tenant_id', 'reviewer_user_id'])->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        Schema::create('board_defense_assessment_dimensions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_defense_assessment_id');
            $table->string('dimension_key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('faculty_evaluation')->nullable();
            $table->longText('comments')->nullable();
            $table->timestamps();

            $table->unique(['board_defense_assessment_id', 'dimension_key'], 'board_defense_assessment_dimension_unique');
            $table->foreign(['tenant_id', 'board_defense_assessment_id'], 'board_dimensions_tenant_board_assessment_fk')->references(['tenant_id', 'id'])->on('board_defense_assessments')->cascadeOnDelete();
        });

        Schema::create('board_defense_assessment_feedback', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_defense_assessment_id');
            $table->longText('feedback_body')->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('published_by_user_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'board_defense_assessment_id'], 'board_defense_assessment_feedback_unique');
            $table->foreign(['tenant_id', 'board_defense_assessment_id'], 'board_feedback_tenant_board_assessment_fk')->references(['tenant_id', 'id'])->on('board_defense_assessments')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'published_by_user_id'], 'board_feedback_tenant_published_by_fk')->references(['tenant_id', 'id'])->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_defense_assessment_feedback');
        Schema::dropIfExists('board_defense_assessment_dimensions');
        Schema::dropIfExists('board_defense_assessments');
        Schema::dropIfExists('board_defense_submission_revisions');
        Schema::dropIfExists('board_defense_submissions');
    }
};
