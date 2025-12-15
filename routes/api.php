<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\KeywordController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Projects
    Route::get('projects', [ProjectController::class, 'index']);
    Route::post('projects', [ProjectController::class, 'store']);
    Route::get('projects/{project}', [ProjectController::class, 'show']);
    Route::put('projects/{project}', [ProjectController::class, 'update']);
    Route::delete('projects/{project}', [ProjectController::class, 'destroy']);
    Route::get('projects/{project}/stats', [ProjectController::class, 'stats']);

    // Keywords
    Route::get('projects/{project}/keywords', [KeywordController::class, 'index']);
    Route::post('projects/{project}/keywords', [KeywordController::class, 'store']);
    Route::get('keywords/{keyword}', [KeywordController::class, 'show']);
    Route::put('keywords/{keyword}', [KeywordController::class, 'update']);
    Route::delete('keywords/{keyword}', [KeywordController::class, 'destroy']);
    Route::patch('keywords/{keyword}/move', [KeywordController::class, 'move']);
    Route::patch('keywords/reorder', [KeywordController::class, 'reorder']);

    // Admin-only routes
    Route::middleware('admin')->group(function () {
        // User routes
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{user}', [UserController::class, 'show']);
    });
});
