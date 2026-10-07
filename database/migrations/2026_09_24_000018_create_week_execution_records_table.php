<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('week_execution_records', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_simulation_week_id');
            $table->foreignId('simulation_week_id');
            $table->string('execution_version');
            $table->string('status');
            $table->json('steps');
            $table->json('outputs')->nullable();
            $table->text('failure_message')->nullable();
            $table->foreignId('started_by_user_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'section_simulation_week_id', 'execution_version'], 'week_execution_unique');
            $table->foreign(['tenant_id', 'section_simulation_week_id'], 'week_execs_tenant_runtime_week_fk')->references(['tenant_id', 'id'])->on('section_simulation_weeks')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'section_simulation_id'])->references(['tenant_id', 'id'])->on('section_simulations')->cascadeOnDelete();
            $table->foreign('simulation_week_id')->references('id')->on('simulation_weeks')->restrictOnDelete();
            $table->foreign('started_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('week_execution_records');
    }
};
