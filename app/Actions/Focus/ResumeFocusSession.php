<?php

namespace App\Actions\Focus;

use App\Models\FocusSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResumeFocusSession
{
    public function handle(User $user, int $sessionId): ?bool
    {
        return DB::transaction(function () use ($user, $sessionId): ?bool {
            $session = FocusSession::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->find($sessionId);

            if (! $session) {
                return null;
            }

            if ($session->status !== 'paused' || $session->intervals()->whereNull('ended_at')->exists()) {
                return false;
            }

            $startedAt = now();
            $session->intervals()->create(['started_at' => $startedAt, 'last_confirmed_at' => $startedAt]);
            $session->update(['status' => 'running']);

            return true;
        });
    }
}
