<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_content_activations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('simulation_version_id');
            $table->foreignId('simulation_week_id');
            $table->foreignId('simulation_content_package_id');
            $table->string('package_type');
            $table->string('package_version');
            $table->string('status');
            $table->timestamp('activated_at');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->index(['simulation_week_id', 'package_type', 'status'], 'content_activation_week_status_index');
            $table->unique('simulation_content_package_id', 'content_activations_package_unique');
            $table->foreign(['simulation_version_id', 'simulation_week_id'], 'content_activations_version_week_fk')->references(['simulation_version_id', 'id'])->on('simulation_weeks')->restrictOnDelete();
            $table->foreign('simulation_content_package_id', 'content_activations_package_fk')->references('id')->on('simulation_content_packages')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_content_activations');
    }
};
