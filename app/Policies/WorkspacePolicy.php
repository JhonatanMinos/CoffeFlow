<?php

namespace App\Policies;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceType;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspacePermission($workspace, WorkspacePermission::UpdateWorkspace);
    }

    /**
     * Determine whether the user can leave the workspace.
     */
    public function leave(User $user, Workspace $workspace): bool
    {
        return $workspace->type !== WorkspaceType::Personal
            && $user->belongsToWorkspace($workspace)
            && ! $user->ownsWorkspace($workspace);
    }

    /**
     * Determine whether the user can add a member to the workspace.
     */
    public function addMember(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspacePermission($workspace, WorkspacePermission::AddMember);
    }

    /**
     * Determine whether the user can update a member's role in the workspace.
     */
    public function updateMember(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspacePermission($workspace, WorkspacePermission::UpdateMember);
    }

    /**
     * Determine whether the user can remove a member from the workspace.
     */
    public function removeMember(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspacePermission($workspace, WorkspacePermission::RemoveMember);
    }

    /**
     * Determine whether the user can invite members to the workspace.
     */
    public function inviteMember(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspacePermission($workspace, WorkspacePermission::CreateInvitation);
    }

    /**
     * Determine whether the user can cancel invitations.
     */
    public function cancelInvitation(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspacePermission($workspace, WorkspacePermission::CancelInvitation);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->type !== WorkspaceType::Personal
            && $user->hasWorkspacePermission($workspace, WorkspacePermission::DeleteWorkspace);
    }
}
