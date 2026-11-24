<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('???-#####')),
            'name' => ucwords(fake()->word().' '.fake()->word().' '.fake()->word()),
            'description' => fake()->optional()->paragraph(),
            // Kelipatan 500 rupiah, 5.000 – 2.000.000
            'price' => fake()->numberBetween(10, 4000) * 500,
            'stock' => fake()->numberBetween(0, 500),
            'is_active' => fake()->boolean(90),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
