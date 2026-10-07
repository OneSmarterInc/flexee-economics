<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_seat_assignment_periods', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('user_id');
            $table->foreignId('seat_id')->constrained()->restrictOnDelete();
            $table->string('role_phase')->nullable();
            $table->unsignedSmallInteger('effective_from_week_number')->nullable();
            $table->unsignedSmallInteger('effective_until_week_number')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->string('source')->default('manual');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'team_simulation_id', 'user_id', 'effective_from_week_number'], 'seat_period_user_week_unique');
            $table->index(['tenant_id', 'team_simulation_id', 'user_id', 'effective_from_week_number'], 'seat_period_user_week_index');
            $table->index(['tenant_id', 'team_simulation_id', 'seat_id', 'effective_from_week_number'], 'seat_period_seat_week_index');
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'], 'seat_periods_tenant_team_sim_team_fk')->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_id', 'user_id'], 'seat_periods_tenant_team_user_fk')->references(['tenant_id', 'team_id', 'user_id'])->on('team_members')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_seat_assignment_periods');
    }
};
