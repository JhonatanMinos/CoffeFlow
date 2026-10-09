<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetWorkspaceUrlDefaults
{
    /**
     * Set the default URL parameters for workspace-based routes.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($currentWorkspace = $request->user()?->currentWorkspace) {
            URL::defaults([
                'current_workspace' => $currentWorkspace->slug,
                'workspace' => $currentWorkspace->slug,
            ]);
        }

        return $next($request);
    }
}
