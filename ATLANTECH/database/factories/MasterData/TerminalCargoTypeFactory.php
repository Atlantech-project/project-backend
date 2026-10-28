<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\CargoType;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Models\TerminalCargoType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TerminalCargoType>
 */
class TerminalCargoTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'terminal_id' => Terminal::factory(),
            'cargo_type_id' => CargoType::factory(),
        ];
    }
}
