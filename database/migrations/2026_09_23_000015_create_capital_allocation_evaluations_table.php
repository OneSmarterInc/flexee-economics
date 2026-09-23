<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_allocation_evaluations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_simulation_week_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('capital_allocation_decision_id');
            $table->string('engine_identifier');
            $table->string('engine_version');
            $table->string('status');
            $table->decimal('portfolio_npv_musd', 14, 3)->nullable();
            $table->decimal('portfolio_irr_percent', 8, 4)->nullable();
            $table->decimal('capital_required_musd', 12, 3)->nullable();
            $table->boolean('capital_envelope_feasible')->nullable();
            $table->json('input_snapshot');
            $table->json('output_snapshot');
            $table->text('unavailable_reason')->nullable();
            $table->foreignId('evaluated_by_user_id')->nullable();
            $table->string('evaluated_by_process');
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'capital_allocation_decision_id', 'engine_identifier'], 'capital_allocation_evaluation_unique');
            $table->index(['tenant_id', 'engine_identifier', 'engine_version']);
            $table->foreign(['tenant_id', 'section_simulation_week_id'])->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'])->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'capital_allocation_decision_id'])->references(['tenant_id', 'id'])->on('capital_allocation_decisions')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'evaluated_by_user_id'])->references(['tenant_id', 'id'])->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_allocation_evaluations');
    }
};
