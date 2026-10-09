<?php

namespace App\Actions\Boards;

use App\Enums\BoardColumnCategory;
use App\Models\Board;
use App\Models\Project;

class ConfigureKanbanBoard
{
    public function handle(Project $project): Board
    {
        $board = $project->boards()->create([
            'name' => 'Kanban',
            'slug' => 'kanban',
            'is_default' => true,
        ]);

        $board->columns()->createMany([
            ['name' => 'Pendiente', 'category' => BoardColumnCategory::Todo, 'position' => 0],
            ['name' => 'En progreso', 'category' => BoardColumnCategory::InProgress, 'position' => 1],
            ['name' => 'En revisión', 'category' => BoardColumnCategory::Review, 'position' => 2],
            ['name' => 'Terminado', 'category' => BoardColumnCategory::Done, 'position' => 3],
        ]);

        return $board;
    }
}
