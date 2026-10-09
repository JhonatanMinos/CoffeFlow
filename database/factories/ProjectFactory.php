<?php

namespace Database\Factories;

use App\Enums\ProjectMethodology;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'created_by' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'methodology' => ProjectMethodology::Kanban,
            'status' => ProjectStatus::Active,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => ProjectStatus::Archived]);
    }
}
