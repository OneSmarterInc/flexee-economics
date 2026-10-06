<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('what_if_runs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_simulation_week_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('source_economic_resolution_id');
            $table->foreignId('requested_by_user_id')->nullable();
            $table->string('scenario_type');
            $table->boolean('counterfactual')->default(true);
            $table->json('scenario_inputs');
            $table->json('calculated_outputs');
            $table->string('engine_identifier');
            $table->string('engine_version');
            $table->timestamp('requested_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'source_economic_resolution_id', 'requested_at'], 'whatif_runs_tenant_source_resolution_requested_at_idx');
            $table->foreign(['tenant_id', 'section_simulation_week_id'])->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'])->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'source_economic_resolution_id'])->references(['tenant_id', 'id'])->on('economic_resolutions')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'requested_by_user_id'])->references(['tenant_id', 'id'])->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('what_if_runs');
    }
};
