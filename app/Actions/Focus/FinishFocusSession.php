<?php

namespace App\Actions\Focus;

use App\Models\User;

class FinishFocusSession
{
    public function handle(User $user, int $sessionId): ?bool
    {
        return app(CloseFocusSession::class)->handle($user, $sessionId, CloseFocusSession::FINISH);
    }
}
