<?php

use App\Http\Controllers\PlayerController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\Api\AdminApiController;
use Illuminate\Support\Facades\Route;

// ── Player flow ───────────────────────────────────────────────────────────────
Route::get('/', [PlayerController::class, 'register'])->name('player.register');
Route::post('/register', [PlayerController::class, 'store'])->name('player.store');
Route::get('/play', [PlayerController::class, 'play'])->name('player.play');

// ── Main display screen (projector / TV) ──────────────────────────────────────
Route::get('/screen', [ScreenController::class, 'index'])->name('screen.index');

// ── Admin auth (public) ───────────────────────────────────────────────────────
Route::get('/admin/login',  [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1')->name('admin.login.submit');
Route::post('/admin/logout',[AdminAuthController::class, 'logout'])->name('admin.logout');

// ── Admin panel + API — all require admin session ────────────────────────────
Route::middleware('admin.auth')->group(function () {

    // Web panel
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');

    // Admin API (moved from api.php so session middleware is available)
    Route::prefix('api/admin')->group(function () {
        Route::get('/audits',                               [AdminApiController::class, 'listAudits']);
        Route::get('/players',                              [AdminApiController::class, 'listPlayers']);
        Route::get('/players/{player}',                     [AdminApiController::class, 'showPlayer']);
        Route::get('/match-config',                         [AdminApiController::class, 'showMatchConfig']);
        Route::put('/match-config',                         [AdminApiController::class, 'updateMatchConfig']);
        Route::get('/teams',                                [AdminApiController::class, 'listTeams']);
        Route::post('/teams',                               [AdminApiController::class, 'storeTeam']);
        Route::put('/teams/{team}',                         [AdminApiController::class, 'updateTeam']);
        Route::delete('/teams/{team}',                      [AdminApiController::class, 'destroyTeam']);
        Route::post('/teams/{team}/players',                [AdminApiController::class, 'storeSportsPlayer']);
        Route::put('/sports-players/{sportsPlayer}',        [AdminApiController::class, 'updateSportsPlayer']);
        Route::delete('/sports-players/{sportsPlayer}',     [AdminApiController::class, 'destroySportsPlayer']);
        Route::post('/testing/simulate',                    [AdminApiController::class, 'simulatePlayers']);
        Route::get('/testing/status',                       [AdminApiController::class, 'testingStatus']);
        Route::get('/testing/scoring-rehearsal',            [AdminApiController::class, 'scoringRehearsal']);
        Route::delete('/testing/simulated-players',         [AdminApiController::class, 'clearSimulatedPlayers']);
        Route::post('/testing/reset-event',                 [AdminApiController::class, 'resetEvent']);
        Route::post('/phase',                              [AdminApiController::class, 'setPhase']);
        Route::get('/rounds',                              [AdminApiController::class, 'showRounds']);
        Route::put('/rounds/settings',                     [AdminApiController::class, 'updateRoundSettings']);
        Route::put('/rounds/{round}',                      [AdminApiController::class, 'updateRound']);
        Route::put('/rounds/{round}/questions/order',      [AdminApiController::class, 'reorderRoundQuestions']);
        Route::post('/rounds/{round}/start',               [AdminApiController::class, 'startRound']);
        Route::post('/rounds/{round}/complete',            [AdminApiController::class, 'completeRound']);
        Route::put('/questions/{question}/round',          [AdminApiController::class, 'assignQuestionToRound']);
        Route::get('/questions',                           [AdminApiController::class, 'listQuestions']);
        Route::post('/questions',                          [AdminApiController::class, 'storeQuestion']);
        Route::put('/questions/{question}',                [AdminApiController::class, 'updateQuestion']);
        Route::patch('/questions/{question}/duration',     [AdminApiController::class, 'updateQuestionDuration']);
        Route::delete('/questions/{question}',             [AdminApiController::class, 'destroyQuestion']);
        Route::post('/questions/{question}/activate',      [AdminApiController::class, 'activateQuestion']);
        Route::post('/questions/{question}/close',         [AdminApiController::class, 'closeQuestion']);
        Route::post('/questions/{question}/skip',          [AdminApiController::class, 'skipQuestion']);
        Route::post('/questions/{question}/reopen',        [AdminApiController::class, 'reopenQuestion']);
        Route::post('/questions/{question}/invalidate',    [AdminApiController::class, 'invalidateQuestion']);
        Route::post('/match-result',                       [AdminApiController::class, 'setMatchResult']);
        Route::post('/players/lookup',                     [AdminApiController::class, 'lookupPlayer']);
        Route::post('/players/{player}/adjust-score',      [AdminApiController::class, 'adjustScore']);
    });

});
