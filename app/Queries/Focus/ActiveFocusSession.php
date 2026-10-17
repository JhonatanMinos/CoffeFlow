<?php

namespace App\Queries\Focus;

use App\Models\FocusSession;
use App\Models\User;

class ActiveFocusSession
{
    /** @return array{id: int, taskId: int|null, status: string, targetDurationSeconds: int|null, confirmedSeconds: int, intervalStartedAt: string|null, lastConfirmedAt: string|null}|null */
    public function forOwner(User $owner): ?array
    {
        $session = FocusSession::query()
            ->select(['id', 'task_id', 'status', 'target_duration_seconds'])
            ->with('intervals:id,focus_session_id,started_at,ended_at,last_confirmed_at')
            ->where('user_id', $owner->id)
            ->whereIn('status', ['running', 'paused'])
            ->first();

        if (! $session) {
            return null;
        }

        $openInterval = $session->intervals->first(fn ($interval) => $interval->ended_at === null);
        $latestInterval = $session->intervals->sortByDesc('started_at')->first();
        $lastConfirmedAt = $openInterval?->last_confirmed_at
            ?? $openInterval?->started_at
            ?? $latestInterval?->last_confirmed_at
            ?? $latestInterval?->ended_at;

        return [
            'id' => $session->id,
            'taskId' => $session->task_id,
            'status' => $session->status,
            'targetDurationSeconds' => $session->target_duration_seconds,
            'confirmedSeconds' => self::confirmedSeconds($session),
            'intervalStartedAt' => $openInterval?->started_at?->toIso8601String(),
            'lastConfirmedAt' => $lastConfirmedAt?->toIso8601String(),
        ];
    }

    public static function confirmedSeconds(FocusSession $session): int
    {
        $session->loadMissing('intervals:id,focus_session_id,started_at,ended_at,last_confirmed_at');

        return $session->intervals->sum(function ($interval): int {
            $end = $interval->ended_at ?? $interval->last_confirmed_at ?? $interval->started_at;

            return max(0, (int) $interval->started_at->diffInSeconds($end, false));
        });
    }
}
