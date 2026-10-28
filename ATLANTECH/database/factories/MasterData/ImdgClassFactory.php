<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\ImdgClass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ImdgClass>
 */
class ImdgClassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(3)),
            'name' => fake()->lexify(str_repeat('?', 10)),
        ];
    }
}
