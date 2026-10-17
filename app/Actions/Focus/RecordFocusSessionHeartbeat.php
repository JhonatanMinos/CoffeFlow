<?php

namespace App\Actions\Focus;

use App\Models\FocusSession;
use App\Models\User;
use App\Queries\Focus\ActiveFocusSession;
use Illuminate\Support\Facades\DB;

class RecordFocusSessionHeartbeat
{
    /** @return array{status: string, confirmedAt: ?string, intervalStartedAt: ?string, confirmedSeconds?: int, targetDurationSeconds?: int|null} | null */
    public function handle(User $user, int $sessionId): ?array
    {
        return DB::transaction(function () use ($user, $sessionId): ?array {
            $session = FocusSession::query()->where('user_id', $user->id)->lockForUpdate()->find($sessionId);
            if (! $session) {
                return null;
            }

            if ($session->status !== 'running') {
                return ['status' => 'invalid'];
            }

            $interval = $session->intervals()->whereNull('ended_at')->lockForUpdate()->first();
            if (! $interval) {
                $session->update(['status' => 'paused']);

                return [
                    'status' => 'paused',
                    'confirmedAt' => null,
                    'intervalStartedAt' => null,
                    'confirmedSeconds' => ActiveFocusSession::confirmedSeconds($session),
                    'targetDurationSeconds' => $session->target_duration_seconds,
                ];
            }

            $outcome = app(ConfirmFocusSessionInterval::class)->handle($interval, now());
            if ($outcome === ConfirmFocusSessionInterval::EXPIRED) {
                $session->update(['status' => 'paused']);
            }

            $interval->refresh();

            return [
                'status' => $outcome === ConfirmFocusSessionInterval::EXPIRED ? 'paused' : 'running',
                'confirmedAt' => ($interval->last_confirmed_at ?? $interval->started_at)->toIso8601String(),
                'intervalStartedAt' => $interval->started_at->toIso8601String(),
                'confirmedSeconds' => ActiveFocusSession::confirmedSeconds($session),
                'targetDurationSeconds' => $session->target_duration_seconds,
            ];
        });
    }
}
