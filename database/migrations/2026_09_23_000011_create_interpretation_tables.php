<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interpretation_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_simulation_week_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('requested_by_user_id');
            $table->string('focus');
            $table->string('context_version');
            $table->char('context_hash', 64);
            $table->string('prompt_version');
            $table->json('context_snapshot');
            $table->timestamp('requested_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'team_simulation_id', 'section_simulation_week_id'], 'interp_reqs_tenant_team_sim_runtime_week_idx');
            $table->foreign(['tenant_id', 'section_simulation_week_id'], 'interp_reqs_tenant_runtime_week_fk')->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'], 'interp_reqs_tenant_team_sim_team_fk')->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'requested_by_user_id'])->references(['tenant_id', 'id'])->on('users')->cascadeOnDelete();
        });

        Schema::create('interpretation_results', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interpretation_request_id');
            $table->string('provider');
            $table->string('model')->nullable();
            $table->string('prompt_version');
            $table->char('context_hash', 64);
            $table->longText('response');
            $table->json('response_snapshot');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'interpretation_request_id'], 'interp_results_tenant_interp_req_uniq');
            $table->foreign(['tenant_id', 'interpretation_request_id'], 'interp_results_tenant_interp_req_fk')->references(['tenant_id', 'id'])->on('interpretation_requests')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interpretation_results');
        Schema::dropIfExists('interpretation_requests');
    }
};
