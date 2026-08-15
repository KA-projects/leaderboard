<?php

use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\UserActionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/users', [UserController::class, 'store']);
Route::get('/users/{user}', [UserController::class, 'show']);
Route::get('/users/{user}/rank', [UserController::class, 'rank']);
Route::get('/users/{user}/neighbors', [UserController::class, 'neighbors']);

Route::post('/actions', [UserActionController::class, 'store']);

Route::get('/leaderboard', [LeaderboardController::class, 'index']);
