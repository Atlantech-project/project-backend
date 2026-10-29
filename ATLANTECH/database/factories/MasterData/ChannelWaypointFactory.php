<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\ChannelWaypoint;
use App\Modules\MasterData\Models\NavigationChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelWaypoint>
 */
class ChannelWaypointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_id' => NavigationChannel::factory(),
            'sequence_order' => fake()->numberBetween(1, 10),
            'label' => fake()->lexify(str_repeat('?', 10)),
            'latitude' => fake()->randomFloat(2, 1, 9),
            'longitude' => fake()->randomFloat(2, 1, 9),
        ];
    }
}
