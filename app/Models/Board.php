<?php

namespace App\Models;

use Database\Factories\BoardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string $slug
 * @property bool $is_default
 * @property-read Project $project
 * @property-read Collection<int, BoardColumn> $columns
 */
#[Fillable(['project_id', 'name', 'slug', 'is_default'])]
class Board extends Model
{
    /** @use HasFactory<BoardFactory> */
    use HasFactory;

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<BoardColumn, $this> */
    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('position');
    }

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }
}
