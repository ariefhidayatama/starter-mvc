<?php

namespace Database\Factories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Data palsu untuk test & seeder. Dibuat oleh CRUD generator.
 *
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'whs_code' => fake()->lexify('??????????'),
            'owner_code' => fake()->lexify('??????????'),
            'msg_group' => fake()->lexify('??????????'),
            'msg_description' => fake()->paragraph(),
            'photo1' => ucwords(fake()->word().' '.fake()->word()),
            'photo2' => ucwords(fake()->word().' '.fake()->word()),
            'photo3' => ucwords(fake()->word().' '.fake()->word()),
            'photo4' => ucwords(fake()->word().' '.fake()->word()),
            'photo5' => ucwords(fake()->word().' '.fake()->word()),
            'document1' => ucwords(fake()->word().' '.fake()->word()),
            'document2' => ucwords(fake()->word().' '.fake()->word()),
            'document3' => ucwords(fake()->word().' '.fake()->word()),
        ];
    }
}
