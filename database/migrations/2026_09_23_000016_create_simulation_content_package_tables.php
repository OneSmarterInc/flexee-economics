<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_content_packages', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('simulation_version_id');
            $table->foreignId('simulation_week_id');
            $table->string('package_type');
            $table->string('version');
            $table->json('manifest');
            $table->string('manifest_hash', 64);
            $table->string('status');
            $table->json('validation_summary')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['simulation_week_id', 'package_type', 'version'], 'simulation_content_package_unique');
            $table->foreign(['simulation_version_id', 'simulation_week_id'], 'content_packages_version_week_fk')->references(['simulation_version_id', 'id'])->on('simulation_weeks')->restrictOnDelete();
        });

        Schema::create('content_artifacts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('simulation_content_package_id')->constrained()->cascadeOnDelete();
            $table->string('artifact_key');
            $table->string('artifact_type');
            $table->string('visibility')->nullable();
            $table->string('path_reference');
            $table->string('checksum_algorithm')->default('sha256');
            $table->string('checksum', 64)->nullable();
            $table->string('actual_checksum', 64)->nullable();
            $table->string('version')->nullable();
            $table->boolean('is_missing')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['simulation_content_package_id', 'artifact_key', 'version'], 'content_artifact_unique');
            $table->index(['artifact_type', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_artifacts');
        Schema::dropIfExists('simulation_content_packages');
    }
};
