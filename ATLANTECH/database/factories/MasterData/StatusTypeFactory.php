<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\StatusType;
use App\Modules\Shared\Enums\ComponentType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StatusType>
 */
class StatusTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'component_type' => ComponentType::Berth,
            'code' => strtoupper(Str::random(10)),
            'name' => fake()->lexify(str_repeat('?', 10)),
        ];
    }
}
