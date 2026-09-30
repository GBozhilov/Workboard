<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectTagController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskTagController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('api.login');

Route::middleware('auth:sanctum')->name('api.')->group(function () {
    Route::get('/user', [AuthController::class, 'user'])->name('user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::apiResource('projects', ProjectController::class);

    Route::get('projects/{project}/tags', [ProjectTagController::class, 'index'])->name('projects.tags.index');

    Route::apiResource('projects.tasks', TaskController::class)->scoped();

    Route::get('projects/{project}/tasks/{task}/comments', [CommentController::class, 'index'])
        ->name('projects.tasks.comments.index')
        ->scopeBindings();
    Route::post('projects/{project}/tasks/{task}/comments', [CommentController::class, 'store'])
        ->name('projects.tasks.comments.store')
        ->scopeBindings();
    Route::delete('projects/{project}/tasks/{task}/comments/{comment}', [CommentController::class, 'destroy'])
        ->name('projects.tasks.comments.destroy')
        ->scopeBindings();

    Route::post('projects/{project}/tasks/{task}/tags', [TaskTagController::class, 'store'])
        ->name('projects.tasks.tags.store')
        ->scopeBindings();
    Route::post('projects/{project}/tasks/{task}/tags/{tag}/attach', [TaskTagController::class, 'attach'])
        ->name('projects.tasks.tags.attach')
        ->scopeBindings();
    Route::delete('projects/{project}/tasks/{task}/tags/{tag}', [TaskTagController::class, 'destroy'])
        ->name('projects.tasks.tags.destroy')
        ->scopeBindings();
});
