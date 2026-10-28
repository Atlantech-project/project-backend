<?php

namespace Database\Factories\Recommendation;

use App\Modules\Recommendation\Models\ObjectiveTerm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ObjectiveTerm>
 */
class ObjectiveTermFactory extends Factory
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
        ];
    }
}
