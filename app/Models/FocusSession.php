<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $task_id
 * @property string $status
 * @property Carbon $started_at
 * @property-read User $user
 * @property-read Task|null $task
 */
#[Fillable(['user_id', 'task_id', 'status', 'started_at'])]
class FocusSession extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return HasMany<FocusSessionInterval, $this> */
    public function intervals(): HasMany
    {
        return $this->hasMany(FocusSessionInterval::class);
    }
}
