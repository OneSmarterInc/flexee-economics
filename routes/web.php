<?php

use App\Http\Controllers\Halden\FacultyController;
use App\Http\Controllers\Halden\OpeningController;
use App\Http\Controllers\Halden\PlayController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [PlayController::class, 'home'])->name('dashboard');

    Route::get('play', [PlayController::class, 'home'])->name('play.home');
    Route::get('play/{quarter}', [PlayController::class, 'show'])->name('play.show');
    Route::post('play/{quarter}/page/{page}', [PlayController::class, 'savePage'])->name('play.page');
    Route::post('play/{quarter}/memo', [PlayController::class, 'saveMemo'])->name('play.memo');
    Route::post('play/{quarter}/ready', [PlayController::class, 'toggleReady'])->name('play.ready');
    Route::post('play/{quarter}/advisors/{advisor}', [PlayController::class, 'ask'])->whereAlpha('advisor')->middleware('throttle:20,1')->name('play.ask');
    Route::get('files/{path}', [PlayController::class, 'exhibit'])->where('path', '[A-Za-z0-9_\-/\.]+')->name('exhibits.show');

    Route::get('opening', [OpeningController::class, 'show'])->name('opening');
    Route::post('opening', [OpeningController::class, 'finish'])->name('opening.finish');

    Route::get('faculty', [FacultyController::class, 'board'])->name('faculty.board');
    Route::post('faculty/quarters/{quarter}/{action}', [FacultyController::class, 'action'])->whereIn('action', ['open', 'close', 'publish', 'extend'])->name('faculty.quarter');
    Route::get('faculty/teams/{team}/quarters/{quarter}', [FacultyController::class, 'viewTeam'])->name('faculty.team');
});

require __DIR__.'/settings.php';
