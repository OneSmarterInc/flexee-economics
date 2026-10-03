<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decision_submissions', function (Blueprint $table): void {
            $table->json('definition_snapshot')->nullable()->after('answers');
        });

        Schema::table('decision_submission_revisions', function (Blueprint $table): void {
            $table->json('definition_snapshot')->nullable()->after('answers');
        });
    }

    public function down(): void
    {
        Schema::table('decision_submission_revisions', function (Blueprint $table): void {
            $table->dropColumn('definition_snapshot');
        });

        Schema::table('decision_submissions', function (Blueprint $table): void {
            $table->dropColumn('definition_snapshot');
        });
    }
};
