<?php

namespace App\Http\Controllers\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\DeleteWorkspaceRequest;
use App\Http\Requests\Workspaces\SaveWorkspaceRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    /**
     * Display a listing of the user's workspaces.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('workspaces/index', [
            'workspaces' => $user->toUserWorkspaces(includeCurrent: true),
        ]);
    }

    /**
     * Store a newly created workspace.
     */
    public function store(SaveWorkspaceRequest $request, CreateWorkspace $createWorkspace): RedirectResponse
    {
        $workspace = $createWorkspace->handle($request->user(), $request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace created.')]);

        return to_route('workspaces.edit', ['workspace' => $workspace->slug]);
    }

    /**
     * Show the workspace edit page.
     */
    public function edit(Request $request, Workspace $workspace): Response
    {
        $user = $request->user();

        return Inertia::render('workspaces/edit', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'isPersonal' => $workspace->type === WorkspaceType::Personal,
            ],
            'members' => $workspace->members()->get()->map(function (User $member) {
                /** @var WorkspaceMember $membership */
                $membership = $member->getRelation('pivot');

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'avatar' => $member->avatar ?? null,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                ];
            }),
            'invitations' => $workspace->invitations()
                ->whereNull('accepted_at')
                ->get()
                ->map(fn ($invitation) => [
                    'code' => $invitation->code,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'role_label' => $invitation->role->label(),
                    'created_at' => $invitation->created_at->toISOString(),
                ]),
            'permissions' => $user->toWorkspacePermissions($workspace),
            'availableRoles' => WorkspaceRole::assignable(),
        ]);
    }

    /**
     * Update the specified workspace.
     */
    public function update(SaveWorkspaceRequest $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('update', $workspace);

        $workspace = DB::transaction(function () use ($request, $workspace) {
            $workspace = Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();

            $workspace->update(['name' => $request->validated('name')]);

            return $workspace;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace updated.')]);

        return to_route('workspaces.edit', ['workspace' => $workspace->slug]);
    }

    /**
     * Switch the user's current workspace.
     */
    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($request->user()->belongsToWorkspace($workspace), 403);

        $request->user()->switchWorkspace($workspace);

        return back();
    }

    /**
     * Leave the specified workspace.
     */
    public function leave(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('leave', $workspace);

        $user = $request->user();

        $fallbackWorkspace = $user->isCurrentWorkspace($workspace)
            ? $user->fallbackWorkspace($workspace)
            : null;

        $workspace->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($fallbackWorkspace) {
            $user->switchWorkspace($fallbackWorkspace);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the workspace ":name"', ['name' => $workspace->name])]);

        return to_route('workspaces.index');
    }

    /**
     * Delete the specified workspace.
     */
    public function destroy(DeleteWorkspaceRequest $request, Workspace $workspace): RedirectResponse
    {
        $user = $request->user();
        $fallbackWorkspace = $user->isCurrentWorkspace($workspace)
            ? $user->fallbackWorkspace($workspace)
            : null;

        DB::transaction(function () use ($user, $workspace) {
            User::where('current_workspace_id', $workspace->id)
                ->where('id', '!=', $user->id)
                ->each(fn (User $affectedUser) => $affectedUser->switchWorkspace($affectedUser->personalWorkspace()));

            $workspace->invitations()->delete();
            $workspace->memberships()->delete();
            $workspace->delete();
        });

        if ($fallbackWorkspace) {
            $user->switchWorkspace($fallbackWorkspace);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace deleted.')]);

        return to_route('workspaces.index');
    }
}
