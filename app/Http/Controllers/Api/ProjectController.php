<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexApiProjectRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Support\ProjectSummaryCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index(IndexApiProjectRequest $request): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->accessibleBy($request->user())
            ->with('user')
            ->search($request->searchTerm())
            ->sorted($request->sort())
            ->paginate($request->perPage());

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $request->user()->projects()->create($request->validated());

        $project->members()->attach($request->user()->id, [
            'role' => ProjectRole::Owner->value,
        ]);

        $project->load('user');

        return ProjectResource::make($project)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Project $project): ProjectResource
    {
        Gate::authorize('view', $project);

        $project->load('user');
        $project->summary = app(ProjectSummaryCache::class)->get($project);

        return ProjectResource::make($project);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $project->update($request->validated());
        $project->load('user');

        return ProjectResource::make($project);
    }

    public function destroy(Project $project): JsonResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return response()->json(null, 204);
    }
}
