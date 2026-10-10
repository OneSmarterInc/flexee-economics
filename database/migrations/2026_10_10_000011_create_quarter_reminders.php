<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Which deadline reminders (24 hours, 4 hours, 1 hour) have gone out for a quarter, so each goes once. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quarter_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quarter_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8); // 24h | 4h | 1h
            $table->unsignedSmallInteger('sent')->default(0);
            $table->timestamps();
            $table->unique(['quarter_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarter_reminders');
    }
};
