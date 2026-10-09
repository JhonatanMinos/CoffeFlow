<?php

use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('workspace members can create and see tasks grouped in a project board column', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto tareas']);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $column = $board->columns()->where('category', 'todo')->firstOrFail();

    $this->actingAs($owner)
        ->post(route('tasks.store', [$workspace, $project->slug]), [
            'title' => 'Preparar lanzamiento',
            'description' => 'Revisar los pasos pendientes.',
            'board_id' => $board->id,
            'column_id' => $column->id,
        ])
        ->assertRedirect(route('projects.show', [$workspace, $project->slug]));

    $this->assertDatabaseHas('tasks', [
        'project_id' => $project->id,
        'column_id' => $column->id,
        'created_by' => $owner->id,
        'title' => 'Preparar lanzamiento',
    ]);

    $this->actingAs($owner)
        ->get(route('projects.show', [$workspace, $project->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/show')
            ->where('board.columns.0.tasks.0.title', 'Preparar lanzamiento')
            ->where('board.columns.0.tasks.0.description', 'Revisar los pasos pendientes.')
            ->where('canCreateTask', true),
        );
});

test('outsiders cannot create or observe project tasks', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto privado']);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $column = $board->columns()->firstOrFail();

    $this->actingAs($outsider)
        ->post(route('tasks.store', [$workspace, $project->slug]), [
            'title' => 'Tarea ajena',
            'board_id' => $board->id,
            'column_id' => $column->id,
        ])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->get(route('projects.show', [$workspace, $project->slug]))
        ->assertForbidden();

    $this->assertDatabaseMissing('tasks', ['title' => 'Tarea ajena']);
});

test('task creation rejects boards and columns outside the project scope', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto principal']);
    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto externo']);

    $projects = Project::where('workspace_id', $workspace->id)->orderBy('id')->get();
    $project = $projects->firstOrFail();
    $otherProject = $projects->last();
    $board = $project->boards()->firstOrFail();
    $column = $board->columns()->firstOrFail();
    $otherBoard = $otherProject->boards()->firstOrFail();
    $otherColumn = $otherBoard->columns()->firstOrFail();

    $this->actingAs($owner)
        ->post(route('tasks.store', [$workspace, $project->slug]), [
            'title' => 'Tablero incorrecto',
            'board_id' => $otherBoard->id,
            'column_id' => $column->id,
        ])
        ->assertSessionHasErrors('board_id');

    $this->actingAs($owner)
        ->post(route('tasks.store', [$workspace, $project->slug]), [
            'title' => 'Columna incorrecta',
            'board_id' => $board->id,
            'column_id' => $otherColumn->id,
        ])
        ->assertSessionHasErrors('column_id');

    $this->assertDatabaseCount('tasks', 0);
});

test('task titles are required and bounded', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto validación']);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $column = $board->columns()->firstOrFail();

    $this->actingAs($owner)
        ->post(route('tasks.store', [$workspace, $project->slug]), [
            'title' => str_repeat('a', 161),
            'board_id' => $board->id,
            'column_id' => $column->id,
        ])
        ->assertSessionHasErrors('title');

    $this->assertDatabaseCount('tasks', 0);
});

test('task creation rejects the done column to preserve explicit completion', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto terminado']);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $doneColumn = $board->columns()->where('category', 'done')->firstOrFail();

    $this->actingAs($owner)
        ->post(route('tasks.store', [$workspace, $project->slug]), [
            'title' => 'Evitar el cierre implícito',
            'board_id' => $board->id,
            'column_id' => $doneColumn->id,
        ])
        ->assertSessionHasErrors('column_id');

    $this->assertDatabaseCount('tasks', 0);
});
