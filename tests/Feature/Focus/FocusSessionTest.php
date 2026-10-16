<?php

use App\Enums\WorkspaceRole;
use App\Models\FocusSession;
use App\Models\FocusSessionInterval;
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

test('the owner can pause a running focus session and close its active interval', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $startedAt = Carbon::parse('2026-10-10 12:00:00');
    $pausedAt = $startedAt->copy()->addMinutes(25);
    Carbon::setTestNow($startedAt);
    $this->actingAs($user)->post(route('focus-sessions.store', $workspace))->assertRedirect();
    $session = FocusSession::firstOrFail();

    Carbon::setTestNow($pausedAt);
    $this->actingAs($user)
        ->post(route('focus-sessions.pause', [$workspace, $session]))
        ->assertRedirect();

    $this->assertDatabaseHas('focus_sessions', ['id' => $session->id, 'status' => 'paused']);
    $this->assertDatabaseHas('focus_session_intervals', [
        'focus_session_id' => $session->id,
        'started_at' => $startedAt->toDateTimeString(),
        'ended_at' => $pausedAt->toDateTimeString(),
    ]);
});

test('a legacy running focus session without intervals can be paused without backfilling time', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $session = FocusSession::create([
        'user_id' => $user->id,
        'status' => 'running',
        'started_at' => Carbon::parse('2026-10-09 12:00:00'),
    ]);

    $this->actingAs($user)
        ->post(route('focus-sessions.pause', [$workspace, $session]))
        ->assertRedirect();

    $this->assertDatabaseHas('focus_sessions', ['id' => $session->id, 'status' => 'paused']);
    $this->assertDatabaseCount('focus_session_intervals', 0);
});

test('the owner can resume a paused focus session with a new open interval', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $startedAt = Carbon::parse('2026-10-10 12:00:00');
    $pausedAt = $startedAt->copy()->addMinutes(25);
    $resumedAt = $pausedAt->copy()->addMinutes(5);
    Carbon::setTestNow($startedAt);
    $this->actingAs($user)->post(route('focus-sessions.store', $workspace))->assertRedirect();
    $session = FocusSession::firstOrFail();
    Carbon::setTestNow($pausedAt);
    $this->actingAs($user)->post(route('focus-sessions.pause', [$workspace, $session]))->assertRedirect();

    Carbon::setTestNow($resumedAt);
    $this->actingAs($user)
        ->post(route('focus-sessions.resume', [$workspace, $session]))
        ->assertRedirect();

    $this->assertDatabaseHas('focus_sessions', ['id' => $session->id, 'status' => 'running']);
    $this->assertDatabaseCount('focus_session_intervals', 2);
    $this->assertDatabaseHas('focus_session_intervals', [
        'focus_session_id' => $session->id,
        'started_at' => $resumedAt->toDateTimeString(),
        'ended_at' => null,
    ]);
});

test('a legacy paused focus session resumes with a fresh interval and no historical time', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $session = FocusSession::create([
        'user_id' => $user->id,
        'status' => 'paused',
        'started_at' => Carbon::parse('2026-10-09 12:00:00'),
    ]);
    $resumedAt = Carbon::parse('2026-10-10 12:00:00');
    Carbon::setTestNow($resumedAt);

    $this->actingAs($user)
        ->post(route('focus-sessions.resume', [$workspace, $session]))
        ->assertRedirect();

    $this->assertDatabaseHas('focus_sessions', ['id' => $session->id, 'status' => 'running']);
    $this->assertDatabaseCount('focus_session_intervals', 1);
    $this->assertDatabaseHas('focus_session_intervals', [
        'focus_session_id' => $session->id,
        'started_at' => $resumedAt->toDateTimeString(),
        'ended_at' => null,
    ]);
});

test('repeated or invalid focus session transitions leave session intervals unchanged', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00'));
    $this->actingAs($user)->post(route('focus-sessions.store', $workspace))->assertRedirect();
    $session = FocusSession::firstOrFail();
    $interval = FocusSessionInterval::firstOrFail();

    $this->actingAs($user)->post(route('focus-sessions.pause', [$workspace, $session]))->assertRedirect();
    $pausedInterval = FocusSessionInterval::firstOrFail();
    $this->actingAs($user)
        ->post(route('focus-sessions.pause', [$workspace, $session]))
        ->assertRedirect()
        ->assertSessionHasErrors('focus_session');

    $this->assertDatabaseHas('focus_sessions', ['id' => $session->id, 'status' => 'paused']);
    $this->assertDatabaseHas('focus_session_intervals', ['id' => $interval->id, 'ended_at' => $pausedInterval->ended_at]);
    $this->assertDatabaseCount('focus_session_intervals', 1);

    $this->actingAs($user)->post(route('focus-sessions.resume', [$workspace, $session]))->assertRedirect();
    $this->actingAs($user)
        ->post(route('focus-sessions.resume', [$workspace, $session]))
        ->assertRedirect()
        ->assertSessionHasErrors('focus_session');
    $this->assertDatabaseHas('focus_sessions', ['id' => $session->id, 'status' => 'running']);
    $this->assertDatabaseCount('focus_session_intervals', 2);
});

test('a workspace member cannot pause another users focus session', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = $owner->personalWorkspace();
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);
    $session = FocusSession::create([
        'user_id' => $member->id,
        'status' => 'running',
        'started_at' => now(),
    ]);
    $interval = FocusSessionInterval::create([
        'focus_session_id' => $session->id,
        'started_at' => now(),
    ]);

    $this->actingAs($owner)
        ->post(route('focus-sessions.pause', [$workspace, $session]))
        ->assertNotFound();

    $this->assertDatabaseHas('focus_sessions', ['id' => $session->id, 'status' => 'running']);
    $this->assertDatabaseHas('focus_session_intervals', ['id' => $interval->id, 'ended_at' => null]);
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

test('a workspace member cannot start focus on an archived task', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = $owner->personalWorkspace();
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);
    $this->actingAs($owner)->post(route('projects.store', $workspace), ['name' => 'Focus archivado']);
    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $board->columns()->firstOrFail()->id,
        'created_by' => $owner->id,
    ]);
    $task->forceFill(['archived_at' => now()])->save();

    $this->actingAs($member)
        ->post(route('focus-sessions.store', $workspace), ['task_id' => $task->id])
        ->assertSessionHasErrors('task_id');

    $this->assertDatabaseCount('focus_sessions', 0);
});
