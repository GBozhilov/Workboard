<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Http\Requests\FilterProjectTasksRequest;
use App\Http\Requests\IndexProjectRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectController extends Controller
{
    private const PROJECTS_PER_PAGE = 12;

    private const TASKS_PER_PAGE = 10;

    public function index(IndexProjectRequest $request): View
    {
        $projects = Project::query()
            ->accessibleBy($request->user())
            ->search($request->searchTerm())
            ->sorted($request->sort())
            ->paginate(self::PROJECTS_PER_PAGE)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'search' => $request->input('search', ''),
            'sort' => $request->sort(),
            'hasActiveFilters' => $request->hasActiveFilters(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Project::class);

        return view('projects.create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $request->user()->projects()->create($request->validated());

        $project->members()->attach($request->user()->id, [
            'role' => ProjectRole::Owner->value,
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Project created successfully.');
    }

    public function show(FilterProjectTasksRequest $request, Project $project): View
    {
        $project->load('members');

        $tasks = $project->tasks()
            ->search($request->searchTerm())
            ->filterStatus($request->statusFilter())
            ->filterPriority($request->priorityFilter())
            ->sorted($request->sort())
            ->paginate(self::TASKS_PER_PAGE)
            ->withQueryString();

        $totalTasksCount = $project->tasks()->count();

        return view('projects.show', [
            'project' => $project,
            'tasks' => $tasks,
            'taskSearch' => $request->input('search', ''),
            'taskStatus' => $request->input('status', FilterProjectTasksRequest::STATUS_ALL),
            'taskPriority' => $request->input('priority', FilterProjectTasksRequest::PRIORITY_ALL),
            'taskSort' => $request->sort(),
            'hasActiveTaskFilters' => $request->hasActiveFilters(),
            'totalTasksCount' => $totalTasksCount,
        ]);
    }

    public function edit(Project $project): View
    {
        Gate::authorize('update', $project);

        return view('projects.edit', compact('project'));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Project updated successfully.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('status', 'Project deleted successfully.');
    }
}
