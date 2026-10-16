<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $focus_session_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $last_confirmed_at
 * @property-read FocusSession $focusSession
 */
#[Fillable(['focus_session_id', 'started_at', 'ended_at', 'last_confirmed_at'])]
class FocusSessionInterval extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FocusSession, $this> */
    public function focusSession(): BelongsTo
    {
        return $this->belongsTo(FocusSession::class);
    }
}
