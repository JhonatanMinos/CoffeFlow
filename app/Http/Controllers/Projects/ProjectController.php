<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\CreateProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $this->currentWorkspace($request);
        Gate::authorize('viewAny', [Project::class, $workspace]);

        $paginator = $workspace->projects()
            ->select(['id', 'workspace_id', 'created_by', 'name', 'slug', 'methodology', 'status', 'created_at'])
            ->with('creator:id,name')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $projects = $paginator->toArray();
        $projects['data'] = $paginator->getCollection()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'methodology' => $project->methodology->value,
                'status' => $project->status->value,
                'createdBy' => $project->creator->name,
                'createdAt' => $project->created_at?->toISOString(),
            ])
            ->all();

        return Inertia::render('projects/index', [
            'projects' => $projects,
            'canCreateProject' => Gate::allows('create', [Project::class, $workspace]),
        ]);
    }

    public function store(StoreProjectRequest $request, CreateProject $createProject): RedirectResponse
    {
        $workspace = $this->currentWorkspace($request);
        Gate::authorize('create', [Project::class, $workspace]);

        $project = $createProject->handle($request->user(), $workspace, $request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Proyecto creado.')]);

        return to_route('projects.show', [
            'current_workspace' => $workspace->slug,
            'project' => $project->slug,
        ]);
    }

    public function show(Request $request): Response
    {
        $workspace = $this->currentWorkspace($request);
        $projectSlug = $request->route('project');

        abort_if(! is_string($projectSlug), 404);

        $projectModel = $workspace->projects()
            ->select(['id', 'workspace_id', 'name', 'slug', 'methodology', 'status'])
            ->where('slug', $projectSlug)
            ->firstOrFail();

        Gate::authorize('view', $projectModel);

        $board = $projectModel->boards()
            ->select(['id', 'project_id', 'name', 'slug', 'is_default'])
            ->where('is_default', true)
            ->with([
                'columns:id,board_id,name,category,position,wip_limit',
                'columns.tasks:id,column_id,title,description',
            ])
            ->firstOrFail();

        return Inertia::render('projects/show', [
            'project' => [
                'id' => $projectModel->id,
                'name' => $projectModel->name,
                'slug' => $projectModel->slug,
                'methodology' => $projectModel->methodology->value,
                'status' => $projectModel->status->value,
            ],
            'board' => [
                'id' => $board->id,
                'name' => $board->name,
                'slug' => $board->slug,
                'columns' => $board->columns->map(fn ($column) => [
                    'id' => $column->id,
                    'name' => $column->name,
                    'category' => $column->category->value,
                    'position' => $column->position,
                    'wipLimit' => $column->wip_limit,
                    'tasks' => $column->tasks->map(fn ($task) => [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                    ]),
                ]),
            ],
            'canCreateTask' => Gate::allows('create', [Task::class, $projectModel]),
        ]);
    }

    private function currentWorkspace(Request $request): Workspace
    {
        $workspace = $request->route('current_workspace');

        abort_if(! $workspace instanceof Workspace, 404);

        return $workspace;
    }
}
