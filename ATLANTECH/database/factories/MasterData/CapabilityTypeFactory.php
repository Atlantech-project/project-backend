<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Enums\CapabilityTypeUnit;
use App\Modules\MasterData\Models\CapabilityType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CapabilityType>
 */
class CapabilityTypeFactory extends Factory
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
            'unit' => CapabilityTypeUnit::Tonnes,
        ];
    }
}
