<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The admin's class settings: how many students the class may hold, and whether the advisors answer.
        Schema::table('sections', function (Blueprint $table) {
            $table->unsignedSmallInteger('seats')->nullable()->after('weeks');
            $table->boolean('advisors_enabled')->default(true)->after('seats');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn(['seats', 'advisors_enabled']);
        });
    }
};
