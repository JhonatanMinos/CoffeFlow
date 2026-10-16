<?php

namespace App\Actions\Focus;

use App\Models\FocusSession;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartFocusSession
{
    public function handle(User $user, ?Task $task): ?FocusSession
    {
        try {
            return DB::transaction(function () use ($user, $task): ?FocusSession {
                if ($task !== null) {
                    $task = Task::query()->lockForUpdate()->findOrFail($task->id);

                    if ($task->archived_at !== null) {
                        throw ValidationException::withMessages([
                            'task_id' => 'No se puede iniciar una sesión en una tarea archivada.',
                        ]);
                    }
                }

                if (FocusSession::query()
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['running', 'paused'])
                    ->exists()) {
                    return null;
                }

                $startedAt = now();
                $session = FocusSession::query()->create([
                    'user_id' => $user->id,
                    'task_id' => $task?->id,
                    'status' => 'running',
                    'started_at' => $startedAt,
                ]);

                $session->intervals()->create(['started_at' => $startedAt]);

                return $session;
            });
        } catch (QueryException $exception) {
            if ($this->isActiveSessionConflict($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    private function isActiveSessionConflict(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $message = $exception->getMessage();

        return in_array($sqlState, ['23000', '23505'], true)
            && (
                str_contains($message, 'focus_sessions_one_active_per_user')
                || str_contains($message, 'focus_sessions.user_id')
            );
    }
}
