<?php

namespace App\Actions\Tasks;

use App\Enums\BoardColumnCategory;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveTask
{
    public function handle(Task $task, int $targetColumnId): Task
    {
        return DB::transaction(function () use ($task, $targetColumnId): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);

            if ($lockedTask->archived_at !== null) {
                throw ValidationException::withMessages([
                    'column_id' => 'No se pueden mover tareas archivadas.',
                ]);
            }

            $targetColumn = BoardColumn::query()
                ->lockForUpdate()
                ->findOrFail($targetColumnId);
            $sourceColumn = BoardColumn::query()->findOrFail($lockedTask->column_id);
            $targetBoard = Board::query()->findOrFail($targetColumn->board_id);

            if ($targetColumn->category === BoardColumnCategory::Done) {
                throw ValidationException::withMessages([
                    'column_id' => 'Las tareas solo se finalizan mediante la acción explícita de completar.',
                ]);
            }

            if ($sourceColumn->category === BoardColumnCategory::Done) {
                throw ValidationException::withMessages([
                    'column_id' => 'Las tareas completadas no se reabren mediante movimiento.',
                ]);
            }

            if (
                $sourceColumn->board_id !== $targetColumn->board_id ||
                $targetBoard->project_id !== $lockedTask->project_id
            ) {
                throw ValidationException::withMessages([
                    'column_id' => 'La columna de destino debe pertenecer al mismo tablero y proyecto.',
                ]);
            }

            if ($lockedTask->column_id === $targetColumn->id) {
                return $lockedTask;
            }

            if (
                $targetColumn->wip_limit !== null &&
                Task::query()->where('column_id', $targetColumn->id)->whereNull('archived_at')->count() >= $targetColumn->wip_limit
            ) {
                throw ValidationException::withMessages([
                    'column_id' => 'La columna alcanzó su límite WIP.',
                ]);
            }

            $lockedTask->column_id = $targetColumn->id;
            $lockedTask->save();

            return $lockedTask;
        });
    }
}
