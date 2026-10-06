<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_projects', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('key');
            $table->string('name');
            $table->string('category');
            $table->string('version');
            $table->string('cash_flow_reference')->nullable();
            $table->string('risk_class')->nullable();
            $table->json('required_inputs');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['key', 'version']);
        });

        Schema::create('capital_allocation_decisions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_simulation_week_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('discount_rate_consequence_id')->nullable();
            $table->foreignId('submitted_by_user_id');
            $table->json('selected_projects');
            $table->json('rejected_projects');
            $table->json('context_snapshot');
            $table->json('memo_references')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'section_simulation_week_id', 'team_simulation_id'], 'capital_allocation_team_week_unique');
            $table->foreign(['tenant_id', 'section_simulation_week_id'], 'capital_decisions_tenant_runtime_week_fk')->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'], 'capital_decisions_tenant_team_sim_team_fk')->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'discount_rate_consequence_id'], 'capital_decisions_tenant_discount_rate_fk')->references(['tenant_id', 'id'])->on('discount_rate_consequences')->nullOnDelete();
            $table->foreign(['tenant_id', 'submitted_by_user_id'], 'capital_decisions_tenant_submitted_by_fk')->references(['tenant_id', 'id'])->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_allocation_decisions');
        Schema::dropIfExists('capital_projects');
    }
};
