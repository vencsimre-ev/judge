<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ScoreSheetController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/score-sheets', [ScoreSheetController::class, 'index'])->name('score-sheets.index');
    Route::get('/score-sheets/create', [ScoreSheetController::class, 'create'])->name('score-sheets.create');
    Route::post('/score-sheets', [ScoreSheetController::class, 'store'])->name('score-sheets.store');
    Route::get('/score-sheets/{scoreSheet}', [ScoreSheetController::class, 'show'])->name('score-sheets.show');
    Route::get('/score-sheets/{scoreSheet}/edit', [ScoreSheetController::class, 'edit'])->name('score-sheets.edit');
    Route::put('/score-sheets/{scoreSheet}', [ScoreSheetController::class, 'update'])->name('score-sheets.update');
    Route::get('/score-sheets/{scoreSheet}/export/json', [ScoreSheetController::class, 'exportJson'])->name('score-sheets.export.json');
    Route::get('/score-sheets/{scoreSheet}/export/csv', [ScoreSheetController::class, 'exportCsv'])->name('score-sheets.export.csv');
});
