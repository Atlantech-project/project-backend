<?php

namespace Database\Factories\Recommendation;

use App\Modules\Recommendation\Models\RationaleTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RationaleTemplate>
 */
class RationaleTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->lexify(str_repeat('?', 10)),
            'version' => fake()->numberBetween(1, 10),
            'template_text' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
