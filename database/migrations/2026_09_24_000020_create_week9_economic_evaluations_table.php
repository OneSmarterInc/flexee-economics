<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('week9_economic_evaluations', function (Blueprint $table) {
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
            $table->string('nonfuel_state_key')->nullable();
            $table->json('selected_rebrand_markets')->nullable();
            $table->decimal('cost_per_site', 14, 6)->nullable();
            $table->decimal('partial_gain_musd', 14, 6)->nullable();
            $table->decimal('partial_cost_musd', 14, 6)->nullable();
            $table->decimal('partial_payback_years', 12, 6)->nullable();
            $table->decimal('full_net_gain_musd', 14, 6)->nullable();
            $table->decimal('pricewar_partial_payback_years', 12, 6)->nullable();
            $table->json('input_snapshot');
            $table->json('output_snapshot');
            $table->text('unavailable_reason')->nullable();
            $table->foreignId('evaluated_by_user_id')->nullable();
            $table->string('evaluated_by_process');
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'decision_submission_id', 'engine_identifier'], 'week9_evaluation_unique');
            $table->index(['tenant_id', 'engine_identifier', 'engine_version'], 'w9_evals_tenant_engine_engine_ver_idx');
            $table->foreign(['tenant_id', 'section_simulation_week_id'], 'w9_evals_tenant_runtime_week_fk')->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'], 'w9_evals_tenant_team_sim_team_fk')->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'decision_submission_id'], 'w9_evals_tenant_decision_sub_fk')->references(['tenant_id', 'id'])->on('decision_submissions')->cascadeOnDelete();
            $table->foreign('evaluated_by_user_id', 'w9_evals_tenant_evaluated_by_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('week9_economic_evaluations');
    }
};
