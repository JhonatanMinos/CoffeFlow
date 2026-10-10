<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\Workspaces\WorkspaceInvitationController;
use App\Http\Middleware\EnsureWorkspaceMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_workspace}')
    ->middleware(['auth', 'verified', EnsureWorkspaceMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::post('projects/{project}/tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
        Route::post('projects/{project}/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [WorkspaceInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [WorkspaceInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
