<?php

use App\Enums\WorkspaceRole;
use App\Models\FocusSession;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $workspace = $user->currentWorkspace;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $workspace = $user->currentWorkspace;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

test('dashboard exposes only the owners confirmed focus time and nullable target', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $startedAt = Carbon::parse('2026-10-16 12:00:00');
    $session = FocusSession::create([
        'user_id' => $owner->id,
        'status' => 'running',
        'started_at' => $startedAt,
        'target_duration_seconds' => null,
    ]);
    $session->intervals()->create([
        'started_at' => $startedAt,
        'ended_at' => $startedAt->copy()->addSeconds(30),
        'last_confirmed_at' => $startedAt->copy()->addSeconds(30),
    ]);
    $session->intervals()->create([
        'started_at' => $startedAt->copy()->addMinute(),
        'last_confirmed_at' => $startedAt->copy()->addSeconds(80),
    ]);
    FocusSession::create([
        'user_id' => $other->id,
        'status' => 'running',
        'started_at' => $startedAt,
        'target_duration_seconds' => 1500,
    ])->intervals()->create([
        'started_at' => $startedAt,
        'last_confirmed_at' => $startedAt->copy()->addMinutes(10),
    ]);

    $this->actingAs($owner)->get(route('dashboard', $owner->personalWorkspace()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeFocusSession.id', $session->id)
            ->where('activeFocusSession.status', 'running')
            ->where('activeFocusSession.targetDurationSeconds', null)
            ->where('activeFocusSession.confirmedSeconds', 50)
            ->where('activeFocusSession.intervalStartedAt', $startedAt->copy()->addMinute()->toIso8601String())
            ->where('activeFocusSession.lastConfirmedAt', $startedAt->copy()->addSeconds(80)->toIso8601String())
            ->missing('activeFocusSession.userId')
            ->missing('activeFocusSession.otherUserId'),
        );
});

test('dashboard includes pending invitations for the authenticated user', function () {
    $owner = User::factory()->create(['name' => 'Taylor Otwell']);
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $workspace = Workspace::factory()->create(['name' => 'Laravel Workspace']);

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);

    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 1)
        ->where('pendingInvitations.0.code', $invitation->code)
        ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
        ->where('pendingInvitations.0.workspace.name', 'Laravel Workspace')
        ->where('pendingInvitations.0.workspace.slug', $workspace->slug)
        ->missing('pendingInvitations.0.workspaceName'),
    );
});

test('dashboard does not include accepted invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);

    WorkspaceInvitation::factory()->accepted()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 0),
    );
});

test('dashboard excludes expired invitations without deleting them', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);

    $invitation = WorkspaceInvitation::factory()->expired()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('workspace_invitations', [
        'id' => $invitation->id,
    ]);
});

test('dashboard does not include or delete other users invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);

    $invitation = WorkspaceInvitation::factory()->expired()->create([
        'workspace_id' => $workspace->id,
        'email' => 'someone@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('workspace_invitations', [
        'id' => $invitation->id,
    ]);
});
