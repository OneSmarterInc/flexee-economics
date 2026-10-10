<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Co-instructors: a class keeps its lead instructor (sections.faculty_user_id) and can have other instructors
 * who see and run the same board.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_instructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['section_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_instructors');
    }
};
