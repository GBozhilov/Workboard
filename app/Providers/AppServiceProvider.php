<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Observers\TaskObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('tag', function (string $value, \Illuminate\Routing\Route $route): Tag {
            $project = $route->parameter('project');
            $projectId = $project instanceof Project ? $project->id : (int) $project;

            return Tag::query()
                ->where('project_id', $projectId)
                ->whereKey($value)
                ->firstOrFail();
        });

        Task::observe(TaskObserver::class);

        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();

            $view->with(
                'unreadNotificationsCount',
                $user ? $user->unreadNotifications()->count() : 0,
            );
        });
    }
}
