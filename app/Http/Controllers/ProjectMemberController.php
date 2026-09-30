<?php

namespace App\Http\Controllers;

use App\Events\ProjectMemberAdded;
use App\Http\Requests\StoreProjectMemberRequest;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectSummaryCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ProjectMemberController extends Controller
{
    public function store(StoreProjectMemberRequest $request, Project $project): RedirectResponse
    {
        $member = User::query()->where('email', $request->validated('email'))->firstOrFail();

        $project->members()->attach($member->id, [
            'role' => $request->memberRole()->value,
        ]);

        ProjectMemberAdded::dispatch($project, $member, $request->user());

        ProjectSummaryCache::forget($project);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Member added successfully.');
    }

    public function destroy(Project $project, User $member): RedirectResponse
    {
        Gate::authorize('manageMembers', $project);

        if ($member->id === $project->user_id) {
            return redirect()
                ->route('projects.show', $project)
                ->withErrors(['member' => 'The project owner cannot be removed.']);
        }

        if (! $project->members()->whereKey($member->id)->exists()) {
            abort(404);
        }

        $project->members()->detach($member->id);

        ProjectSummaryCache::forget($project);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Member removed successfully.');
    }
}
