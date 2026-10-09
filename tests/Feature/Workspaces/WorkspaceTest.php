<?php

use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

test('the workspaces index page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('workspaces.index'));

    $response->assertOk();
});

test('workspaces can be created', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('workspaces.store'), [
            'name' => 'Test Workspace',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('workspaces', [
        'name' => 'Test Workspace',
        'type' => WorkspaceType::Team->value,
    ]);
});

test('workspace slug uses next available suffix', function () {
    $user = User::factory()->create();

    Workspace::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    Workspace::factory()->create(['name' => 'Acme One', 'slug' => 'acme-1']);
    Workspace::factory()->create(['name' => 'Acme Ten', 'slug' => 'acme-10']);

    $this
        ->actingAs($user)
        ->post(route('workspaces.store'), [
            'name' => 'Acme',
        ]);

    $this->assertDatabaseHas('workspaces', [
        'name' => 'Acme',
        'slug' => 'acme-11',
    ]);
});

test('the workspace edit page can be rendered', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($user, ['role' => WorkspaceRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->get(route('workspaces.edit', $workspace));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/edit')
            ->where('members.0.role', WorkspaceRole::Owner->value)
            ->where('members.0.role_label', WorkspaceRole::Owner->label()),
        );
});

test('workspaces can be updated by owners', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['name' => 'Original Name']);

    $workspace->members()->attach($user, ['role' => WorkspaceRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->patch(route('workspaces.update', $workspace), [
            'name' => 'Updated Name',
        ]);

    $response->assertRedirect(route('workspaces.edit', $workspace->fresh()));

    $this->assertDatabaseHas('workspaces', [
        'id' => $workspace->id,
        'name' => 'Updated Name',
    ]);
});

test('workspaces cannot be updated by members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $response = $this
        ->actingAs($member)
        ->patch(route('workspaces.update', $workspace), [
            'name' => 'Updated Name',
        ]);

    $response->assertForbidden();
});

test('workspaces can be deleted by owners', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($user, ['role' => WorkspaceRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->delete(route('workspaces.destroy', $workspace), [
            'name' => $workspace->name,
        ]);

    $response->assertRedirect();

    $this->assertSoftDeleted('workspaces', [
        'id' => $workspace->id,
    ]);
});

test('workspace deletion requires name confirmation', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($user, ['role' => WorkspaceRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->delete(route('workspaces.destroy', $workspace), [
            'name' => 'Wrong Name',
        ]);

    $response->assertSessionHasErrors('name');

    $this->assertDatabaseHas('workspaces', [
        'id' => $workspace->id,
        'deleted_at' => null,
    ]);
});

test('deleting current workspace switches to alphabetically first remaining workspace', function () {
    $user = User::factory()->create(['name' => 'Mike']);

    $zuluWorkspace = Workspace::factory()->create(['name' => 'Zulu Workspace']);
    $zuluWorkspace->members()->attach($user, ['role' => WorkspaceRole::Owner->value]);

    $alphaWorkspace = Workspace::factory()->create(['name' => 'Alpha Workspace']);
    $alphaWorkspace->members()->attach($user, ['role' => WorkspaceRole::Owner->value]);

    $betaWorkspace = Workspace::factory()->create(['name' => 'Beta Workspace']);
    $betaWorkspace->members()->attach($user, ['role' => WorkspaceRole::Owner->value]);

    $user->update(['current_workspace_id' => $zuluWorkspace->id]);

    $response = $this
        ->actingAs($user)
        ->delete(route('workspaces.destroy', $zuluWorkspace), [
            'name' => $zuluWorkspace->name,
        ]);

    $response->assertRedirect();

    $this->assertSoftDeleted('workspaces', [
        'id' => $zuluWorkspace->id,
    ]);

    expect($user->fresh()->current_workspace_id)->toEqual($alphaWorkspace->id);
});

test('deleting current workspace falls back to personal workspace when alphabetically first', function () {
    $user = User::factory()->create();
    $personalWorkspace = $user->personalWorkspace();
    $workspace = Workspace::factory()->create(['name' => 'Zulu Workspace']);
    $workspace->members()->attach($user, ['role' => WorkspaceRole::Owner->value]);

    $user->update(['current_workspace_id' => $workspace->id]);

    $response = $this
        ->actingAs($user)
        ->delete(route('workspaces.destroy', $workspace), [
            'name' => $workspace->name,
        ]);

    $response->assertRedirect();

    $this->assertSoftDeleted('workspaces', [
        'id' => $workspace->id,
    ]);

    expect($user->fresh()->current_workspace_id)->toEqual($personalWorkspace->id);
});

test('deleting non current workspace leaves current workspace unchanged', function () {
    $user = User::factory()->create();
    $personalWorkspace = $user->personalWorkspace();
    $workspace = Workspace::factory()->create();
    $workspace->members()->attach($user, ['role' => WorkspaceRole::Owner->value]);

    $user->update(['current_workspace_id' => $personalWorkspace->id]);

    $response = $this
        ->actingAs($user)
        ->delete(route('workspaces.destroy', $workspace), [
            'name' => $workspace->name,
        ]);

    $response->assertRedirect();

    $this->assertSoftDeleted('workspaces', [
        'id' => $workspace->id,
    ]);

    expect($user->fresh()->current_workspace_id)->toEqual($personalWorkspace->id);
});

test('members can leave non personal workspaces', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $response = $this
        ->actingAs($member)
        ->delete(route('workspaces.leave', $workspace));

    $response->assertRedirect(route('workspaces.index'));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => "You left the workspace \"{$workspace->name}\""]);

    expect($member->fresh()->belongsToWorkspace($workspace))->toBeFalse();
});

test('leaving current workspace switches to alphabetically first remaining workspace', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['name' => 'Mike']);

    $zuluWorkspace = Workspace::factory()->create(['name' => 'Zulu Workspace']);
    $zuluWorkspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);
    $zuluWorkspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $alphaWorkspace = Workspace::factory()->create(['name' => 'Alpha Workspace']);
    $alphaWorkspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $betaWorkspace = Workspace::factory()->create(['name' => 'Beta Workspace']);
    $betaWorkspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $member->update(['current_workspace_id' => $zuluWorkspace->id]);

    $response = $this
        ->actingAs($member)
        ->delete(route('workspaces.leave', $zuluWorkspace));

    $response->assertRedirect(route('workspaces.index'));

    expect($member->fresh()->belongsToWorkspace($zuluWorkspace))->toBeFalse();
    expect($member->fresh()->current_workspace_id)->toEqual($alphaWorkspace->id);
});

test('personal workspaces cannot be left', function () {
    $user = User::factory()->create();
    $personalWorkspace = $user->personalWorkspace();

    $response = $this
        ->actingAs($user)
        ->delete(route('workspaces.leave', $personalWorkspace));

    $response->assertForbidden();

    expect($user->fresh()->belongsToWorkspace($personalWorkspace))->toBeTrue();
});

test('workspace owners cannot leave their workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('workspaces.leave', $workspace));

    $response->assertForbidden();

    expect($owner->fresh()->belongsToWorkspace($workspace))->toBeTrue();
});

test('users cannot leave workspaces they dont belong to', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('workspaces.leave', $workspace));

    $response->assertForbidden();
});

test('deleting workspace switches other affected users to their personal workspace', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $workspace = Workspace::factory()->create();
    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $owner->update(['current_workspace_id' => $workspace->id]);
    $member->update(['current_workspace_id' => $workspace->id]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('workspaces.destroy', $workspace), [
            'name' => $workspace->name,
        ]);

    $response->assertRedirect();

    expect($member->fresh()->current_workspace_id)->toEqual($member->personalWorkspace()->id);
});

test('personal workspaces cannot be deleted', function () {
    $user = User::factory()->create();

    $personalWorkspace = $user->personalWorkspace();

    $response = $this
        ->actingAs($user)
        ->delete(route('workspaces.destroy', $personalWorkspace), [
            'name' => $personalWorkspace->name,
        ]);

    $response->assertForbidden();

    $this->assertDatabaseHas('workspaces', [
        'id' => $personalWorkspace->id,
        'deleted_at' => null,
    ]);
});

test('workspaces cannot be deleted by non owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $response = $this
        ->actingAs($member)
        ->delete(route('workspaces.destroy', $workspace), [
            'name' => $workspace->name,
        ]);

    $response->assertForbidden();
});

test('users can switch workspaces', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($user, ['role' => WorkspaceRole::Member->value]);

    $response = $this
        ->actingAs($user)
        ->post(route('workspaces.switch', $workspace));

    $response->assertRedirect();

    expect($user->fresh()->current_workspace_id)->toEqual($workspace->id);
});

test('users cannot switch to workspace they dont belong to', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('workspaces.switch', $workspace));

    $response->assertForbidden();
});

test('guests cannot access workspaces', function () {
    $response = $this->get(route('workspaces.index'));

    $response->assertRedirect(route('login'));
});
