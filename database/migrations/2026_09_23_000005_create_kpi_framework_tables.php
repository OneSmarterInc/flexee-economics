<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_definitions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight', 8, 6);
            $table->string('calculation_source');
            $table->string('version');
            $table->string('status')->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['key', 'version']);
            $table->index(['status', 'version']);
        });

        Schema::create('kpi_snapshots', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_simulation_week_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('kpi_definition_id')->constrained()->restrictOnDelete();
            $table->foreignId('economic_resolution_id')->nullable();
            $table->string('status');
            $table->decimal('value', 16, 4)->nullable();
            $table->string('unit')->nullable();
            $table->unsignedTinyInteger('precision')->default(2);
            $table->string('calculation_version');
            $table->json('input_snapshot');
            $table->text('unavailable_reason')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'section_simulation_week_id', 'team_simulation_id'], 'kpi_snaps_tenant_runtime_week_team_sim_idx');
            $table->index(['tenant_id', 'kpi_definition_id', 'calculated_at']);
            $table->index(['tenant_id', 'economic_resolution_id', 'calculation_version'], 'kpi_snapshots_resolution_calculation_index');
            $table->foreign(['tenant_id', 'section_simulation_week_id'])->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'])->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'economic_resolution_id'])->references(['tenant_id', 'id'])->on('economic_resolutions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_snapshots');
        Schema::dropIfExists('kpi_definitions');
    }
};
