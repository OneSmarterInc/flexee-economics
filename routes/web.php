<?php

use App\Http\Controllers\Halden\AdminController;
use App\Http\Controllers\Halden\FacultyController;
use App\Http\Controllers\Halden\JoinController;
use App\Http\Controllers\Halden\OpeningController;
use App\Http\Controllers\Halden\PlayController;
use App\Http\Controllers\Halden\RosterController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// The join link an instructor hands out; works signed out (make a login) or signed in (join the class).
Route::get('join/{code}', [JoinController::class, 'show'])->whereAlphaNumeric('code')->name('join.show');
Route::post('join/{code}', [JoinController::class, 'store'])->whereAlphaNumeric('code')->middleware('throttle:10,1')->name('join.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [PlayController::class, 'home'])->name('dashboard');

    Route::get('play', [PlayController::class, 'home'])->name('play.home');
    Route::get('play/{quarter}', [PlayController::class, 'show'])->name('play.show');
    Route::post('play/{quarter}/page/{page}', [PlayController::class, 'savePage'])->name('play.page');
    Route::post('play/{quarter}/memo', [PlayController::class, 'saveMemo'])->name('play.memo');
    Route::post('play/{quarter}/defense', [PlayController::class, 'saveDefense'])->name('play.defense');
    Route::post('play/{quarter}/ready', [PlayController::class, 'toggleReady'])->name('play.ready');
    Route::post('play/{quarter}/meeting', [PlayController::class, 'meet'])->middleware('throttle:20,1')->name('play.meet');
    Route::post('play/{quarter}/advisors/{advisor}', [PlayController::class, 'ask'])->whereAlpha('advisor')->middleware('throttle:20,1')->name('play.ask');
    Route::get('files/{path}', [PlayController::class, 'exhibit'])->where('path', '[A-Za-z0-9_\-/\.]+')->name('exhibits.show');

    Route::post('help', [PlayController::class, 'help'])->middleware('throttle:10,1')->name('help');
    Route::get('opening', [OpeningController::class, 'show'])->name('opening');
    Route::post('opening', [OpeningController::class, 'finish'])->name('opening.finish');

    Route::get('admin', [AdminController::class, 'index'])->name('admin.index');
    Route::post('admin/classes', [AdminController::class, 'storeClass'])->name('admin.classes.store');
    Route::get('admin/classes/{section}', [AdminController::class, 'showClass'])->name('admin.class');
    Route::post('admin/classes/{section}', [AdminController::class, 'updateClass'])->name('admin.class.update');
    Route::delete('admin/classes/{section}', [AdminController::class, 'destroyClass'])->name('admin.class.delete');
    Route::post('admin/instructors', [AdminController::class, 'storeInstructor'])->name('admin.instructors.store');

    Route::get('faculty', [FacultyController::class, 'board'])->name('faculty.board');
    Route::get('faculty/roster', [RosterController::class, 'show'])->name('faculty.roster');
    Route::post('faculty/roster/students', [RosterController::class, 'add'])->name('faculty.roster.add');
    Route::post('faculty/roster/students/{user}/{action}', [RosterController::class, 'student'])->whereIn('action', ['move', 'block', 'unblock', 'remove', 'reset-password'])->name('faculty.roster.student');
    Route::post('faculty/roster/join-link', [RosterController::class, 'joinLink'])->name('faculty.roster.join');
    Route::post('faculty/roster/form-teams', [RosterController::class, 'formTeams'])->name('faculty.roster.form');
    Route::post('faculty/roster/teams', [RosterController::class, 'newTeam'])->name('faculty.roster.team.new');
    Route::post('faculty/roster/teams/{team}', [RosterController::class, 'renameTeam'])->name('faculty.roster.team.rename');
    Route::delete('faculty/roster/teams/{team}', [RosterController::class, 'deleteTeam'])->name('faculty.roster.team.delete');
    Route::post('faculty/quarters/{quarter}/{action}', [FacultyController::class, 'action'])->whereIn('action', ['open', 'close', 'publish', 'extend'])->name('faculty.quarter');
    Route::post('faculty/quarters/{quarter}/draws', [FacultyController::class, 'setDraw'])->name('faculty.draw');
    Route::get('faculty/teams/{team}/quarters/{quarter}', [FacultyController::class, 'viewTeam'])->name('faculty.team');
    Route::get('faculty/teams/{team}/quarters/{quarter}/feedback', [FacultyController::class, 'feedback'])->name('faculty.feedback');
    Route::post('faculty/teams/{team}/quarters/{quarter}/feedback/draft', [FacultyController::class, 'draftFeedback'])->middleware('throttle:10,1')->name('faculty.feedback.draft');
    Route::post('faculty/teams/{team}/quarters/{quarter}/feedback', [FacultyController::class, 'saveFeedback'])->name('faculty.feedback.save');
    Route::post('faculty/teams/{team}/quarters/{quarter}/verdict', [FacultyController::class, 'saveVerdict'])->name('faculty.verdict');
});

require __DIR__.'/settings.php';
