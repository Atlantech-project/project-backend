<?php

namespace Database\Factories\MasterData;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Models\ConstraintType;
use App\Modules\MasterData\Models\SafetyConstraint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SafetyConstraint>
 */
class SafetyConstraintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'constraint_type_id' => ConstraintType::factory(),
            'terminal_id' => null,
            'berth_id' => null,
            'vessel_type_id' => null,
            'imdg_class' => null,
            'equipment_category_id' => null,
            'value' => fake()->randomFloat(2, 1, 9),
            'created_by' => User::factory(),
            'active_from' => now(),
            'active_to' => null,
        ];
    }
}
