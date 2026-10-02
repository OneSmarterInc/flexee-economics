<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unique(['tenant_id', 'section_id', 'id']);
        });

        Schema::create('simulations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['id', 'slug']);
        });

        Schema::create('simulation_variants', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('simulation_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->unsignedSmallInteger('duration_weeks');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['simulation_id', 'slug']);
            $table->unique(['simulation_id', 'id']);
        });

        Schema::create('simulation_versions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('simulation_id')->constrained()->restrictOnDelete();
            $table->foreignId('simulation_variant_id');
            $table->string('version');
            $table->string('status')->default('draft');
            $table->string('config_hash')->nullable();
            $table->json('configuration')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['simulation_variant_id', 'version']);
            $table->unique(['simulation_id', 'simulation_variant_id', 'id']);
            $table->unique(['simulation_variant_id', 'id']);
            $table->foreign(['simulation_id', 'simulation_variant_id'])->references(['simulation_id', 'id'])->on('simulation_variants')->restrictOnDelete();
        });

        Schema::create('simulation_weeks', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('simulation_id')->constrained()->restrictOnDelete();
            $table->foreignId('simulation_variant_id');
            $table->foreignId('simulation_version_id');
            $table->unsignedSmallInteger('week_number');
            $table->string('slug');
            $table->string('title');
            $table->string('pattern')->nullable();
            $table->string('content_key')->nullable();
            $table->json('content_metadata')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['simulation_version_id', 'week_number']);
            $table->unique(['simulation_version_id', 'slug']);
            $table->unique(['simulation_version_id', 'id']);
            $table->foreign(['simulation_id', 'simulation_variant_id', 'simulation_version_id'])->references(['simulation_id', 'simulation_variant_id', 'id'])->on('simulation_versions')->restrictOnDelete();
        });

        Schema::create('week_content_versions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('simulation_version_id');
            $table->foreignId('simulation_week_id');
            $table->string('version');
            $table->string('content_key')->nullable();
            $table->string('manifest_reference')->nullable();
            $table->string('config_hash')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('placeholder');
            $table->timestamps();

            $table->unique(['simulation_week_id', 'version']);
            $table->foreign(['simulation_version_id', 'simulation_week_id'])->references(['simulation_version_id', 'id'])->on('simulation_weeks')->restrictOnDelete();
        });

        Schema::create('section_simulations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id');
            $table->foreignId('simulation_id')->constrained()->restrictOnDelete();
            $table->foreignId('simulation_variant_id');
            $table->foreignId('simulation_version_id');
            $table->foreignId('created_by_user_id')->nullable();
            $table->string('name')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'id', 'section_id']);
            $table->unique(['tenant_id', 'id', 'simulation_version_id']);
            $table->index(['tenant_id', 'section_id', 'status']);
            $table->foreign(['tenant_id', 'section_id'])->references(['tenant_id', 'id'])->on('sections')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'created_by_user_id'])->references(['tenant_id', 'id'])->on('users')->nullOnDelete();
            $table->foreign(['simulation_id', 'simulation_variant_id', 'simulation_version_id'])->references(['simulation_id', 'simulation_variant_id', 'id'])->on('simulation_versions')->restrictOnDelete();
        });

        Schema::create('section_simulation_weeks', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('simulation_version_id');
            $table->foreignId('simulation_week_id');
            $table->string('status')->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'section_simulation_id', 'simulation_week_id']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status', 'closes_at']);
            $table->foreign(['tenant_id', 'section_simulation_id', 'simulation_version_id'])->references(['tenant_id', 'id', 'simulation_version_id'])->on('section_simulations')->cascadeOnDelete();
            $table->foreign(['simulation_version_id', 'simulation_week_id'])->references(['simulation_version_id', 'id'])->on('simulation_weeks')->restrictOnDelete();
        });

        Schema::create('team_simulations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_simulation_id');
            $table->foreignId('section_id');
            $table->foreignId('team_id');
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'section_simulation_id', 'team_id']);
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'id', 'team_id']);
            $table->foreign(['tenant_id', 'section_simulation_id', 'section_id'])->references(['tenant_id', 'id', 'section_id'])->on('section_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'section_id', 'team_id'])->references(['tenant_id', 'section_id', 'id'])->on('teams')->cascadeOnDelete();
        });

        Schema::create('simulation_seat_assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_simulation_id');
            $table->foreignId('team_id');
            $table->foreignId('user_id');
            $table->foreignId('seat_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'team_simulation_id', 'user_id']);
            $table->unique(['tenant_id', 'team_simulation_id', 'seat_id']);
            $table->foreign(['tenant_id', 'team_simulation_id', 'team_id'])->references(['tenant_id', 'id', 'team_id'])->on('team_simulations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'team_id', 'user_id'])->references(['tenant_id', 'team_id', 'user_id'])->on('team_members')->cascadeOnDelete();
        });

        Schema::create('generated_artifacts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('simulation_version_id');
            $table->foreignId('simulation_week_id')->nullable();
            $table->string('artifact_key');
            $table->string('artifact_type');
            $table->string('version')->nullable();
            $table->string('hash')->nullable();
            $table->string('storage_disk')->default('local');
            $table->string('storage_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['simulation_version_id', 'simulation_week_id', 'artifact_key', 'version']);
            $table->foreign(['simulation_version_id', 'simulation_week_id'])->references(['simulation_version_id', 'id'])->on('simulation_weeks')->restrictOnDelete();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable();
            $table->string('action');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['tenant_id', 'action', 'occurred_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->foreign(['tenant_id', 'actor_user_id'])->references(['tenant_id', 'id'])->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('generated_artifacts');
        Schema::dropIfExists('simulation_seat_assignments');
        Schema::dropIfExists('team_simulations');
        Schema::dropIfExists('section_simulation_weeks');
        Schema::dropIfExists('section_simulations');
        Schema::dropIfExists('week_content_versions');
        Schema::dropIfExists('simulation_weeks');
        Schema::dropIfExists('simulation_versions');
        Schema::dropIfExists('simulation_variants');
        Schema::dropIfExists('simulations');

        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'section_id', 'id']);
        });
    }
};
