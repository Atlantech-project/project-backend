<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\PortShift;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PortShift>
 */
class PortShiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(10)),
            'name' => fake()->lexify(str_repeat('?', 10)),
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
            'is_active' => true,
        ];
    }
}
