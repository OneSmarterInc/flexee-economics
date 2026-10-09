<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI drafts for the instructor. Kept even when a check drops them, so faculty can see why.
        Schema::create('faculty_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_quarter_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);                 // feedback, writing or mismatch
            $table->longText('text')->nullable();
            $table->json('data')->nullable();           // score and reason, or mismatch and note
            $table->string('status', 12);               // ok or dropped
            $table->string('dropped_reason')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->string('model', 80)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['team_quarter_id', 'kind']);
        });

        Schema::table('team_quarters', function (Blueprint $table) {
            $table->longText('feedback')->nullable();
            $table->timestamp('feedback_published_at')->nullable();
            $table->unsignedTinyInteger('writing_score')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('team_quarters', function (Blueprint $table) {
            $table->dropColumn(['feedback', 'feedback_published_at', 'writing_score']);
        });
        Schema::dropIfExists('faculty_drafts');
    }
};
