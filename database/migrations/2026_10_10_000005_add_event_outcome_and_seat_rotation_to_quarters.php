<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quarters', function (Blueprint $table) {
            $table->string('event_outcome', 16)->nullable()->after('status');   // e.g. the OPEC+ outcome, drawn at the close
            $table->timestamp('seats_rotated_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('quarters', function (Blueprint $table) {
            $table->dropColumn(['event_outcome', 'seats_rotated_at']);
        });
    }
};
