<?php

use App\Enums\WorkspaceRole;
use App\Models\FocusSession;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard exposes the current users active focus session', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();

    $this->actingAs($user)
        ->get(route('dashboard', $workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('activeFocusSession', null),
        );

    $session = FocusSession::create([
        'user_id' => $user->id,
        'status' => 'running',
        'started_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', $workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('activeFocusSession.id', $session->id)
            ->where('activeFocusSession.status', 'running')
            ->where('activeFocusSession.taskId', null),
        );
});

test('project board exposes the current users active task-linked focus session', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $this->actingAs($user)->post(route('projects.store', $workspace), ['name' => 'Focus board']);
    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $board->columns()->firstOrFail()->id,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('projects.show', [$workspace, $project->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/show')
            ->where('activeFocusSession', null),
        );

    $session = FocusSession::create([
        'user_id' => $user->id,
        'task_id' => $task->id,
        'status' => 'paused',
        'started_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('projects.show', [$workspace, $project->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/show')
            ->where('activeFocusSession.id', $session->id)
            ->where('activeFocusSession.status', 'paused')
            ->where('activeFocusSession.taskId', $task->id),
        );
});

test('an authenticated user can start a free focus session', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $startedAt = Carbon::parse('2026-10-10 12:00:00');
    Carbon::setTestNow($startedAt);

    $this->actingAs($user)
        ->post(route('focus-sessions.store', $workspace))
        ->assertRedirect();

    $this->assertDatabaseHas('focus_sessions', [
        'user_id' => $user->id,
        'task_id' => null,
        'status' => 'running',
        'started_at' => $startedAt->toDateTimeString(),
    ]);
});

test('a workspace member can start a focus session linked to an accessible task', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = $owner->personalWorkspace();
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $this->actingAs($owner)->post(route('projects.store', $workspace), ['name' => 'Focus project']);
    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $board->columns()->firstOrFail()->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($member)
        ->withHeader('referer', route('projects.show', [$workspace, $project->slug]))
        ->post(route('focus-sessions.store', $workspace), ['task_id' => $task->id])
        ->assertRedirect(route('projects.show', [$workspace, $project->slug]));

    $this->assertDatabaseHas('focus_sessions', [
        'user_id' => $member->id,
        'task_id' => $task->id,
        'status' => 'running',
    ]);
});

test('a task outside the active workspace cannot be linked to a focus session', function () {
    $member = User::factory()->create();
    $taskOwner = User::factory()->create();
    $workspace = $member->personalWorkspace();
    $otherWorkspace = $taskOwner->personalWorkspace();

    $this->actingAs($taskOwner)->post(route('projects.store', $otherWorkspace), ['name' => 'Other project']);
    $project = Project::where('workspace_id', $otherWorkspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $board->columns()->firstOrFail()->id,
        'created_by' => $taskOwner->id,
    ]);

    $this->actingAs($member)
        ->post(route('focus-sessions.store', $workspace), ['task_id' => $task->id])
        ->assertNotFound();

    $this->actingAs($member)
        ->post(route('focus-sessions.store', $workspace), ['task_id' => $task->id + 100000])
        ->assertNotFound();

    $this->assertDatabaseCount('focus_sessions', 0);
});

test('focus session start rejects malformed task identifiers', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();

    $this->actingAs($user)
        ->post(route('focus-sessions.store', $workspace), ['task_id' => 'not-an-id'])
        ->assertSessionHasErrors('task_id');

    $this->assertDatabaseCount('focus_sessions', 0);
});

test('a zero task identifier is not treated as a free focus session', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();

    $this->actingAs($user)
        ->post(route('focus-sessions.store', $workspace), ['task_id' => 0])
        ->assertNotFound();

    $this->assertDatabaseCount('focus_sessions', 0);
});

test('a user cannot start a second active focus session', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();

    $this->actingAs($user)->post(route('focus-sessions.store', $workspace))->assertRedirect();
    $this->actingAs($user)
        ->post(route('focus-sessions.store', $workspace))
        ->assertSessionHasErrors('focus_session');

    $this->assertDatabaseCount('focus_sessions', 1);
});

test('the database prevents concurrent active focus sessions for the same user', function () {
    $user = User::factory()->create();
    $startedAt = now();

    FocusSession::create(['user_id' => $user->id, 'status' => 'running', 'started_at' => $startedAt]);

    expect(fn () => DB::transaction(fn () => FocusSession::create([
        'user_id' => $user->id,
        'status' => 'paused',
        'started_at' => $startedAt,
    ])))->toThrow(QueryException::class);

    $this->assertDatabaseHas('focus_sessions', ['user_id' => $user->id, 'status' => 'running']);
    $this->assertDatabaseCount('focus_sessions', 1);
});
