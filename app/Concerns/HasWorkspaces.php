<?php

namespace App\Concerns;

use App\Data\UserWorkspace;
use App\Data\WorkspacePermissions;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

trait HasWorkspaces
{
    /**
     * Get all of the workspaces the user belongs to.
     *
     * @return BelongsToMany<Workspace, $this>
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members', 'user_id', 'workspace_id')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all of the workspaces the user owns.
     *
     * @return HasManyThrough<Workspace, WorkspaceMember, $this>
     */
    public function ownedWorkspaces(): HasManyThrough
    {
        return $this->hasManyThrough(
            Workspace::class,
            WorkspaceMember::class,
            'user_id',
            'id',
            'id',
            'workspace_id',
        )->where('workspace_members.role', WorkspaceRole::Owner->value);
    }

    /**
     * Get all of the memberships for the user.
     *
     * @return HasMany<WorkspaceMember, $this>
     */
    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class, 'user_id');
    }

    /**
     * Get the user's current workspace.
     *
     * @return BelongsTo<Workspace, $this>
     */
    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    /**
     * Get the user's personal workspace.
     */
    public function personalWorkspace(): ?Workspace
    {
        return $this->ownedWorkspaces()
            ->where('workspaces.type', WorkspaceType::Personal->value)
            ->first();
    }

    /**
     * Switch to the given workspace.
     */
    public function switchWorkspace(Workspace $workspace): bool
    {
        if (! $this->belongsToWorkspace($workspace)) {
            return false;
        }

        $this->update(['current_workspace_id' => $workspace->id]);
        $this->setRelation('currentWorkspace', $workspace);

        URL::defaults(['current_workspace' => $workspace->slug]);

        return true;
    }

    /**
     * Determine if the user belongs to the given workspace.
     */
    public function belongsToWorkspace(Workspace $workspace): bool
    {
        return $this->workspaces()->where('workspaces.id', $workspace->id)->exists();
    }

    /**
     * Determine if the given workspace is the user's current workspace.
     */
    public function isCurrentWorkspace(Workspace $workspace): bool
    {
        return $this->current_workspace_id === $workspace->id;
    }

    /**
     * Determine if the user is the owner of the given workspace.
     */
    public function ownsWorkspace(Workspace $workspace): bool
    {
        return $this->workspaceRole($workspace) === WorkspaceRole::Owner;
    }

    /**
     * Get the user's role on the given workspace.
     */
    public function workspaceRole(Workspace $workspace): ?WorkspaceRole
    {
        return $this->workspaceMemberships()
            ->where('workspace_id', $workspace->id)
            ->first()
            ?->role;
    }

    /**
     * Get the user's workspaces as a collection of UserWorkspace objects.
     *
     * @return Collection<int, UserWorkspace>
     */
    public function toUserWorkspaces(bool $includeCurrent = false): Collection
    {
        return $this->workspaces()
            ->get()
            ->map(fn (Workspace $workspace) => ! $includeCurrent && $this->isCurrentWorkspace($workspace) ? null : $this->toUserWorkspace($workspace))
            ->filter()
            ->values();
    }

    /**
     * Get the user's workspace as a UserWorkspace object.
     */
    public function toUserWorkspace(Workspace $workspace): UserWorkspace
    {
        $role = $this->workspaceRole($workspace);

        return new UserWorkspace(
            id: $workspace->id,
            name: $workspace->name,
            slug: $workspace->slug,
            isPersonal: $workspace->type === WorkspaceType::Personal,
            role: $role?->value,
            roleLabel: $role?->label(),
            isCurrent: $this->isCurrentWorkspace($workspace),
        );
    }

    /**
     * Get the standard permissions for a workspace as a WorkspacePermissions object.
     */
    public function toWorkspacePermissions(Workspace $workspace): WorkspacePermissions
    {
        $role = $this->workspaceRole($workspace);

        return new WorkspacePermissions(
            canUpdateWorkspace: $role?->hasPermission(WorkspacePermission::UpdateWorkspace) ?? false,
            canDeleteWorkspace: $role?->hasPermission(WorkspacePermission::DeleteWorkspace) ?? false,
            canAddMember: $role?->hasPermission(WorkspacePermission::AddMember) ?? false,
            canUpdateMember: $role?->hasPermission(WorkspacePermission::UpdateMember) ?? false,
            canRemoveMember: $role?->hasPermission(WorkspacePermission::RemoveMember) ?? false,
            canCreateInvitation: $role?->hasPermission(WorkspacePermission::CreateInvitation) ?? false,
            canCancelInvitation: $role?->hasPermission(WorkspacePermission::CancelInvitation) ?? false,
        );
    }

    public function fallbackWorkspace(?Workspace $excluding = null): ?Workspace
    {
        return $this->workspaces()
            ->when($excluding, fn ($query) => $query->where('workspaces.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(workspaces.name)')
            ->first();
    }

    /**
     * Determine if the user has the given permission on the workspace.
     */
    public function hasWorkspacePermission(Workspace $workspace, WorkspacePermission $permission): bool
    {
        return $this->workspaceRole($workspace)?->hasPermission($permission) ?? false;
    }
}
