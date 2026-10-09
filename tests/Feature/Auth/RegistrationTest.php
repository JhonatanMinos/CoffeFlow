<?php

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Inertia\Testing\AssertableInertia as Assert;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('registration screen includes workspace invitation context', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['name' => 'Laravel Workspace']);
    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);

    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this->get(route('register', ['invitation' => $invitation->code]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/register')
        ->where('workspaceInvitation.code', $invitation->code)
        ->where('workspaceInvitation.workspaceName', 'Laravel Workspace'),
    );
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'test@example.com')->first();
    $response->assertRedirect(route('dashboard'));

    $workspace = $user->personalWorkspace();

    expect($workspace)
        ->not->toBeNull()
        ->type->toBe(WorkspaceType::Personal)
        ->and($user->current_workspace_id)->toBe($workspace->id)
        ->and($user->workspaceRole($workspace))->toBe(WorkspaceRole::Owner)
        ->and($user->workspaces()->count())->toBe(1);
});

test('registration rolls back the user when personal workspace creation fails', function () {
    $createWorkspace = Mockery::mock(CreateWorkspace::class);
    $createWorkspace
        ->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Workspace creation failed.'));

    $action = new CreateNewUser($createWorkspace);

    expect(fn () => $action->create([
        'name' => 'Atomic User',
        'email' => 'atomic@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]))->toThrow(RuntimeException::class);

    $this->assertDatabaseMissing('users', ['email' => 'atomic@example.com']);
});
