<?php

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
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

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $column->id,
        'created_by' => $owner->id,
    ]);
    $target = $board->columns()->where('category', 'in_progress')->firstOrFail();

    $this->actingAs($outsider)
        ->post(route('tasks.move', [$workspace, $project->slug, $task->id]), [
            'column_id' => $target->id,
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'column_id' => $column->id]);
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

test('workspace members can move a task between columns on its board', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto movimiento']);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $source = $board->columns()->where('category', 'todo')->firstOrFail();
    $target = $board->columns()->where('category', 'in_progress')->firstOrFail();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $source->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('tasks.move', [$workspace, $project->slug, $task->id]), [
            'column_id' => $target->id,
        ])
        ->assertRedirect(route('projects.show', [$workspace, $project->slug]));

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'column_id' => $target->id]);
});

test('task movement rejects columns from another board or project', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto movimiento seguro']);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $source = $board->columns()->where('category', 'todo')->firstOrFail();
    $otherBoard = Board::factory()->for($project)->create([
        'slug' => 'secundario',
        'is_default' => false,
    ]);
    $otherBoardColumn = BoardColumn::factory()->for($otherBoard)->create();

    $otherProject = Project::factory()->for($workspace)->for($owner, 'creator')->create();
    $otherProjectBoard = $otherProject->boards()->first();
    if (! $otherProjectBoard) {
        $otherProjectBoard = Board::factory()->for($otherProject)->create();
    }
    $otherProjectColumn = BoardColumn::factory()->for($otherProjectBoard)->create();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $source->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('tasks.move', [$workspace, $project->slug, $task->id]), [
            'column_id' => $otherBoardColumn->id,
        ])
        ->assertSessionHasErrors('column_id');

    $this->actingAs($owner)
        ->post(route('tasks.move', [$workspace, $project->slug, $task->id]), [
            'column_id' => $otherProjectColumn->id,
        ])
        ->assertSessionHasErrors('column_id');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'column_id' => $source->id]);
});

test('task movement rejects a full target column without changing the task', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto WIP']);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $source = $board->columns()->where('category', 'todo')->firstOrFail();
    $target = $board->columns()->where('category', 'in_progress')->firstOrFail();
    $target->update(['wip_limit' => 1]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $source->id,
        'created_by' => $owner->id,
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $target->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('tasks.move', [$workspace, $project->slug, $task->id]), [
            'column_id' => $target->id,
        ])
        ->assertSessionHasErrors('column_id');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'column_id' => $source->id]);
    $this->assertDatabaseCount('tasks', 2);
});

test('moving a task to its current column is idempotent at the WIP limit', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto idempotente']);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $column = $board->columns()->where('category', 'todo')->firstOrFail();
    $column->update(['wip_limit' => 1]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $column->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('tasks.move', [$workspace, $project->slug, $task->id]), [
            'column_id' => $column->id,
        ])
        ->assertRedirect(route('projects.show', [$workspace, $project->slug]));

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'column_id' => $column->id]);
});

test('task movement rejects direct entry into the done column', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto sin cierre']);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $source = $board->columns()->where('category', 'todo')->firstOrFail();
    $done = $board->columns()->where('category', 'done')->firstOrFail();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $source->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('tasks.move', [$workspace, $project->slug, $task->id]), [
            'column_id' => $done->id,
        ])
        ->assertSessionHasErrors('column_id');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'column_id' => $source->id]);
});

test('task movement cannot reopen tasks from the done column', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto completado']);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $done = $board->columns()->where('category', 'done')->firstOrFail();
    $target = $board->columns()->where('category', 'todo')->firstOrFail();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $done->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('tasks.move', [$workspace, $project->slug, $task->id]), [
            'column_id' => $target->id,
        ])
        ->assertSessionHasErrors('column_id');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'column_id' => $done->id]);
});

test('task creation rejects a full WIP-limited column', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto creación WIP']);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $column = $board->columns()->where('category', 'todo')->firstOrFail();
    $column->update(['wip_limit' => 1]);
    $payload = [
        'board_id' => $board->id,
        'column_id' => $column->id,
    ];

    $this->actingAs($owner)
        ->post(route('tasks.store', [$workspace, $project->slug]), $payload + ['title' => 'Primera tarea']);

    $this->actingAs($owner)
        ->post(route('tasks.store', [$workspace, $project->slug]), $payload + ['title' => 'Segunda tarea'])
        ->assertSessionHasErrors('column_id');

    $this->assertDatabaseCount('tasks', 1);
});
