<?php

namespace App\Http\Controllers\Tasks;

use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\MoveTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\MoveTaskRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function move(MoveTaskRequest $request, MoveTask $moveTask): RedirectResponse
    {
        $workspace = $request->route('current_workspace');
        $projectSlug = $request->route('project');
        $taskId = $request->route('task');

        abort_if(! $workspace instanceof Workspace, 404);
        abort_if(! is_string($projectSlug) || ! is_string($taskId), 404);

        $project = $workspace->projects()->where('slug', $projectSlug)->firstOrFail();
        $task = $project->tasks()->whereKey($taskId)->firstOrFail();
        Gate::authorize('create', [Task::class, $project]);

        $moveTask->handle($task, (int) $request->validated('column_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tarea movida.')]);

        return to_route('projects.show', [
            'current_workspace' => $workspace->slug,
            'project' => $project->slug,
        ]);
    }

    public function store(StoreTaskRequest $request, CreateTask $createTask): RedirectResponse
    {
        $workspace = $this->currentWorkspace($request);
        $projectSlug = $request->route('project');

        abort_if(! is_string($projectSlug), 404);

        $project = $workspace->projects()->where('slug', $projectSlug)->firstOrFail();
        Gate::authorize('create', [Task::class, $project]);

        $validated = $request->validated();
        $createTask->handle(
            $request->user(),
            $project,
            (int) $validated['board_id'],
            (int) $validated['column_id'],
            [
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tarea creada.')]);

        return to_route('projects.show', [
            'current_workspace' => $workspace->slug,
            'project' => $project->slug,
        ]);
    }

    private function currentWorkspace(StoreTaskRequest $request): Workspace
    {
        $workspace = $request->route('current_workspace');

        abort_if(! $workspace instanceof Workspace, 404);

        return $workspace;
    }
}
