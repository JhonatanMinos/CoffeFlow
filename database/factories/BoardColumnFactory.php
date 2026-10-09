<?php

namespace Database\Factories;

use App\Enums\BoardColumnCategory;
use App\Models\Board;
use App\Models\BoardColumn;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BoardColumn> */
class BoardColumnFactory extends Factory
{
    public function definition(): array
    {
        return [
            'board_id' => Board::factory(),
            'name' => fake()->words(2, true),
            'category' => BoardColumnCategory::Todo,
            'position' => fake()->unique()->numberBetween(0, 1000),
            'wip_limit' => null,
        ];
    }
}
