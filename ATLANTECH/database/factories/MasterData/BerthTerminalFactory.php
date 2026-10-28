<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\BerthTerminal;
use App\Modules\MasterData\Models\Terminal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BerthTerminal>
 */
class BerthTerminalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'berth_id' => Berth::factory(),
            'terminal_id' => Terminal::factory(),
        ];
    }
}
