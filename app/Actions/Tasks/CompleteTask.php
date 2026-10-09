<?php

namespace App\Actions\Tasks;

use App\Enums\BoardColumnCategory;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteTask
{
    public function handle(Task $task, User $completer): Task
    {
        return DB::transaction(function () use ($task, $completer): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
            $sourceColumn = BoardColumn::query()->findOrFail($lockedTask->column_id);
            $board = Board::query()->findOrFail($sourceColumn->board_id);

            if ($board->project_id !== $lockedTask->project_id) {
                throw ValidationException::withMessages([
                    'column_id' => 'La tarea debe pertenecer al tablero de su proyecto.',
                ]);
            }

            $doneColumnQuery = BoardColumn::query()
                ->where('board_id', $board->id)
                ->where('category', BoardColumnCategory::Done->value)
                ->orderBy('position')
                ->lockForUpdate();

            $doneColumn = $sourceColumn->category === BoardColumnCategory::Done
                ? (clone $doneColumnQuery)->whereKey($sourceColumn->id)->first()
                : $doneColumnQuery->first();

            if ($doneColumn === null) {
                throw ValidationException::withMessages([
                    'column_id' => 'El tablero no tiene una columna compatible para completar tareas.',
                ]);
            }

            $hasCompletionTimestamp = $lockedTask->completed_at !== null;
            $hasCompletionAuthor = $lockedTask->completed_by !== null;

            if ($hasCompletionTimestamp && $hasCompletionAuthor) {
                if ($sourceColumn->category === BoardColumnCategory::Done) {
                    return $lockedTask;
                }

                throw ValidationException::withMessages([
                    'column_id' => 'Los datos de finalización de la tarea no son coherentes.',
                ]);
            }

            if ($hasCompletionTimestamp || $hasCompletionAuthor) {
                throw ValidationException::withMessages([
                    'column_id' => 'Los datos de finalización de la tarea no son coherentes.',
                ]);
            }

            if (
                $sourceColumn->id !== $doneColumn->id &&
                $doneColumn->wip_limit !== null &&
                Task::query()->where('column_id', $doneColumn->id)->count() >= $doneColumn->wip_limit
            ) {
                throw ValidationException::withMessages([
                    'column_id' => 'La columna alcanzó su límite WIP.',
                ]);
            }

            $lockedTask->column_id = $doneColumn->id;
            $lockedTask->completed_at = now();
            $lockedTask->completed_by = $completer->id;
            $lockedTask->save();

            return $lockedTask;
        });
    }
}
