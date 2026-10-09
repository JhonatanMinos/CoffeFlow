<?php

namespace Database\Factories;

use App\Enums\BoardColumnCategory;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'column_id' => null,
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Task $task): void {
            if ($task->column_id !== null) {
                return;
            }

            $project = $task->project;
            $board = $project->boards()->first()
                ?? Board::factory()->for($project)->create();
            $column = $board->columns()->first()
                ?? BoardColumn::factory()->for($board)->create([
                    'category' => BoardColumnCategory::Todo,
                ]);

            $task->column_id = $column->id;
        });
    }
}
