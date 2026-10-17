<?php

use App\Enums\WorkspaceRole;
use App\Models\FocusSession;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('workspace members only see projects from the requested workspace', function () {
    $user = User::factory()->create();
    $workspace = $user->personalWorkspace();
    $otherUser = User::factory()->create();

    $visibleProject = Project::factory()
        ->for($workspace)
        ->for($user, 'creator')
        ->create(['name' => 'Proyecto visible']);

    Project::factory()
        ->for($otherUser->personalWorkspace())
        ->for($otherUser, 'creator')
        ->create(['name' => 'Proyecto privado']);

    $response = $this->actingAs($user)->get(route('projects.index', $workspace));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('projects/index')
        ->has('projects.data', 1)
        ->where('projects.data.0.id', $visibleProject->id)
        ->where('projects.data.0.name', 'Proyecto visible')
        ->where('canCreateProject', true),
    );
});

test('users without workspace membership cannot list its projects', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('projects.index', $owner->personalWorkspace()))
        ->assertForbidden();
});

test('owners can create kanban projects in their workspace', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Aplicación móvil'])
        ->assertRedirect(route('projects.show', [$workspace, 'aplicacion-movil']));

    $this->assertDatabaseHas('projects', [
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'name' => 'Aplicación móvil',
        'slug' => 'aplicacion-movil',
        'methodology' => 'kanban',
        'status' => 'active',
    ]);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();

    expect($board->name)->toBe('Kanban')
        ->and($board->is_default)->toBeTrue()
        ->and($board->columns->map->category->map->value->all())->toBe([
            'todo',
            'in_progress',
            'review',
            'done',
        ]);
});

test('managers can create projects but members cannot', function () {
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);
    $workspace->members()->attach($manager, ['role' => WorkspaceRole::Manager->value]);
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $this->actingAs($manager)
        ->post(route('projects.store', $workspace), ['name' => 'Permitido'])
        ->assertRedirect(route('projects.show', [$workspace, 'permitido']));

    $this->actingAs($member)
        ->post(route('projects.store', $workspace), ['name' => 'Prohibido'])
        ->assertForbidden();

    $this->assertDatabaseHas('projects', ['name' => 'Permitido']);
    $this->assertDatabaseMissing('projects', ['name' => 'Prohibido']);
});

test('project slugs are unique inside a workspace and reusable across workspaces', function () {
    $firstOwner = User::factory()->create();
    $secondOwner = User::factory()->create();
    $firstWorkspace = $firstOwner->personalWorkspace();
    $secondWorkspace = $secondOwner->personalWorkspace();

    $this->actingAs($firstOwner)
        ->post(route('projects.store', $firstWorkspace), ['name' => 'Coffee App']);
    $this->actingAs($firstOwner)
        ->post(route('projects.store', $firstWorkspace), ['name' => 'Coffee App']);
    $this->actingAs($secondOwner)
        ->post(route('projects.store', $secondWorkspace), ['name' => 'Coffee App']);

    expect(Project::where('workspace_id', $firstWorkspace->id)->pluck('slug')->all())
        ->toBe(['coffee-app', 'coffee-app-1'])
        ->and(Project::where('workspace_id', $secondWorkspace->id)->value('slug'))
        ->toBe('coffee-app');
});

test('project name is required and bounded', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => ''])
        ->assertSessionHasErrors('name');

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => str_repeat('a', 121)])
        ->assertSessionHasErrors('name');
});

test('workspace members can view the configured project board', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Producto web']);

    $response = $this->actingAs($owner)
        ->get(route('projects.show', [$workspace, 'producto-web']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('projects/show')
        ->where('project.name', 'Producto web')
        ->where('board.name', 'Kanban')
        ->has('board.columns', 4)
        ->where('board.columns.0.category', 'todo')
        ->where('board.columns.3.category', 'done'),
    );
});

test('project board receives the owners confirmed focus state', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();
    $this->actingAs($owner)->post(route('projects.store', $workspace), ['name' => 'Producto activo']);
    $startedAt = Carbon::parse('2026-10-16 12:00:00');
    $session = FocusSession::create([
        'user_id' => $owner->id,
        'status' => 'paused',
        'started_at' => $startedAt,
        'target_duration_seconds' => 1500,
    ]);
    $session->intervals()->create([
        'started_at' => $startedAt,
        'ended_at' => $startedAt->copy()->addSeconds(45),
        'last_confirmed_at' => $startedAt->copy()->addSeconds(45),
    ]);

    $this->actingAs($owner)->get(route('projects.show', [$workspace, 'producto-activo']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeFocusSession.id', $session->id)
            ->where('activeFocusSession.taskId', null)
            ->where('activeFocusSession.status', 'paused')
            ->where('activeFocusSession.targetDurationSeconds', 1500)
            ->where('activeFocusSession.confirmedSeconds', 45)
            ->where('activeFocusSession.intervalStartedAt', null)
            ->where('activeFocusSession.lastConfirmedAt', $startedAt->copy()->addSeconds(45)->toIso8601String()),
        );
});

test('a project cannot be read through a different workspace', function () {
    $firstOwner = User::factory()->create();
    $secondOwner = User::factory()->create();
    $firstWorkspace = $firstOwner->personalWorkspace();
    $secondWorkspace = $secondOwner->personalWorkspace();

    $this->actingAs($firstOwner)
        ->post(route('projects.store', $firstWorkspace), ['name' => 'Proyecto aislado']);

    $this->actingAs($secondOwner)
        ->get(route('projects.show', [$secondWorkspace, 'proyecto-aislado']))
        ->assertNotFound();
});
