<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Enums\TerminalType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Terminal>
 */
class TerminalFactory extends Factory
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
            'name' => strtoupper(Str::random(10)),
            'terminal_type' => TerminalType::Container,
            'boundary_coordinates' => null,
            'is_active' => true,
        ];
    }
}
