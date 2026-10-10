<?php

namespace App\Models;

use App\Enums\ProjectMethodology;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $created_by
 * @property string $name
 * @property string $slug
 * @property ProjectMethodology $methodology
 * @property ProjectStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Workspace $workspace
 * @property-read User $creator
 * @property-read Collection<int, Board> $boards
 * @property-read Collection<int, Task> $tasks
 */
#[Fillable(['workspace_id', 'created_by', 'name', 'slug', 'methodology', 'status'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if (empty($project->slug)) {
                $project->slug = self::uniqueSlug($project->workspace_id, $project->name);
            }
        });
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Board, $this> */
    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    protected function casts(): array
    {
        return [
            'methodology' => ProjectMethodology::class,
            'status' => ProjectStatus::class,
        ];
    }

    private static function uniqueSlug(int $workspaceId, string $name): string
    {
        $base = Str::slug($name) ?: 'proyecto';
        $slug = $base;
        $suffix = 1;

        while (static::withTrashed()
            ->where('workspace_id', $workspaceId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
