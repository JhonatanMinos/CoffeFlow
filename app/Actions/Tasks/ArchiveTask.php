<?php

namespace App\Actions\Tasks;

use App\Models\FocusSession;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchiveTask
{
    public function handle(Task $task): Task
    {
        return DB::transaction(function () use ($task): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);

            if ($lockedTask->archived_at !== null) {
                return $lockedTask;
            }

            if (FocusSession::query()
                ->where('task_id', $lockedTask->id)
                ->whereIn('status', ['running', 'paused'])
                ->exists()) {
                throw ValidationException::withMessages([
                    'task' => 'No se puede archivar una tarea con una sesión de enfoque activa.',
                ]);
            }

            $lockedTask->archived_at = now();
            $lockedTask->save();

            return $lockedTask;
        });
    }
}
