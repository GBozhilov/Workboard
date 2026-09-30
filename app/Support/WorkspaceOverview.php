<?php

namespace App\Support;

readonly class WorkspaceOverview
{
    public function __construct(
        public int $accessibleProjectsCount,
        public int $openTasksCount,
        public int $unreadNotificationsCount,
        public int $assignedToMeOpenTasksCount,
    ) {}
}
