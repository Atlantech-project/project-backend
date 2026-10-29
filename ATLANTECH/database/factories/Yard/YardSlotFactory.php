<?php

namespace Database\Factories\Yard;

use App\Modules\Yard\Enums\YardSlotStatus;
use App\Modules\Yard\Models\YardBlock;
use App\Modules\Yard\Models\YardSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YardSlot>
 */
class YardSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'yard_block_id' => YardBlock::factory(),
            'bay' => fake()->numberBetween(1, 10),
            'row_no' => fake()->numberBetween(1, 10),
            'tier' => fake()->numberBetween(1, 10),
            'status' => YardSlotStatus::Active,
            'container_id' => null,
        ];
    }
}
