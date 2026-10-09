<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\CreateWorkspaceInvitationRequest;
use App\Http\Requests\Workspaces\RespondToWorkspaceInvitationRequest;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\Workspaces\WorkspaceInvitation as WorkspaceInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class WorkspaceInvitationController extends Controller
{
    /**
     * Store a newly created invitation.
     */
    public function store(CreateWorkspaceInvitationRequest $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('inviteMember', $workspace);

        $invitation = $workspace->invitations()->create([
            'email' => $request->validated('email'),
            'role' => WorkspaceRole::from($request->validated('role')),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(3),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new WorkspaceInvitationNotification($invitation));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return to_route('workspaces.edit', ['workspace' => $workspace->slug]);
    }

    /**
     * Cancel the specified invitation.
     */
    public function destroy(Workspace $workspace, WorkspaceInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->workspace_id === $workspace->id, 404);

        Gate::authorize('cancelInvitation', $workspace);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return to_route('workspaces.edit', ['workspace' => $workspace->slug]);
    }

    /**
     * Accept the invitation.
     */
    public function accept(RespondToWorkspaceInvitationRequest $request, WorkspaceInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $invitation) {
            $workspace = $invitation->workspace;

            $workspace->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $invitation->role],
            );

            $invitation->update(['accepted_at' => now()]);

            $user->switchWorkspace($workspace);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation accepted.')]);

        return to_route('dashboard');
    }

    /**
     * Decline the invitation.
     */
    public function decline(RespondToWorkspaceInvitationRequest $request, WorkspaceInvitation $invitation): RedirectResponse
    {
        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation declined.')]);

        return to_route('dashboard');
    }
}
