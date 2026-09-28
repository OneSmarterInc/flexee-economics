<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('week12_economic_evaluations', function (Blueprint $table) {
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
            $table->string('package_identifier');
            $table->string('package_version')->nullable();
            $table->string('status');
            $table->json('selected_projects');
            $table->json('rejected_projects');
            $table->json('available_projects');
            $table->boolean('selected_portfolio_feasible')->nullable();
            $table->json('selected_constraint_failures')->nullable();
            $table->boolean('selected_includes_divestment')->nullable();
            $table->boolean('selected_unlocked_by_divestment')->nullable();
            $table->decimal('selected_capital_required_musd', 16, 6)->nullable();
            $table->decimal('selected_available_envelope_musd', 16, 6)->nullable();
            $table->decimal('discretionary_envelope_musd', 16, 6)->nullable();
            $table->decimal('envelope_with_divestment_musd', 16, 6)->nullable();
            $table->unsignedInteger('feasible_portfolio_count')->nullable();
            $table->unsignedInteger('feasible_with_helix_rotterdam_count')->nullable();
            $table->unsignedInteger('portfolios_unlocked_by_divestment_count')->nullable();
            $table->json('portfolio_results')->nullable();
            $table->json('worked_example_snapshot')->nullable();
            $table->json('input_snapshot');
            $table->json('output_snapshot');
            $table->text('unavailable_reason')->nullable();
            $table->foreignId('evaluated_by_user_id')->nullable();
            $table->string('evaluated_by_process');
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'decision_submission_id', 'engine_identifier'], 'week12_evaluation_unique');
            $table->index(['tenant_id', 'engine_identifier', 'engine_version']);
            $table->foreign(['tenant_id', 'section_simulation_week_id'])->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'])->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'decision_submission_id'])->references(['tenant_id', 'id'])->on('decision_submissions')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'evaluated_by_user_id'])->references(['tenant_id', 'id'])->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('week12_economic_evaluations');
    }
};
