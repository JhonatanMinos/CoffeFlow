<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateWorkspace
{
    /**
     * Create a new workspace and add the user as owner.
     */
    public function handle(User $user, string $name, WorkspaceType $type = WorkspaceType::Team): Workspace
    {
        return DB::transaction(function () use ($user, $name, $type) {
            $workspace = Workspace::create([
                'name' => $name,
                'type' => $type,
            ]);

            $workspace->memberships()->create([
                'user_id' => $user->id,
                'role' => WorkspaceRole::Owner,
            ]);

            $user->switchWorkspace($workspace);

            return $workspace;
        });
    }
}
