<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consequence_definitions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('source_type');
            $table->string('target_type');
            $table->string('effect_type');
            $table->string('version');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['key', 'version']);
            $table->index(['source_type', 'target_type', 'effect_type'], 'conseq_defs_source_type_target_type_effect_idx');
        });

        Schema::create('consequence_links', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('source_section_simulation_week_id')->nullable();
            $table->foreignId('target_section_simulation_week_id')->nullable();
            $table->foreignId('consequence_definition_id')->constrained()->restrictOnDelete();
            $table->string('definition_key');
            $table->string('definition_version');
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('effect_type');
            $table->text('explanation');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'team_simulation_id', 'source_type', 'source_id'], 'conseq_links_tenant_team_sim_source_type_source_idx');
            $table->index(['tenant_id', 'team_simulation_id', 'target_type', 'target_id'], 'conseq_links_tenant_team_sim_target_type_target_idx');
            $table->index(['tenant_id', 'source_section_simulation_week_id', 'target_section_simulation_week_id'], 'conseq_links_tenant_source_week_target_week_idx');
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'])->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'source_section_simulation_week_id'], 'conseq_links_tenant_source_week_fk')->references(['tenant_id', 'id'])->on('section_simulation_weeks')->nullOnDelete();
            $table->foreign(['tenant_id', 'target_section_simulation_week_id'], 'conseq_links_tenant_target_week_fk')->references(['tenant_id', 'id'])->on('section_simulation_weeks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consequence_links');
        Schema::dropIfExists('consequence_definitions');
    }
};
