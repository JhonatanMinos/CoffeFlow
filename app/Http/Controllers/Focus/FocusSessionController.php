<?php

namespace App\Http\Controllers\Focus;

use App\Actions\Focus\PauseFocusSession;
use App\Actions\Focus\RecordFocusSessionHeartbeat;
use App\Actions\Focus\ResumeFocusSession;
use App\Actions\Focus\StartFocusSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Focus\StoreFocusSessionRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function pause(Request $request, Workspace $currentWorkspace, int $focusSession, PauseFocusSession $pauseFocusSession): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);
        $result = $pauseFocusSession->handle($user, $focusSession);

        return $this->transitionResponse($result, 'No se puede pausar esta sesión de enfoque.');
    }

    public function resume(Request $request, Workspace $currentWorkspace, int $focusSession, ResumeFocusSession $resumeFocusSession): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);
        $result = $resumeFocusSession->handle($user, $focusSession);

        return $this->transitionResponse($result, 'No se puede reanudar esta sesión de enfoque.');
    }

    public function heartbeat(Request $request, Workspace $currentWorkspace, int $focusSession, RecordFocusSessionHeartbeat $heartbeat): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);
        $result = $heartbeat->handle($user, $focusSession);
        abort_if($result === null, 404);

        if ($result['status'] === 'invalid') {
            return response()->json(['message' => 'Solo se puede confirmar una sesión en ejecución.'], 422);
        }

        return response()->json($result);
    }

    private function transitionResponse(?bool $result, string $error): RedirectResponse
    {
        abort_if($result === null, 404);

        if (! $result) {
            return back()->withErrors(['focus_session' => $error]);
        }

        return back();
    }
}
