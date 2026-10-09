<?php

namespace App\Actions\Projects;

use App\Actions\Boards\ConfigureKanbanBoard;
use App\Enums\ProjectMethodology;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateProject
{
    public function __construct(private ConfigureKanbanBoard $configureKanbanBoard)
    {
        //
    }

    public function handle(User $creator, Workspace $workspace, string $name): Project
    {
        return DB::transaction(function () use ($creator, $workspace, $name): Project {
            $lockedWorkspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);

            $project = $lockedWorkspace->projects()->create([
                'created_by' => $creator->id,
                'name' => $name,
                'methodology' => ProjectMethodology::Kanban,
                'status' => ProjectStatus::Active,
            ]);

            $this->configureKanbanBoard->handle($project);

            return $project;
        });
    }
}
