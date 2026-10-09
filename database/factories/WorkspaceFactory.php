<?php

namespace Database\Factories;

use App\Enums\WorkspaceType;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => WorkspaceType::Team,
        ];
    }

    /**
     * Indicate that the workspace is a personal workspace.
     */
    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => WorkspaceType::Personal,
        ]);
    }

    /**
     * Indicate that the workspace has been deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
