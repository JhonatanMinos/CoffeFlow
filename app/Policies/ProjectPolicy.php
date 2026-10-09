<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;

class ProjectPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->belongsToWorkspace($project->workspace);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $user->workspaceRole($workspace)?->isAtLeast(WorkspaceRole::Manager) ?? false;
    }

    public function update(User $user, Project $project): bool
    {
        return $user->workspaceRole($project->workspace)?->isAtLeast(WorkspaceRole::Manager) ?? false;
    }

    public function delete(User $user, Project $project): bool
    {
        return in_array($user->workspaceRole($project->workspace), [
            WorkspaceRole::Owner,
            WorkspaceRole::Admin,
        ], true);
    }
}
