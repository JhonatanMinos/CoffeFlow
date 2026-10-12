<?php

namespace App\Http\Controllers\Focus;

use App\Actions\Focus\StartFocusSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Focus\StoreFocusSessionRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class FocusSessionController extends Controller
{
    public function store(StoreFocusSessionRequest $request, StartFocusSession $startFocusSession): RedirectResponse
    {
        $workspace = $request->route('current_workspace');
        abort_if(! $workspace instanceof Workspace, 404);

        $task = null;
        if (($taskId = $request->validated('task_id')) !== null) {
            $task = Task::query()
                ->whereHas('project', fn ($query) => $query->where('workspace_id', $workspace->id))
                ->whereKey($taskId)
                ->firstOrFail();

            Gate::authorize('view', $task);
        }

        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        if (! $startFocusSession->handle($user, $task)) {
            return back()->withErrors([
                'focus_session' => 'Ya tienes una sesión de enfoque activa.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sesión de enfoque iniciada.')]);

        return back();
    }
}
