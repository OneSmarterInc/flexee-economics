<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A meeting is one more thread per team and quarter (advisor = "meeting") with several advisors in it:
        // each reply records who spoke, and each question records who was in the room.
        Schema::table('advisor_messages', function (Blueprint $table) {
            $table->string('advisor', 40)->nullable()->after('role');
            $table->json('invited')->nullable()->after('advisor');
        });
    }

    public function down(): void
    {
        Schema::table('advisor_messages', function (Blueprint $table) {
            $table->dropColumn(['advisor', 'invited']);
        });
    }
};
