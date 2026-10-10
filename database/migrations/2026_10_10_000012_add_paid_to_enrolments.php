<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment as a mark, not a flow (D30): a class can require each student to be marked paid before they play, and
 * the instructor marks them from the roster. No money moves through Halden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->boolean('requires_payment')->default(false)->after('advisors_enabled');
        });
        Schema::table('enrolments', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('requires_payment');
        });
    }
};
