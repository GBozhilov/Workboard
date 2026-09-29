<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskTagController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('projects', ProjectController::class);

    Route::post('projects/{project}/members', [ProjectMemberController::class, 'store'])
        ->name('projects.members.store');
    Route::delete('projects/{project}/members/{member}', [ProjectMemberController::class, 'destroy'])
        ->name('projects.members.destroy');

    // Scoped binding: resolve {task} via $project->tasks() (task id in URL, must belong to project).
    Route::resource('projects.tasks', TaskController::class)->scoped();

    Route::post('projects/{project}/tasks/{task}/comments', [CommentController::class, 'store'])
        ->name('projects.tasks.comments.store')
        ->scopeBindings();
    Route::delete('projects/{project}/tasks/{task}/comments/{comment}', [CommentController::class, 'destroy'])
        ->name('projects.tasks.comments.destroy')
        ->scopeBindings();

    Route::post('projects/{project}/tasks/{task}/tags', [TaskTagController::class, 'store'])
        ->name('projects.tasks.tags.store')
        ->scopeBindings();
    Route::delete('projects/{project}/tasks/{task}/tags/{tag}', [TaskTagController::class, 'destroy'])
        ->name('projects.tasks.tags.destroy')
        ->scopeBindings();

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
