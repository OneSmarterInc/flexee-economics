<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quarters', function (Blueprint $table) {
            $table->string('world', 24)->nullable()->after('event_outcome');   // the future the Q4 2029 portfolio is valued in, drawn when the board quarter opens ("mid:slow")
        });
        Schema::table('team_quarters', function (Blueprint $table) {
            $table->json('defense')->nullable()->after('memo_saved_at');        // the board defense: synthesis, decisions, counterfactual
            $table->foreignId('defense_saved_by')->nullable()->after('defense')->constrained('users')->nullOnDelete();
            $table->timestamp('defense_saved_at')->nullable()->after('defense_saved_by');
            $table->string('reasoning', 8)->nullable()->after('writing_score');  // the instructor's call: strong | weak
            $table->string('verdict', 16)->nullable()->after('reasoning');       // the board's ending: widen | conditions | split | sold
            $table->timestamp('verdict_published_at')->nullable()->after('verdict');
        });
    }

    public function down(): void
    {
        Schema::table('team_quarters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('defense_saved_by');
            $table->dropColumn(['defense', 'defense_saved_at', 'reasoning', 'verdict', 'verdict_published_at']);
        });
        Schema::table('quarters', function (Blueprint $table) {
            $table->dropColumn('world');
        });
    }
};
