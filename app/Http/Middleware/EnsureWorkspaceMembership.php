<?php

namespace App\Http\Middleware;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceMembership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $minimumRole = null): Response
    {
        [$user, $workspace] = [$request->user(), $this->workspace($request)];

        abort_if(! $user || ! $workspace || ! $user->belongsToWorkspace($workspace), 403);

        if ($request->route('current_workspace')) {
            $request->route()->setParameter('current_workspace', $workspace);
        }

        if ($request->route('workspace')) {
            $request->route()->setParameter('workspace', $workspace);
        }

        $this->ensureWorkspaceMemberHasRequiredRole($user, $workspace, $minimumRole);

        if ($request->route('current_workspace') && ! $user->isCurrentWorkspace($workspace)) {
            $user->switchWorkspace($workspace);
        }

        return $next($request);
    }

    /**
     * Ensure the given user has at least the given role, if applicable.
     */
    protected function ensureWorkspaceMemberHasRequiredRole(User $user, Workspace $workspace, ?string $minimumRole): void
    {
        if ($minimumRole === null) {
            return;
        }

        $role = $user->workspaceRole($workspace);

        $requiredRole = WorkspaceRole::tryFrom($minimumRole);

        abort_if(
            $requiredRole === null ||
            $role === null ||
            ! $role->isAtLeast($requiredRole),
            403,
        );
    }

    /**
     * Get the workspace associated with the request.
     */
    protected function workspace(Request $request): ?Workspace
    {
        $workspace = $request->route('current_workspace') ?? $request->route('workspace');

        if (is_string($workspace)) {
            $workspace = Workspace::where('slug', $workspace)->first();
        }

        return $workspace;
    }
}
