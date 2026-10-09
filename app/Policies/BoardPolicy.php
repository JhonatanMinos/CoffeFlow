<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Board;
use App\Models\User;

class BoardPolicy
{
    public function view(User $user, Board $board): bool
    {
        return $user->belongsToWorkspace($board->project->workspace);
    }

    public function update(User $user, Board $board): bool
    {
        return $user->workspaceRole($board->project->workspace)?->isAtLeast(WorkspaceRole::Manager) ?? false;
    }
}
