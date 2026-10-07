<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('week11_economic_evaluations', function (Blueprint $table) {
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
            $table->decimal('realized_price', 14, 6)->nullable();
            $table->decimal('profit_oil', 14, 6)->nullable();
            $table->decimal('annual_mbbl', 14, 6)->nullable();
            $table->decimal('annuity_factor', 14, 6)->nullable();
            $table->decimal('margin_current', 14, 6)->nullable();
            $table->decimal('pv_stay_current_musd', 16, 6)->nullable();
            $table->decimal('margin_mid', 14, 6)->nullable();
            $table->decimal('pv_stay_mid_musd', 16, 6)->nullable();
            $table->decimal('margin_demanded', 14, 6)->nullable();
            $table->decimal('pv_stay_demanded_musd', 16, 6)->nullable();
            $table->decimal('margin_harsh', 14, 6)->nullable();
            $table->decimal('pv_stay_harsh_musd', 16, 6)->nullable();
            $table->decimal('exit_value_musd', 16, 6)->nullable();
            $table->decimal('stay_minus_exit_demanded_musd', 16, 6)->nullable();
            $table->decimal('indifference_take', 12, 6)->nullable();
            $table->decimal('comparables_min', 12, 6)->nullable();
            $table->decimal('comparables_max', 12, 6)->nullable();
            $table->decimal('demanded_take', 12, 6)->nullable();
            $table->boolean('demanded_take_inside_comparables')->nullable();
            $table->boolean('staying_beats_exit_across_take_grid')->nullable();
            $table->boolean('stay_value_falls_as_take_rises')->nullable();
            $table->boolean('sunk_invariant')->nullable();
            $table->json('take_results')->nullable();
            $table->json('worked_example_snapshot')->nullable();
            $table->json('input_snapshot');
            $table->json('output_snapshot');
            $table->text('unavailable_reason')->nullable();
            $table->foreignId('evaluated_by_user_id')->nullable();
            $table->string('evaluated_by_process');
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'decision_submission_id', 'engine_identifier'], 'week11_evaluation_unique');
            $table->index(['tenant_id', 'engine_identifier', 'engine_version'], 'w11_evals_tenant_engine_engine_ver_idx');
            $table->foreign(['tenant_id', 'section_simulation_week_id'], 'w11_evals_tenant_runtime_week_fk')->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'], 'w11_evals_tenant_team_sim_team_fk')->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'decision_submission_id'], 'w11_evals_tenant_decision_sub_fk')->references(['tenant_id', 'id'])->on('decision_submissions')->cascadeOnDelete();
            $table->foreign('evaluated_by_user_id', 'w11_evals_tenant_evaluated_by_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('week11_economic_evaluations');
    }
};
