<?php

namespace App\Models;

use App\Enums\BoardColumnCategory;
use Database\Factories\BoardColumnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $board_id
 * @property string $name
 * @property BoardColumnCategory $category
 * @property int $position
 * @property int|null $wip_limit
 * @property-read Board $board
 */
#[Fillable(['board_id', 'name', 'category', 'position', 'wip_limit'])]
class BoardColumn extends Model
{
    /** @use HasFactory<BoardColumnFactory> */
    use HasFactory;

    /** @return BelongsTo<Board, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    protected function casts(): array
    {
        return [
            'category' => BoardColumnCategory::class,
            'position' => 'integer',
            'wip_limit' => 'integer',
        ];
    }
}
