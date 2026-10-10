<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A student belongs to a class (an enrolment) before and apart from being on a team, so the roster can hold
 * students who are not yet placed, and an instructor can pause a student's access. Classes get a join code
 * for the self-registration link. Existing team members are enrolled as they stand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('active'); // active | blocked
            $table->timestamps();
            $table->unique(['section_id', 'user_id']);
        });
        Schema::table('sections', function (Blueprint $table) {
            $table->string('join_code', 16)->nullable()->unique()->after('advisors_enabled');
        });

        $now = now();
        $rows = DB::table('team_members')
            ->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->select('teams.section_id', 'team_members.user_id')
            ->distinct()
            ->get();
        foreach ($rows->chunk(200) as $chunk) {
            DB::table('enrolments')->insertOrIgnore($chunk->map(fn ($r) => [
                'section_id' => $r->section_id, 'user_id' => $r->user_id, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
            ])->all());
        }
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropUnique(['join_code']);
            $table->dropColumn('join_code');
        });
        Schema::dropIfExists('enrolments');
    }
};
