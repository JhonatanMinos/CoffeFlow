<?php

use App\Enums\WorkspaceRole;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\FocusSession;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
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
            ->where('canCreateTask', true)
            ->where('canEditTasks', true)
            ->where('canArchiveTasks', true),
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

test('guests can view projects but cannot create or move tasks', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto invitado']);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $column = $board->columns()->where('category', 'todo')->firstOrFail();
    $target = $board->columns()->where('category', 'in_progress')->firstOrFail();
    $workspace->members()->attach($guest, ['role' => WorkspaceRole::Guest->value]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $column->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($guest)
        ->get(route('projects.show', [$workspace, $project->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/show')
            ->where('canCreateTask', false)
            ->where('canEditTasks', false)
            ->where('canArchiveTasks', false),
        );

    $this->actingAs($guest)
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

test('authorized users can manually complete a task into its done column', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto finalización']);

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
        ->post(route('tasks.complete', [$workspace, $project->slug, $task->id]))
        ->assertRedirect(route('projects.show', [$workspace, $project->slug]));

    $completedTask = $task->fresh();
    expect($completedTask->column_id)->toBe($done->id)
        ->and($completedTask->completed_by)->toBe($owner->id)
        ->and($completedTask->completed_at)->not->toBeNull();
});

test('guests and outsiders cannot manually complete tasks', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();
    $outsider = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto autorizado']);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $source = $board->columns()->where('category', 'todo')->firstOrFail();
    $workspace->members()->attach($guest, ['role' => WorkspaceRole::Guest->value]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $source->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($guest)
        ->post(route('tasks.complete', [$workspace, $project->slug, $task->id]))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->post(route('tasks.complete', [$workspace, $project->slug, $task->id]))
        ->assertForbidden();

    expect($task->fresh()->column_id)->toBe($source->id)
        ->and($task->fresh()->completed_at)->toBeNull()
        ->and($task->fresh()->completed_by)->toBeNull();
});

test('completion cannot resolve a task through a different project', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto original']);
    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Otro proyecto']);

    $projects = Project::where('workspace_id', $workspace->id)->orderBy('id')->get();
    $project = $projects->firstOrFail();
    $otherProject = $projects->last();
    $source = $project->boards()->firstOrFail()->columns()->where('category', 'todo')->firstOrFail();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $source->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('tasks.complete', [$workspace, $otherProject->slug, $task->id]))
        ->assertNotFound();

    expect($task->fresh()->column_id)->toBe($source->id)
        ->and($task->fresh()->completed_at)->toBeNull()
        ->and($task->fresh()->completed_by)->toBeNull();
});

test('repeated completion preserves the original author and timestamp', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto idempotencia']);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $source = $project->boards()->firstOrFail()->columns()->where('category', 'todo')->firstOrFail();
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $source->id,
        'created_by' => $owner->id,
    ]);

    $this->travelTo(Carbon::parse('2026-10-09 12:00:00'));
    $this->actingAs($owner)
        ->post(route('tasks.complete', [$workspace, $project->slug, $task->id]));

    $completedAt = $task->fresh()->completed_at;
    $this->travel(5)->minutes();

    $this->actingAs($member)
        ->post(route('tasks.complete', [$workspace, $project->slug, $task->id]))
        ->assertRedirect(route('projects.show', [$workspace, $project->slug]));

    expect($task->fresh()->completed_by)->toBe($owner->id)
        ->and($task->fresh()->completed_at->equalTo($completedAt))->toBeTrue();
});

test('completion rejects a full done column without changing the task', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();

    $this->actingAs($owner)
        ->post(route('projects.store', $workspace), ['name' => 'Proyecto WIP terminado']);

    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $source = $board->columns()->where('category', 'todo')->firstOrFail();
    $done = $board->columns()->where('category', 'done')->firstOrFail();
    $done->update(['wip_limit' => 1]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $source->id,
        'created_by' => $owner->id,
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $done->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('tasks.complete', [$workspace, $project->slug, $task->id]))
        ->assertSessionHasErrors('column_id');

    expect($task->fresh()->column_id)->toBe($source->id)
        ->and($task->fresh()->completed_at)->toBeNull()
        ->and($task->fresh()->completed_by)->toBeNull();
    $this->assertDatabaseCount('tasks', 2);
});

test('authorized workspace members can edit only task title and description', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = $owner->personalWorkspace();
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $this->actingAs($owner)->post(route('projects.store', $workspace), ['name' => 'Edición segura']);
    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $column = $board->columns()->where('category', 'todo')->firstOrFail();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $column->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($member)
        ->put(route('tasks.update', [$workspace, $project->slug, $task->id]), [
            'title' => 'Título actualizado',
            'description' => 'Descripción actualizada',
            'column_id' => $board->columns()->where('category', 'done')->firstOrFail()->id,
        ])
        ->assertRedirect(route('projects.show', [$workspace, $project->slug]));

    expect($task->fresh()->title)->toBe('Título actualizado')
        ->and($task->fresh()->description)->toBe('Descripción actualizada')
        ->and($task->fresh()->column_id)->toBe($column->id)
        ->and($task->fresh()->created_by)->toBe($owner->id);
});

test('task edits reject invalid and extra fields without changing task state', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();
    $this->actingAs($owner)->post(route('projects.store', $workspace), ['name' => 'Edición inválida']);
    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $column = $board->columns()->where('category', 'todo')->firstOrFail();
    $done = $board->columns()->where('category', 'done')->firstOrFail();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $column->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->put(route('tasks.update', [$workspace, $project->slug, $task->id]), [
            'title' => str_repeat('x', 161),
            'description' => 'Inválida',
            'column_id' => $done->id,
        ])
        ->assertSessionHasErrors('title');

    expect($task->fresh()->title)->not->toBe(str_repeat('x', 161))
        ->and($task->fresh()->column_id)->toBe($column->id);
});

dataset('task edit validation errors', [
    'required title' => [null, 'Texto válido', 'title', 'El título es obligatorio.'],
    'title must be a string' => [['no', 'es texto'], 'Texto válido', 'title', 'El título debe ser texto.'],
    'title maximum length' => [str_repeat('x', 161), 'Texto válido', 'title', 'El título no puede superar los 160 caracteres.'],
    'description must be a string' => ['Título válido', ['no', 'es texto'], 'description', 'La descripción debe ser texto.'],
    'description maximum length' => ['Título válido', str_repeat('x', 5001), 'description', 'La descripción no puede superar los 5000 caracteres.'],
]);

test('task edit validation errors are localized and preserve task state', function ($title, $description, $field, $message) {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();
    $this->actingAs($owner)->post(route('projects.store', $workspace), ['name' => 'Validación de edición']);
    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $done = $board->columns()->where('category', 'done')->firstOrFail();
    $completedAt = now()->subDay();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $done->id,
        'created_by' => $owner->id,
        'title' => 'Título original',
        'description' => 'Descripción original',
        'completed_at' => $completedAt,
        'completed_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->put(route('tasks.update', [$workspace, $project->slug, $task->id]), [
            'title' => $title,
            'description' => $description,
        ])
        ->assertSessionHasErrors([$field => $message]);

    expect($task->fresh()->title)->toBe('Título original')
        ->and($task->fresh()->description)->toBe('Descripción original')
        ->and($task->fresh()->column_id)->toBe($done->id)
        ->and($task->fresh()->completed_at->toDateTimeString())->toBe($completedAt->toDateTimeString())
        ->and($task->fresh()->completed_by)->toBe($owner->id);
})->with('task edit validation errors');

test('guests and outsiders cannot edit tasks and project scope is enforced', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();
    $outsider = User::factory()->create();
    $workspace = $owner->personalWorkspace();
    $workspace->members()->attach($guest, ['role' => WorkspaceRole::Guest->value]);
    $this->actingAs($owner)->post(route('projects.store', $workspace), ['name' => 'Permisos edición']);
    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $column = $project->boards()->firstOrFail()->columns()->firstOrFail();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $column->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($guest)
        ->put(route('tasks.update', [$workspace, $project->slug, $task->id]), ['title' => 'Sin permiso'])
        ->assertForbidden();
    $this->actingAs($outsider)
        ->put(route('tasks.update', [$workspace, $project->slug, $task->id]), ['title' => 'Fuera'])
        ->assertForbidden();

    $this->actingAs($owner)->post(route('projects.store', $workspace), ['name' => 'Otro ámbito']);
    $otherProject = Project::where('workspace_id', $workspace->id)->where('id', '!=', $project->id)->firstOrFail();
    $this->actingAs($owner)
        ->put(route('tasks.update', [$workspace, $otherProject->slug, $task->id]), ['title' => 'Fuera de proyecto'])
        ->assertNotFound();

    expect($task->fresh()->title)->not->toBe('Sin permiso');
});

test('authorized users archive tasks without deleting focus history and archived tasks leave the board', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();
    $this->actingAs($owner)->post(route('projects.store', $workspace), ['name' => 'Archivo seguro']);
    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $column = $project->boards()->firstOrFail()->columns()->firstOrFail();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $column->id,
        'created_by' => $owner->id,
    ]);
    $session = FocusSession::create([
        'user_id' => $owner->id,
        'task_id' => $task->id,
        'status' => 'completed',
        'started_at' => now()->subHour(),
    ]);

    $this->actingAs($owner)
        ->delete(route('tasks.archive', [$workspace, $project->slug, $task->id]))
        ->assertRedirect(route('projects.show', [$workspace, $project->slug]));

    expect($task->fresh()->archived_at)->not->toBeNull()
        ->and($session->fresh()->task_id)->toBe($task->id);
    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => $task->title]);
    $this->actingAs($owner)
        ->get(route('projects.show', [$workspace, $project->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('board.columns.0.tasks', []));
});

test('archive is idempotent and requires an authorized user', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();
    $workspace = $owner->personalWorkspace();
    $workspace->members()->attach($guest, ['role' => WorkspaceRole::Guest->value]);
    $this->actingAs($owner)->post(route('projects.store', $workspace), ['name' => 'Archivo idempotente']);
    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $column = $project->boards()->firstOrFail()->columns()->firstOrFail();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $column->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($guest)
        ->delete(route('tasks.archive', [$workspace, $project->slug, $task->id]))
        ->assertForbidden();
    $this->actingAs($owner)
        ->delete(route('tasks.archive', [$workspace, $project->slug, $task->id]))
        ->assertRedirect();
    $archivedAt = $task->fresh()->archived_at;
    $this->travel(5)->minutes();
    $this->actingAs($owner)
        ->delete(route('tasks.archive', [$workspace, $project->slug, $task->id]))
        ->assertRedirect();

    expect($task->fresh()->archived_at->equalTo($archivedAt))->toBeTrue();
});

test('archiving a task releases its WIP slot and archived tasks cannot be moved or completed', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();
    $this->actingAs($owner)->post(route('projects.store', $workspace), ['name' => 'WIP archivo']);
    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $source = $board->columns()->where('category', 'todo')->firstOrFail();
    $target = $board->columns()->where('category', 'in_progress')->firstOrFail();
    $source->update(['wip_limit' => 1]);
    $done = $board->columns()->where('category', 'done')->firstOrFail();
    $done->update(['wip_limit' => 1]);
    $archived = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $source->id,
        'created_by' => $owner->id,
    ]);
    $this->actingAs($owner)->delete(route('tasks.archive', [$workspace, $project->slug, $archived->id]));

    $this->actingAs($owner)
        ->post(route('tasks.store', [$workspace, $project->slug]), [
            'title' => 'Ocupa el espacio liberado', 'board_id' => $board->id, 'column_id' => $source->id,
        ])
        ->assertRedirect();
    $this->actingAs($owner)
        ->post(route('tasks.move', [$workspace, $project->slug, $archived->id]), ['column_id' => $target->id])
        ->assertSessionHasErrors('column_id');
    $this->actingAs($owner)
        ->post(route('tasks.complete', [$workspace, $project->slug, $archived->id]))
        ->assertSessionHasErrors('column_id');

    expect($archived->fresh()->column_id)->toBe($source->id)
        ->and($archived->fresh()->completed_at)->toBeNull();
});

test('archived tasks do not consume WIP capacity for moves or completion', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();
    $this->actingAs($owner)->post(route('projects.store', $workspace), ['name' => 'WIP con archivo']);
    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $board = $project->boards()->firstOrFail();
    $source = $board->columns()->where('category', 'todo')->firstOrFail();
    $target = $board->columns()->where('category', 'in_progress')->firstOrFail();
    $done = $board->columns()->where('category', 'done')->firstOrFail();
    $target->update(['wip_limit' => 1]);
    $done->update(['wip_limit' => 1]);
    $archivedInTarget = Task::factory()->create([
        'project_id' => $project->id, 'column_id' => $target->id, 'created_by' => $owner->id,
    ]);
    $archivedInDone = Task::factory()->create([
        'project_id' => $project->id, 'column_id' => $done->id, 'created_by' => $owner->id,
    ]);
    $this->actingAs($owner)->delete(route('tasks.archive', [$workspace, $project->slug, $archivedInTarget->id]));
    $this->actingAs($owner)->delete(route('tasks.archive', [$workspace, $project->slug, $archivedInDone->id]));
    $toMove = Task::factory()->create([
        'project_id' => $project->id, 'column_id' => $source->id, 'created_by' => $owner->id,
    ]);
    $toComplete = Task::factory()->create([
        'project_id' => $project->id, 'column_id' => $source->id, 'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('tasks.move', [$workspace, $project->slug, $toMove->id]), ['column_id' => $target->id])
        ->assertRedirect();
    $this->actingAs($owner)
        ->post(route('tasks.complete', [$workspace, $project->slug, $toComplete->id]))
        ->assertRedirect();

    expect($toMove->fresh()->column_id)->toBe($target->id)
        ->and($toComplete->fresh()->column_id)->toBe($done->id);
});

test('archiving is rejected while a task has an active focus session', function () {
    $owner = User::factory()->create();
    $workspace = $owner->personalWorkspace();
    $this->actingAs($owner)->post(route('projects.store', $workspace), ['name' => 'Archivo con Focus']);
    $project = Project::where('workspace_id', $workspace->id)->firstOrFail();
    $column = $project->boards()->firstOrFail()->columns()->firstOrFail();
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'column_id' => $column->id,
        'created_by' => $owner->id,
    ]);
    FocusSession::create([
        'user_id' => $owner->id, 'task_id' => $task->id, 'status' => 'paused', 'started_at' => now(),
    ]);

    $this->actingAs($owner)
        ->delete(route('tasks.archive', [$workspace, $project->slug, $task->id]))
        ->assertSessionHasErrors('task');

    expect($task->fresh()->archived_at)->toBeNull();
});
