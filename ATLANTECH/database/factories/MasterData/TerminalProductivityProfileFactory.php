<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Enums\TerminalProductivityProfileRateUnit;
use App\Modules\MasterData\Models\TerminalProductivityProfile;
use App\Modules\Shared\Enums\TerminalType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TerminalProductivityProfile>
 */
class TerminalProductivityProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'terminal_id' => fake()->numberBetween(1, 1_000_000),
            'terminal_type' => TerminalType::Container,
            'vessel_type_id' => null,
            'productivity_rate' => fake()->randomFloat(2, 1, 9),
            'rate_unit' => TerminalProductivityProfileRateUnit::MovesPerHour,
            'fixed_overhead_minutes' => fake()->numberBetween(1, 10),
            'valid_from' => today(),
            'valid_to' => null,
        ];
    }
}
