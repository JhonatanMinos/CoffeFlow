<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'slug']);
            $table->index(['project_id', 'is_default']);
        });

        Schema::create('board_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('category', ['backlog', 'todo', 'in_progress', 'review', 'done', 'cancelled']);
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('wip_limit')->nullable();
            $table->timestamps();

            $table->unique(['board_id', 'position']);
            $table->index(['board_id', 'category']);
        });

        DB::table('projects')->orderBy('id')->each(function (object $project): void {
            $now = now();
            $boardId = DB::table('boards')->insertGetId([
                'project_id' => $project->id,
                'name' => 'Kanban',
                'slug' => 'kanban',
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('board_columns')->insert($this->defaultColumns($boardId, $now));
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_columns');
        Schema::dropIfExists('boards');
    }

    /** @return array<int, array<string, mixed>> */
    private function defaultColumns(int $boardId, mixed $timestamp): array
    {
        return collect([
            ['name' => 'Pendiente', 'category' => 'todo'],
            ['name' => 'En progreso', 'category' => 'in_progress'],
            ['name' => 'En revisión', 'category' => 'review'],
            ['name' => 'Terminado', 'category' => 'done'],
        ])->map(fn (array $column, int $position) => [
            'board_id' => $boardId,
            'name' => $column['name'],
            'category' => $column['category'],
            'position' => $position,
            'wip_limit' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all();
    }
};
