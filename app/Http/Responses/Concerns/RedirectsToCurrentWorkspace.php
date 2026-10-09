<?php

namespace App\Http\Responses\Concerns;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentWorkspace
{
    protected function redirectPathForCurrentWorkspace(Request $request, string $redirect): string
    {
        $workspace = $this->currentWorkspace($request);

        URL::defaults(['current_workspace' => $workspace->slug]);

        return "/{$workspace->slug}{$redirect}";
    }

    protected function currentWorkspace(Request $request): Workspace
    {
        $user = $request->user();

        abort_if(! $user, 403);

        $workspace = $user->currentWorkspace ?? $user->personalWorkspace();

        abort_if(! $workspace, 403);

        return $workspace;
    }
}
