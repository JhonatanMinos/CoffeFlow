<?php

namespace App\Actions\Focus;

use App\Models\FocusSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PauseFocusSession
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

            if ($session->status !== 'running') {
                return false;
            }

            $interval = $session->intervals()->whereNull('ended_at')->lockForUpdate()->first();
            if ($interval) {
                $interval->update(['ended_at' => now()]);
            }

            $session->update(['status' => 'paused']);

            return true;
        });
    }
}
