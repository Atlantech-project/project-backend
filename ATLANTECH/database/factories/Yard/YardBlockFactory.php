<?php

namespace Database\Factories\Yard;

use App\Modules\Yard\Models\YardBlock;
use App\Modules\Yard\Models\YardZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YardBlock>
 */
class YardBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'yard_zone_id' => YardZone::factory(),
            'code' => fake()->lexify(str_repeat('?', 10)),
        ];
    }
}
