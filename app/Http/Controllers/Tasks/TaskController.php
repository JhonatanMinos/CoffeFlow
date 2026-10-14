<?php

namespace App\Http\Controllers\Tasks;

use App\Actions\Tasks\ArchiveTask;
use App\Actions\Tasks\CompleteTask;
use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\MoveTask;
use App\Actions\Tasks\UpdateTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\MoveTaskRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function archive(Request $request, ArchiveTask $archiveTask): RedirectResponse
    {
        $workspace = $request->route('current_workspace');
        $projectSlug = $request->route('project');
        $taskId = $request->route('task');

        abort_if(! $workspace instanceof Workspace, 404);
        abort_if(! is_string($projectSlug) || ! is_string($taskId), 404);

        $project = $workspace->projects()->where('slug', $projectSlug)->firstOrFail();
        $task = $project->tasks()->whereKey($taskId)->firstOrFail();
        Gate::authorize('archive', [Task::class, $project]);
        $archiveTask->handle($task);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tarea archivada.')]);

        return to_route('projects.show', [
            'current_workspace' => $workspace->slug,
            'project' => $project->slug,
        ]);
    }

    public function update(UpdateTaskRequest $request, UpdateTask $updateTask): RedirectResponse
    {
        $workspace = $request->route('current_workspace');
        $projectSlug = $request->route('project');
        $taskId = $request->route('task');

        abort_if(! $workspace instanceof Workspace, 404);
        abort_if(! is_string($projectSlug) || ! is_string($taskId), 404);

        $project = $workspace->projects()->where('slug', $projectSlug)->firstOrFail();
        $task = $project->tasks()->whereKey($taskId)->firstOrFail();
        Gate::authorize('update', [Task::class, $project]);
        $updateTask->handle($task, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tarea actualizada.')]);

        return to_route('projects.show', [
            'current_workspace' => $workspace->slug,
            'project' => $project->slug,
        ]);
    }

    public function complete(Request $request, CompleteTask $completeTask): RedirectResponse
    {
        $workspace = $request->route('current_workspace');
        $projectSlug = $request->route('project');
        $taskId = $request->route('task');

        abort_if(! $workspace instanceof Workspace, 404);
        abort_if(! is_string($projectSlug) || ! is_string($taskId), 404);

        $project = $workspace->projects()->where('slug', $projectSlug)->firstOrFail();
        $task = $project->tasks()->whereKey($taskId)->firstOrFail();
        Gate::authorize('complete', [Task::class, $project]);

        $user = $request->user();
        abort_if(! $user instanceof User, 403);
        $completeTask->handle($task, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tarea completada.')]);

        return to_route('projects.show', [
            'current_workspace' => $workspace->slug,
            'project' => $project->slug,
        ]);
    }

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
