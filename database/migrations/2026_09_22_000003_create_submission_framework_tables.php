<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_form_definitions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('simulation_version_id');
            $table->foreignId('simulation_week_id');
            $table->string('key');
            $table->string('name');
            $table->string('version');
            $table->boolean('is_required')->default(true);
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['simulation_week_id', 'key', 'version']);
            $table->unique(['simulation_version_id', 'id']);
            $table->foreign(['simulation_version_id', 'simulation_week_id'], 'decision_forms_version_week_fk')->references(['simulation_version_id', 'id'])->on('simulation_weeks')->restrictOnDelete();
        });

        Schema::create('decision_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('decision_form_definition_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->string('label');
            $table->string('field_type');
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->text('help_text')->nullable();
            $table->string('unit')->nullable();
            $table->json('validation')->nullable();
            $table->json('options')->nullable();
            $table->json('visibility')->nullable();
            $table->timestamps();

            $table->unique(['decision_form_definition_id', 'field_key'], 'decision_fields_decision_form_field_uniq');
            $table->index(['decision_form_definition_id', 'display_order'], 'decision_fields_decision_form_display_idx');
        });

        Schema::create('memo_definitions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('simulation_version_id');
            $table->foreignId('simulation_week_id');
            $table->string('key');
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->string('version');
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('word_limit')->nullable();
            $table->unsignedInteger('character_limit')->nullable();
            $table->string('rubric_reference')->nullable();
            $table->string('submission_format')->default('text');
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['simulation_week_id', 'key', 'version']);
            $table->unique(['simulation_version_id', 'id']);
            $table->foreign(['simulation_version_id', 'simulation_week_id'], 'memo_defs_version_week_fk')->references(['simulation_version_id', 'id'])->on('simulation_weeks')->restrictOnDelete();
        });

        Schema::create('decision_submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_simulation_week_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('decision_form_definition_id');
            $table->string('status')->default('draft');
            $table->json('answers')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('updated_by_user_id')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable();
            $table->timestamp('draft_saved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'section_simulation_week_id', 'team_simulation_id', 'decision_form_definition_id'], 'decision_submissions_current_unique');
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'section_simulation_week_id'], 'decision_subs_tenant_runtime_week_fk')->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'], 'decision_subs_tenant_team_sim_team_fk')->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('submitted_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign(['decision_form_definition_id'], 'decision_submission_revision_definition_fk')
                ->references(['id'])
                ->on('decision_form_definitions')
                ->restrictOnDelete();
        });

        Schema::create('decision_submission_revisions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('decision_submission_id');
            $table->foreignId('decision_form_definition_id');
            $table->unsignedInteger('revision_number');
            $table->string('status');
            $table->json('answers');
            $table->foreignId('actor_user_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['decision_submission_id', 'revision_number'], 'decision_revs_decision_sub_revision_uniq');
            $table->foreign(['tenant_id', 'decision_submission_id'], 'decision_revs_tenant_decision_sub_fk')->references(['tenant_id', 'id'])->on('decision_submissions')->cascadeOnDelete();
            $table->foreign(['decision_form_definition_id'], 'decision_revs_decision_form_fk')->references(['id'])->on('decision_form_definitions')->restrictOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('memo_submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_simulation_week_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('memo_definition_id');
            $table->string('status')->default('draft');
            $table->longText('body')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('character_count')->default(0);
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('updated_by_user_id')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable();
            $table->timestamp('draft_saved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'section_simulation_week_id', 'team_simulation_id', 'memo_definition_id'], 'memo_submissions_current_unique');
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'section_simulation_week_id'])->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'])->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('submitted_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign(['memo_definition_id'])->references(['id'])->on('memo_definitions')->restrictOnDelete();
        });

        Schema::create('memo_submission_revisions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('memo_submission_id');
            $table->foreignId('memo_definition_id');
            $table->unsignedInteger('revision_number');
            $table->string('status');
            $table->longText('body');
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('character_count')->default(0);
            $table->foreignId('actor_user_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['memo_submission_id', 'revision_number'], 'memo_revs_memo_sub_revision_uniq');
            $table->foreign(['tenant_id', 'memo_submission_id'])->references(['tenant_id', 'id'])->on('memo_submissions')->cascadeOnDelete();
            $table->foreign(['memo_definition_id'])->references(['id'])->on('memo_definitions')->restrictOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memo_submission_revisions');
        Schema::dropIfExists('memo_submissions');
        Schema::dropIfExists('decision_submission_revisions');
        Schema::dropIfExists('decision_submissions');
        Schema::dropIfExists('memo_definitions');
        Schema::dropIfExists('decision_field_definitions');
        Schema::dropIfExists('decision_form_definitions');
    }
};
