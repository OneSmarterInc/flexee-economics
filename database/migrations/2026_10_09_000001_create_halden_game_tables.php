<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('opening_seen_at')->nullable();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('course_name');
            $table->unsignedTinyInteger('weeks')->default(14); // 14 or 7
            $table->foreignId('faculty_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('first_meeting', 16)->nullable();        // marcus | ingrid
            $table->string('strategy_become', 300)->nullable();
            $table->string('strategy_by', 300)->nullable();
            $table->timestamps();
            $table->unique(['section_id', 'name']);
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('seat', 16); // evp | oil_fields | refineries | gas_stations | trading_finance
            $table->timestamps();
            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('quarters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number'); // 1..14 in the course
            $table->string('company_quarter', 8); // e.g. 2027Q1
            $table->string('status', 12)->default('upcoming'); // upcoming | open | closed | published
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['section_id', 'number']);
        });

        Schema::create('team_quarters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quarter_id')->constrained()->cascadeOnDelete();
            $table->json('decisions')->nullable();        // what the team saved this quarter, by page
            $table->json('effective_decisions')->nullable(); // what actually ran (after carry-forward)
            $table->json('saved_pages')->nullable();      // page => {by, at}
            $table->text('memo')->nullable();
            $table->foreignId('memo_saved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('memo_saved_at')->nullable();
            $table->foreignId('ready_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_at')->nullable();
            $table->json('results')->nullable();          // every metric from the engine
            $table->json('state_after')->nullable();      // company state carried to next quarter
            $table->decimal('score', 8, 4)->nullable();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'quarter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_quarters');
        Schema::dropIfExists('quarters');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('sections');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('opening_seen_at');
        });
    }
};
