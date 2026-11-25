<?php

namespace Database\Factories;

use App\Enums\Station;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'price_cents' => fake()->numberBetween(20, 150) * 100,
            'station' => Station::Cocina,
            'available' => true,
        ];
    }

    public function bar(): static
    {
        return $this->state(['station' => Station::Barra]);
    }

    public function soldOut(): static
    {
        return $this->state(['available' => false]);
    }
}
