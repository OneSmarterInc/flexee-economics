<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('week8_economic_evaluations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_simulation_week_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('decision_submission_id');
            $table->string('engine_identifier');
            $table->string('engine_version');
            $table->string('package_version')->nullable();
            $table->string('status');
            $table->decimal('expected_wti', 10, 2)->nullable();
            $table->decimal('expected_upstream_impact_per_bbl', 10, 2)->nullable();
            $table->decimal('expected_refining_crack', 10, 2)->nullable();
            $table->decimal('prediction_expected_wti', 10, 2)->nullable();
            $table->decimal('prediction_expected_upstream_impact_per_bbl', 10, 2)->nullable();
            $table->decimal('prediction_expected_refining_crack', 10, 2)->nullable();
            $table->string('realized_scenario_key')->nullable();
            $table->decimal('realized_wti', 10, 2)->nullable();
            $table->decimal('realized_upstream_impact_per_bbl', 10, 2)->nullable();
            $table->decimal('realized_refining_crack', 10, 2)->nullable();
            $table->decimal('realized_retail_volume_percent', 8, 3)->nullable();
            $table->json('prediction_snapshot')->nullable();
            $table->json('realization_snapshot')->nullable();
            $table->json('input_snapshot');
            $table->json('output_snapshot');
            $table->text('unavailable_reason')->nullable();
            $table->foreignId('evaluated_by_user_id')->nullable();
            $table->string('evaluated_by_process');
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'decision_submission_id', 'engine_identifier'], 'week8_evaluation_unique');
            $table->index(['tenant_id', 'engine_identifier', 'engine_version']);
            $table->foreign(['tenant_id', 'section_simulation_week_id'])->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'])->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'decision_submission_id'])->references(['tenant_id', 'id'])->on('decision_submissions')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'evaluated_by_user_id'])->references(['tenant_id', 'id'])->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('week8_economic_evaluations');
    }
};
