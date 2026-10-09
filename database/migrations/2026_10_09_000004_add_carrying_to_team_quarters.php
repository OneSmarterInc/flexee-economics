<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "What you're carrying": written once when the quarter opens. A draft that fails a check is kept
        // for faculty with the reason, and students see nothing.
        Schema::table('team_quarters', function (Blueprint $table) {
            $table->text('carrying')->nullable();
            $table->string('carrying_status', 12)->nullable();
            $table->string('carrying_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('team_quarters', function (Blueprint $table) {
            $table->dropColumn(['carrying', 'carrying_status', 'carrying_reason']);
        });
    }
};
