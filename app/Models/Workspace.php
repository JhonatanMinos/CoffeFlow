<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueWorkspaceSlugs;
use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property WorkspaceType $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, WorkspaceInvitation> $invitations
 * @property-read Collection<int, WorkspaceMember> $memberships
 * @property-read Collection<int, User> $members
 */
#[Fillable(['name', 'slug', 'type'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use GeneratesUniqueWorkspaceSlugs, HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Workspace $workspace) {
            if (empty($workspace->slug)) {
                $workspace->slug = static::generateUniqueWorkspaceSlug($workspace->name);
            }
        });

        static::updating(function (Workspace $workspace) {
            if ($workspace->isDirty('name')) {
                $workspace->slug = static::generateUniqueWorkspaceSlug($workspace->name, $workspace->id);
            }
        });
    }

    /**
     * Get the workspace owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', WorkspaceRole::Owner->value)
            ->first();
    }

    /**
     * Get all members of this workspace.
     *
     * @return BelongsToMany<User, $this, WorkspaceMember, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members', 'workspace_id', 'user_id')
            ->using(WorkspaceMember::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this workspace.
     *
     * @return HasMany<WorkspaceMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * Get all invitations for this workspace.
     *
     * @return HasMany<WorkspaceInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WorkspaceType::class,
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
