<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TopicalMapController;
use App\Http\Controllers\UserController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Projects (user's own projects)
    Route::apiResource('projects', ProjectController::class);

    // Topical Map endpoints
    Route::post('projects/{project}/generate-map', [TopicalMapController::class, 'generate']);
    Route::get('projects/{project}/clusters', [TopicalMapController::class, 'clusters']);
    Route::get('projects/{project}/clusters/{cluster}', [TopicalMapController::class, 'showCluster']);
    Route::post('projects/{project}/suggestions', [TopicalMapController::class, 'suggestions']);
    Route::post('projects/{project}/ideas', [TopicalMapController::class, 'ideas']);
    Route::post('projects/{project}/clusters/{cluster}/suggestions', [TopicalMapController::class, 'clusterSuggestions']);

    // Admin-only routes
    Route::middleware('admin')->group(function () {
        // User routes
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{user}', [UserController::class, 'show']);
    });
});
