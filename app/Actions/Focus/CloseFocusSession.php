<?php

namespace App\Actions\Focus;

use App\Models\FocusSession;
use App\Models\FocusSessionInterval;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CloseFocusSession
{
    public const FINISH = 'finish';

    public const INTERRUPT = 'interrupt';

    public function handle(User $user, int $sessionId, string $command): ?bool
    {
        return DB::transaction(function () use ($user, $sessionId, $command): ?bool {
            $session = FocusSession::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->find($sessionId);

            if (! $session) {
                return null;
            }

            if (in_array($session->status, ['completed', 'interrupted'], true)) {
                return true;
            }

            $allowed = $command === self::FINISH
                ? $session->status === 'running'
                : in_array($session->status, ['running', 'paused'], true);

            if (! $allowed) {
                return false;
            }

            $now = now();
            $openInterval = $session->intervals()
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();
            $stale = false;

            if ($openInterval) {
                if ($session->status === 'running') {
                    $outcome = app(ConfirmFocusSessionInterval::class)->handle($openInterval, $now);
                    $stale = $outcome === ConfirmFocusSessionInterval::EXPIRED;
                    $openInterval->refresh();
                }

                if ($openInterval->ended_at === null) {
                    $safeEnd = $openInterval->last_confirmed_at ?? $openInterval->started_at;
                    if ($safeEnd->lessThan($openInterval->started_at)) {
                        $safeEnd = $openInterval->started_at;
                    }
                    $openInterval->update(['ended_at' => $safeEnd]);
                }
            } elseif ($session->status === 'running') {
                // A running session without its expected interval has no verifiable active time.
                $stale = true;
            }

            $intervals = $session->intervals()->get(['started_at', 'ended_at']);
            $effectiveSeconds = $intervals->sum(function (FocusSessionInterval $interval): int {
                if ($interval->ended_at === null) {
                    return 0;
                }

                return max(0, $interval->ended_at->getTimestamp() - $interval->started_at->getTimestamp());
            });

            $hasValidTarget = is_int($session->target_duration_seconds)
                && $session->target_duration_seconds > 0;
            $completed = $command === self::FINISH
                && ! $stale
                && $hasValidTarget
                && $effectiveSeconds >= $session->target_duration_seconds;

            $latestBoundary = $session->started_at;
            foreach ($intervals as $interval) {
                foreach ([$interval->started_at, $interval->ended_at] as $boundary) {
                    if ($boundary !== null && $boundary->greaterThan($latestBoundary)) {
                        $latestBoundary = $boundary;
                    }
                }
            }

            $endedAt = Carbon::instance($now);
            if ($endedAt->lessThan($latestBoundary)) {
                $endedAt = $latestBoundary;
            }

            $session->update([
                'status' => $completed ? 'completed' : 'interrupted',
                'ended_at' => $endedAt,
            ]);

            return true;
        });
    }
}
