<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advisors', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('title');
            $table->text('perspective');
            $table->text('default_guidance');
            $table->string('content_version');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('advisor_consultation_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_simulation_week_id');
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('advisor_id')->constrained()->restrictOnDelete();
            $table->text('question');
            $table->json('context_snapshot')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'section_simulation_week_id', 'team_simulation_id', 'advisor_id'], 'advisor_consultation_one_advisor_per_week');
            $table->index(['tenant_id', 'section_simulation_week_id', 'team_simulation_id'], 'advisor_sessions_tenant_runtime_week_team_sim_idx');
            $table->foreign(['tenant_id', 'section_simulation_week_id'], 'advisor_sessions_tenant_runtime_week_fk')->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'], 'advisor_sessions_tenant_team_sim_team_fk')->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
        });

        Schema::create('advisor_responses', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('advisor_consultation_session_id');
            $table->foreignId('advisor_id')->constrained()->restrictOnDelete();
            $table->string('content_version');
            $table->text('response');
            $table->json('response_snapshot')->nullable();
            $table->timestamp('responded_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'advisor_consultation_session_id'], 'advisor_response_one_per_session');
            $table->foreign(['tenant_id', 'advisor_consultation_session_id'], 'advisor_responses_tenant_advisor_session_fk')->references(['tenant_id', 'id'])->on('advisor_consultation_sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisor_responses');
        Schema::dropIfExists('advisor_consultation_sessions');
        Schema::dropIfExists('advisors');
    }
};
