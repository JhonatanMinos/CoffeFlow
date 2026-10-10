<?php

namespace App\Http\Controllers\Tasks;

use App\Actions\Tasks\CreateTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TaskController extends Controller
{
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
