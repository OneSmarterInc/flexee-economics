<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counterparties', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('standing_states', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('counterparty_id')->constrained()->restrictOnDelete();
            $table->string('state');
            $table->text('reason');
            $table->timestamp('state_changed_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'team_simulation_id', 'counterparty_id'], 'standing_states_tenant_team_sim_counterparty_uniq');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'section_simulation_id', 'counterparty_id'], 'standing_states_tenant_section_sim_counterparty_idx');
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'])->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
        });

        Schema::create('standing_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('standing_state_id');
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_simulation_week_id')->nullable();
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('counterparty_id');
            $table->string('old_state')->nullable();
            $table->string('new_state');
            $table->text('reason');
            $table->string('trigger_type')->nullable();
            $table->unsignedBigInteger('trigger_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'team_simulation_id', 'counterparty_id', 'occurred_at'], 'standing_events_tenant_team_sim_counterparty_occurred_at_idx');
            $table->index(['trigger_type', 'trigger_id']);
            $table->foreign(['tenant_id', 'standing_state_id'])->references(['tenant_id', 'id'])->on('standing_states')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'section_simulation_week_id'])->references(['tenant_id', 'id'])->on('section_simulation_weeks')->nullOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'])->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign('counterparty_id')->references('id')->on('counterparties')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standing_events');
        Schema::dropIfExists('standing_states');
        Schema::dropIfExists('counterparties');
    }
};
