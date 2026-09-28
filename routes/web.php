<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Foundation\CourseController;
use App\Http\Controllers\Foundation\SectionController;
use App\Http\Controllers\Foundation\SectionSimulationController;
use App\Http\Controllers\Foundation\SectionSimulationWeekController;
use App\Http\Controllers\Foundation\TeamController;
use App\Http\Controllers\Student\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::view('foundation', 'foundation')->name('foundation.overview');
    Route::view('simulation-lifecycle', 'simulation-lifecycle')->name('simulation-lifecycle.overview');
    Route::view('faculty/dashboard', 'faculty-dashboard')->name('faculty.dashboard');
    Route::view('faculty/week-control', 'faculty-week-control')->name('faculty.week-control');
    Route::view('faculty/causal-trace', 'faculty-causal-trace')->name('faculty.causal-trace');
    Route::view('faculty/what-if', 'faculty-what-if-console')->name('faculty.what-if');
    Route::get('foundation/courses/{course}', [CourseController::class, 'show'])->name('foundation.courses.show');
    Route::patch('foundation/courses/{course}', [CourseController::class, 'update'])->name('foundation.courses.update');
    Route::get('foundation/sections/{section}', [SectionController::class, 'show'])->name('foundation.sections.show');
    Route::patch('foundation/sections/{section}', [SectionController::class, 'update'])->name('foundation.sections.update');
    Route::get('foundation/teams/{team}', [TeamController::class, 'show'])->name('foundation.teams.show');
    Route::patch('foundation/teams/{team}', [TeamController::class, 'update'])->name('foundation.teams.update');
    Route::get('foundation/section-simulations/{sectionSimulation}', [SectionSimulationController::class, 'show'])->name('foundation.section-simulations.show');
    Route::get('foundation/section-simulation-weeks/{sectionSimulationWeek}', [SectionSimulationWeekController::class, 'show'])->name('foundation.section-simulation-weeks.show');
    Route::post('foundation/section-simulation-weeks/{sectionSimulationWeek}/transition', [SectionSimulationWeekController::class, 'transition'])->name('foundation.section-simulation-weeks.transition');

    Route::get('submissions/weeks/{sectionSimulationWeek}', [SubmissionController::class, 'show'])->name('student.submissions.show');
    Route::post('submissions/weeks/{sectionSimulationWeek}/decisions/draft', [SubmissionController::class, 'saveDecisionDraft'])->name('student.submissions.decisions.draft');
    Route::post('submissions/weeks/{sectionSimulationWeek}/decisions/submit', [SubmissionController::class, 'submitDecision'])->name('student.submissions.decisions.submit');
    Route::post('submissions/weeks/{sectionSimulationWeek}/capital-allocation/submit', [SubmissionController::class, 'submitCapitalAllocation'])->name('student.submissions.capital-allocation.submit');
    Route::post('submissions/weeks/{sectionSimulationWeek}/memo/draft', [SubmissionController::class, 'saveMemoDraft'])->name('student.submissions.memo.draft');
    Route::post('submissions/weeks/{sectionSimulationWeek}/memo/submit', [SubmissionController::class, 'submitMemo'])->name('student.submissions.memo.submit');
});

require __DIR__.'/settings.php';
