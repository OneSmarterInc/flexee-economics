<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Foundation\CourseController;
use App\Http\Controllers\Foundation\SectionController;
use App\Http\Controllers\Foundation\TeamController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::view('foundation', 'foundation')->name('foundation.overview');
    Route::get('foundation/courses/{course}', [CourseController::class, 'show'])->name('foundation.courses.show');
    Route::patch('foundation/courses/{course}', [CourseController::class, 'update'])->name('foundation.courses.update');
    Route::get('foundation/sections/{section}', [SectionController::class, 'show'])->name('foundation.sections.show');
    Route::patch('foundation/sections/{section}', [SectionController::class, 'update'])->name('foundation.sections.update');
    Route::get('foundation/teams/{team}', [TeamController::class, 'show'])->name('foundation.teams.show');
    Route::patch('foundation/teams/{team}', [TeamController::class, 'update'])->name('foundation.teams.update');
});

require __DIR__.'/settings.php';
