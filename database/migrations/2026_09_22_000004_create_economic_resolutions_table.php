<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_resolutions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_simulation_week_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('decision_submission_id');
            $table->string('economic_engine');
            $table->string('engine_version');
            $table->json('input_snapshot');
            $table->json('output_snapshot');
            $table->decimal('transfer_price', 12, 3);
            $table->decimal('integrated_margin', 12, 3);
            $table->decimal('upstream_margin', 12, 3);
            $table->decimal('refining_margin', 12, 3);
            $table->decimal('upstream_vs_target', 12, 3);
            $table->decimal('refining_vs_target', 12, 3);
            $table->decimal('geneva_gap', 12, 3);
            $table->decimal('geneva_capture_per_bbl', 12, 3);
            $table->decimal('geneva_max_volume_bbl_day', 14, 3);
            $table->foreignId('resolved_by_user_id')->nullable();
            $table->string('resolved_by_process')->nullable();
            $table->timestamp('resolved_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'section_simulation_week_id', 'team_simulation_id'], 'economic_resolutions_team_week_unique');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'economic_engine', 'engine_version']);
            $table->foreign(['tenant_id', 'section_simulation_week_id'])->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'])->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'decision_submission_id'])->references(['tenant_id', 'id'])->on('decision_submissions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'resolved_by_user_id'])->references(['tenant_id', 'id'])->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_resolutions');
    }
};
