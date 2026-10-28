<?php

namespace Database\Factories\Recommendation;

use App\Modules\MasterData\Models\ConstraintType;
use App\Modules\Recommendation\Enums\RationaleFactorType;
use App\Modules\Recommendation\Models\RecommendationOption;
use App\Modules\Recommendation\Models\RecommendationRationaleFactor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationRationaleFactor>
 */
class RecommendationRationaleFactorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'option_id' => RecommendationOption::factory(),
            'factor_type' => RationaleFactorType::Constraint,
            'constraint_type_id' => ConstraintType::factory(),
            'objective_term_id' => null,
            'factor_value' => null,
            'display_order' => 0,
        ];
    }
}
