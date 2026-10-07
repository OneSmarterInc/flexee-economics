<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_rate_schedules', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version');
            $table->unsignedSmallInteger('source_week_number');
            $table->unsignedSmallInteger('target_week_number');
            $table->json('classification_rules')->nullable();
            $table->json('classification_outcomes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['key', 'version']);
        });

        Schema::create('discount_rate_consequences', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('source_section_simulation_week_id');
            $table->foreignId('target_section_simulation_week_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('economic_resolution_id');
            $table->foreignId('discount_rate_schedule_id');
            $table->string('schedule_key');
            $table->string('schedule_version');
            $table->string('status');
            $table->string('classification')->nullable();
            $table->decimal('discount_rate_percent', 6, 3)->nullable();
            $table->decimal('capital_envelope_musd', 12, 3)->nullable();
            $table->json('input_snapshot');
            $table->json('result_snapshot');
            $table->foreignId('resolved_by_user_id')->nullable();
            $table->timestamp('resolved_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'economic_resolution_id', 'discount_rate_schedule_id'], 'discount_rate_consequence_unique');
            $table->foreign(['tenant_id', 'source_section_simulation_week_id'], 'discount_rates_tenant_source_week_fk')->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'target_section_simulation_week_id'], 'discount_rates_tenant_target_week_fk')->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'], 'discount_rates_tenant_team_sim_team_fk')->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'economic_resolution_id'], 'discount_rates_tenant_econresoid_fk')->references(['tenant_id', 'id'])->on('economic_resolutions')->cascadeOnDelete();
            $table->foreign('resolved_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('discount_rate_schedule_id')->references('id')->on('discount_rate_schedules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_rate_consequences');
        Schema::dropIfExists('discount_rate_schedules');
    }
};
