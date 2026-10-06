<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('week5_economic_evaluations', function (Blueprint $table) {
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
            $table->decimal('eur_change', 12, 6)->nullable();
            $table->decimal('nok_usd_value_change', 12, 6)->nullable();
            $table->decimal('sgd_usd_value_change', 12, 6)->nullable();
            $table->decimal('norway_benefit_musd', 14, 6)->nullable();
            $table->decimal('norway_lifting_post', 14, 6)->nullable();
            $table->decimal('euro_retail_translation_musd', 14, 6)->nullable();
            $table->decimal('existing_hedge_gain_musd', 14, 6)->nullable();
            $table->decimal('rot_net_eur_musd', 14, 6)->nullable();
            $table->decimal('rot_natural_hedge_ratio', 12, 6)->nullable();
            $table->decimal('rot_net_impact_musd', 14, 6)->nullable();
            $table->decimal('rot_overhedge_loss_musd', 14, 6)->nullable();
            $table->decimal('sing_impact_musd', 14, 6)->nullable();
            $table->json('decision_snapshot')->nullable();
            $table->json('input_snapshot');
            $table->json('output_snapshot');
            $table->text('unavailable_reason')->nullable();
            $table->foreignId('evaluated_by_user_id')->nullable();
            $table->string('evaluated_by_process');
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'decision_submission_id', 'engine_identifier'], 'week5_evaluation_unique');
            $table->index(['tenant_id', 'engine_identifier', 'engine_version'], 'w5_evals_tenant_engine_engine_ver_idx');
            $table->foreign(['tenant_id', 'section_simulation_week_id'], 'w5_evals_tenant_runtime_week_fk')->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'], 'w5_evals_tenant_team_sim_team_fk')->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'decision_submission_id'], 'w5_evals_tenant_decision_sub_fk')->references(['tenant_id', 'id'])->on('decision_submissions')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'evaluated_by_user_id'], 'w5_evals_tenant_evaluated_by_fk')->references(['tenant_id', 'id'])->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('week5_economic_evaluations');
    }
};
