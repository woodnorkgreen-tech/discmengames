<?php

use App\Http\Controllers\Api\EventStateController;
use App\Http\Controllers\Api\PlayerApiController;
use Illuminate\Support\Facades\Route;

// ── Polled by all clients every 1.5 s ────────────────────────────────────────
Route::get('/state', [EventStateController::class, 'show']);

// ── Player ────────────────────────────────────────────────────────────────────
Route::post('/players', [PlayerApiController::class, 'store'])->middleware('throttle:register');
Route::post('/players/login', [PlayerApiController::class, 'login'])->middleware('throttle:player-login');
Route::post('/answers', [PlayerApiController::class, 'submitAnswer'])->middleware('throttle:per-player');
Route::get('/answers/result', [PlayerApiController::class, 'answerResult'])->middleware('throttle:per-player');
Route::post('/predictions', [PlayerApiController::class, 'submitPrediction'])->middleware('throttle:per-player');
Route::get('/predictions/current', [PlayerApiController::class, 'currentPrediction'])->middleware('throttle:60,1');
Route::get('/predictions/feed', [PlayerApiController::class, 'predictionFeed'])->middleware('throttle:60,1');
Route::get('/leaderboard', [PlayerApiController::class, 'leaderboard']);
