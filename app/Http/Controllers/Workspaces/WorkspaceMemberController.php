<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\UpdateWorkspaceMemberRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class WorkspaceMemberController extends Controller
{
    /**
     * Update the specified workspace member's role.
     */
    public function update(UpdateWorkspaceMemberRequest $request, Workspace $workspace, User $user): RedirectResponse
    {
        Gate::authorize('updateMember', $workspace);

        $newRole = WorkspaceRole::from($request->validated('role'));

        $workspace->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->update(['role' => $newRole]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return to_route('workspaces.edit', ['workspace' => $workspace->slug]);
    }

    /**
     * Remove the specified workspace member.
     */
    public function destroy(Workspace $workspace, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $workspace);

        abort_if($workspace->owner()?->is($user), 403, __('The workspace owner cannot be removed.'));

        $workspace->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($user->isCurrentWorkspace($workspace)) {
            $user->switchWorkspace($user->personalWorkspace());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('workspaces.edit', ['workspace' => $workspace->slug]);
    }
}
