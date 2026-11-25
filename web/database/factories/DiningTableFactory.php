<?php

namespace Database\Factories;

use App\Enums\TableStatus;
use App\Models\DiningTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiningTable>
 */
class DiningTableFactory extends Factory
{
    protected $model = DiningTable::class;

    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1, 999),
            'capacity' => fake()->randomElement([2, 4, 4, 6]),
            'status' => TableStatus::Libre,
        ];
    }
}
