<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Data palsu untuk test & seeder. Dibuat oleh CRUD generator.
 *
 * @extends Factory<MessageDetail>
 */
class MessageDetailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'msg_id' => Message::factory(),
            'trans_code' => fake()->lexify('??????????'),
            'trans_no' => ucwords(fake()->word().' '.fake()->word()),
            'ref_no' => ucwords(fake()->word().' '.fake()->word()),
            'po_no' => ucwords(fake()->word().' '.fake()->word()),
            'prod_code' => ucwords(fake()->word().' '.fake()->word()),
            'loc_code' => ucwords(fake()->word().' '.fake()->word()),
            'pallet_no' => ucwords(fake()->word().' '.fake()->word()),
            'lot_no' => ucwords(fake()->word().' '.fake()->word()),
            'inv_status' => ucwords(fake()->word().' '.fake()->word()),
            'qty' => fake()->numberBetween(0, 100),
        ];
    }
}
