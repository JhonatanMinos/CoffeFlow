<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateTask
{
    /** @param array{title: string, description?: string|null} $data */
    public function handle(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);

            if ($lockedTask->archived_at !== null) {
                throw ValidationException::withMessages([
                    'task' => 'No se pueden editar tareas archivadas.',
                ]);
            }

            $lockedTask->fill([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
            ]);
            $lockedTask->save();

            return $lockedTask;
        });
    }
}
