<?php

use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    WorkspaceInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired workspace invitations');
