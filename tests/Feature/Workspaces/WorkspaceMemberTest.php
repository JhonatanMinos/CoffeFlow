<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

test('workspace member roles can be updated by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('workspaces.members.update', [$workspace, $member]), [
            'role' => WorkspaceRole::Admin->value,
        ]);

    $response->assertRedirect(route('workspaces.edit', $workspace));

    expect($workspace->members()->where('user_id', $member->id)->first()->pivot->role->value)->toEqual(WorkspaceRole::Admin->value);
});

test('workspace member roles cannot be updated by non owners', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);
    $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $response = $this
        ->actingAs($admin)
        ->patch(route('workspaces.members.update', [$workspace, $member]), [
            'role' => WorkspaceRole::Admin->value,
        ]);

    $response->assertForbidden();
});

test('workspace members can be removed by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('workspaces.members.destroy', [$workspace, $member]));

    $response->assertRedirect(route('workspaces.edit', $workspace));

    expect($member->fresh()->belongsToWorkspace($workspace))->toBeFalse();
});

test('workspace members cannot be removed by non owners', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);
    $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $response = $this
        ->actingAs($admin)
        ->delete(route('workspaces.members.destroy', [$workspace, $member]));

    $response->assertForbidden();
});

test('workspace owner cannot be removed', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('workspaces.members.destroy', [$workspace, $owner]));

    $response->assertForbidden();

    expect($owner->fresh()->belongsToWorkspace($workspace))->toBeTrue();
});

test('workspace member role cannot be set to owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('workspaces.members.update', [$workspace, $member]), [
            'role' => WorkspaceRole::Owner->value,
        ]);

    $response->assertSessionHasErrors('role');

    expect($workspace->members()->where('user_id', $member->id)->first()->pivot->role->value)->toEqual(WorkspaceRole::Member->value);
});

test('removed member current workspace is set to personal workspace', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $personalWorkspace = $member->personalWorkspace();
    $workspace = Workspace::factory()->create();

    $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $member->update(['current_workspace_id' => $workspace->id]);

    $this
        ->actingAs($owner)
        ->delete(route('workspaces.members.destroy', [$workspace, $member]));

    expect($member->fresh()->current_workspace_id)->toEqual($personalWorkspace->id);
});
