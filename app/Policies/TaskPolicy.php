<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function create(User $user, Project $project): bool
    {
        return $user->workspaceRole($project->workspace)?->isAtLeast(WorkspaceRole::Member) ?? false;
    }

    public function complete(User $user, Project $project): bool
    {
        return $user->workspaceRole($project->workspace)?->isAtLeast(WorkspaceRole::Member) ?? false;
    }

    public function view(User $user, Task $task): bool
    {
        return $user->belongsToWorkspace($task->project->workspace);
    }
}
