<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

class ProjectNotificationRecipients
{
    /**
     * @return Collection<int, User>
     */
    public static function projectOwnerExceptActor(Project $project, User $actor): Collection
    {
        if ($project->user_id === $actor->id) {
            return collect();
        }

        $owner = $project->user;

        if ($owner === null) {
            return collect();
        }

        return collect([$owner]);
    }

    /**
     * @return Collection<int, User>
     */
    public static function addedProjectMember(User $member, User $actor): Collection
    {
        if ($member->id === $actor->id) {
            return collect();
        }

        return collect([$member]);
    }
}
