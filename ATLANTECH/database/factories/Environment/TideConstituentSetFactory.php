<?php

namespace Database\Factories\Environment;

use App\Modules\Environment\Models\TideConstituentSet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TideConstituentSet>
 */
class TideConstituentSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version_label' => strtoupper(Str::random(10)),
            'datum_reference' => fake()->lexify(str_repeat('?', 10)),
            'valid_from' => today(),
            'is_active' => false,
        ];
    }
}
