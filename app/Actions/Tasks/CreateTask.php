<?php

namespace App\Actions\Tasks;

use App\Enums\BoardColumnCategory;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTask
{
    /**
     * @param  array{title: string, description?: string|null}  $data
     */
    public function handle(User $creator, Project $project, int $boardId, int $columnId, array $data): Task
    {
        return DB::transaction(function () use ($creator, $project, $boardId, $columnId, $data): Task {
            $board = Board::query()->whereKey($boardId)->first();

            if (! $board || $board->project_id !== $project->id) {
                throw ValidationException::withMessages([
                    'board_id' => 'El tablero no pertenece a este proyecto.',
                ]);
            }

            $column = BoardColumn::query()
                ->whereKey($columnId)
                ->where('board_id', $board->id)
                ->lockForUpdate()
                ->first();

            if (! $column) {
                throw ValidationException::withMessages([
                    'column_id' => 'La columna no pertenece al tablero seleccionado.',
                ]);
            }

            if ($column->category === BoardColumnCategory::Done) {
                throw ValidationException::withMessages([
                    'column_id' => 'Las tareas solo se finalizan mediante la acción explícita de completar.',
                ]);
            }

            if (
                $column->wip_limit !== null &&
                Task::query()->where('column_id', $column->id)->count() >= $column->wip_limit
            ) {
                throw ValidationException::withMessages([
                    'column_id' => 'La columna alcanzó su límite WIP.',
                ]);
            }

            return $project->tasks()->create([
                'column_id' => $column->id,
                'created_by' => $creator->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
            ]);
        });
    }
}
