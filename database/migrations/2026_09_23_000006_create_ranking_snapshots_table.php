<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranking_snapshots', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_simulation_week_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->string('scope');
            $table->string('status');
            $table->decimal('composite_score', 16, 6)->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->string('ranking_version');
            $table->json('input_snapshot');
            $table->text('incomplete_reason')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'section_simulation_week_id', 'scope', 'calculated_at']);
            $table->index(['tenant_id', 'team_simulation_id', 'ranking_version']);
            $table->foreign(['tenant_id', 'section_simulation_week_id'])->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'])->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_snapshots');
    }
};
