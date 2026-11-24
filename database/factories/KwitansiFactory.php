<?php

namespace Database\Factories;

use App\Models\Kwitansi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Data palsu untuk test & seeder. Dibuat oleh CRUD generator.
 *
 * @extends Factory<Kwitansi>
 */
class KwitansiFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kwitansi_no' => ucwords(fake()->word().' '.fake()->word()),
            'customer' => ucwords(fake()->word().' '.fake()->word()),
            'ship_to' => ucwords(fake()->word().' '.fake()->word()),
            'ttf_no' => ucwords(fake()->word().' '.fake()->word()),
        ];
    }
}
