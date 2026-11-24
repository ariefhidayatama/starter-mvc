<?php

namespace Database\Factories;

use App\Models\Kwitansi;
use App\Models\KwitansiKwitansiDet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Data palsu untuk test & seeder. Dibuat oleh CRUD generator.
 *
 * @extends Factory<KwitansiKwitansiDet>
 */
class KwitansiKwitansiDetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kwitansi_id' => Kwitansi::factory(),
            'customer_po' => ucwords(fake()->word().' '.fake()->word()),
            'si_no' => ucwords(fake()->word().' '.fake()->word()),
        ];
    }
}
