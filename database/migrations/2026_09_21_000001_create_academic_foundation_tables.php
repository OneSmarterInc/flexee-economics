<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id');
            $table->string('code');
            $table->string('name');
            $table->string('term');
            $table->timestamps();

            $table->unique(['tenant_id', 'institution_id', 'code', 'term']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'institution_id'])->references(['tenant_id', 'id'])->on('institutions')->cascadeOnDelete();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id');
            $table->string('name');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->unique(['tenant_id', 'course_id', 'name']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'course_id', 'status']);
            $table->foreign(['tenant_id', 'course_id'])->references(['tenant_id', 'id'])->on('courses')->cascadeOnDelete();
        });

        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('content_ref')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('section_faculty', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id');
            $table->foreignId('user_id');
            $table->string('role')->default('instructor');
            $table->timestamps();

            $table->unique(['tenant_id', 'section_id', 'user_id']);
            $table->foreign(['tenant_id', 'section_id'])->references(['tenant_id', 'id'])->on('sections')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])->on('users')->cascadeOnDelete();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id');
            $table->foreignId('user_id');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'section_id', 'user_id']);
            $table->index(['tenant_id', 'user_id', 'status']);
            $table->foreign(['tenant_id', 'section_id'])->references(['tenant_id', 'id'])->on('sections')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])->on('users')->cascadeOnDelete();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id');
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['tenant_id', 'section_id', 'slug']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'section_id']);
            $table->foreign(['tenant_id', 'section_id'])->references(['tenant_id', 'id'])->on('sections')->cascadeOnDelete();
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id');
            $table->foreignId('user_id');
            $table->foreignId('seat_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'team_id', 'user_id']);
            $table->unique(['tenant_id', 'team_id', 'seat_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->foreign(['tenant_id', 'team_id'])->references(['tenant_id', 'id'])->on('teams')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('section_faculty');
        Schema::dropIfExists('seats');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('institutions');
    }
};
