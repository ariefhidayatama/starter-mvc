<?php

namespace Database\Factories;

use App\Enums\MenuLocation;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location' => MenuLocation::Website,
            'parent_id' => null,
            'title' => ucfirst($this->faker->unique()->word()),
            'url' => '/'.$this->faker->unique()->slug(2),
            'icon' => null,
            'permission' => null,
            'open_in_new_tab' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(['location' => MenuLocation::Admin]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /** Submenu dari $parent (lokasi mengikuti induknya). */
    public function childOf(Menu $parent): static
    {
        return $this->state(['location' => $parent->location, 'parent_id' => $parent->id]);
    }
}
