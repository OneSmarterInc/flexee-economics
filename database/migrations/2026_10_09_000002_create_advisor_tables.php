<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One conversation per team, advisor and quarter. The whole team shares it.
        Schema::create('advisor_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quarter_id')->constrained()->cascadeOnDelete();
            $table->string('advisor', 40);
            $table->timestamps();
            $table->unique(['team_id', 'quarter_id', 'advisor']);
        });

        Schema::create('advisor_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisor_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 12);            // student or advisor
            $table->longText('body');
            $table->string('status', 12)->default('shown'); // shown, or dropped (advisor replies that failed a check)
            $table->string('dropped_reason')->nullable();  // shown to faculty only
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->string('model', 80)->nullable();
            $table->timestamps();
            $table->index(['advisor_thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisor_messages');
        Schema::dropIfExists('advisor_threads');
    }
};
