<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohort_response_functions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version');
            $table->unsignedSmallInteger('source_week_number');
            $table->unsignedSmallInteger('target_week_number');
            $table->json('input_definition');
            $table->json('output_definition');
            $table->json('bounds')->nullable();
            $table->json('parameters');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['key', 'version']);
        });

        Schema::create('cohort_decision_aggregates', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('source_section_simulation_week_id');
            $table->foreignId('target_section_simulation_week_id');
            $table->foreignId('cohort_response_function_id');
            $table->string('function_key');
            $table->string('function_version');
            $table->json('individual_decisions_snapshot');
            $table->json('aggregate_snapshot');
            $table->json('response_snapshot');
            $table->foreignId('calculated_by_user_id')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'section_simulation_id', 'source_section_simulation_week_id', 'cohort_response_function_id'], 'cohort_aggregate_unique');
            $table->foreign(['tenant_id', 'source_section_simulation_week_id'])->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'target_section_simulation_week_id'])->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'calculated_by_user_id'])->references(['tenant_id', 'id'])->on('users')->nullOnDelete();
            $table->foreign('cohort_response_function_id')->references('id')->on('cohort_response_functions')->cascadeOnDelete();
        });

        Schema::create('cohort_feedback_effects', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('source_section_simulation_week_id');
            $table->foreignId('target_section_simulation_week_id');
            $table->foreignId('cohort_decision_aggregate_id');
            $table->foreignId('cohort_response_function_id');
            $table->string('effect_key');
            $table->string('effect_version');
            $table->json('effect_snapshot');
            $table->timestamp('revealed_at')->nullable();
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'cohort_decision_aggregate_id']);
            $table->index(['tenant_id', 'target_section_simulation_week_id']);
            $table->foreign(['tenant_id', 'source_section_simulation_week_id'])->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'target_section_simulation_week_id'])->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'cohort_decision_aggregate_id'])->references(['tenant_id', 'id'])->on('cohort_decision_aggregates')->cascadeOnDelete();
            $table->foreign('cohort_response_function_id')->references('id')->on('cohort_response_functions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cohort_feedback_effects');
        Schema::dropIfExists('cohort_decision_aggregates');
        Schema::dropIfExists('cohort_response_functions');
    }
};
