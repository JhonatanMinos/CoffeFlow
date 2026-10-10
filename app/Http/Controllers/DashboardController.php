<?php

namespace App\Http\Controllers;

use App\Models\FocusSession;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);
        $activeFocusSession = FocusSession::query()
            ->select(['id', 'task_id', 'status'])
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['running', 'paused'])
            ->first();

        $pendingInvitations = WorkspaceInvitation::query()
            ->with(['inviter', 'workspace'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (WorkspaceInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'workspace' => [
                    'name' => $invitation->workspace->name,
                    'slug' => $invitation->workspace->slug,
                ],
            ]);

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'activeFocusSession' => $activeFocusSession ? [
                'id' => $activeFocusSession->id,
                'taskId' => $activeFocusSession->task_id,
                'status' => $activeFocusSession->status,
            ] : null,
        ]);
    }
}
