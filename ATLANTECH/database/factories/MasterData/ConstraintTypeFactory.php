<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Enums\ConstraintTypeCategory;
use App\Modules\MasterData\Enums\ConstraintTypeComparator;
use App\Modules\MasterData\Enums\ConstraintTypeUnit;
use App\Modules\MasterData\Models\ConstraintType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ConstraintType>
 */
class ConstraintTypeFactory extends Factory
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
            'category' => ConstraintTypeCategory::Environmental,
            'unit' => ConstraintTypeUnit::Knots,
            'comparator' => ConstraintTypeComparator::MaxAllowed,
            'is_hard_safety_limit' => true,
            'description' => null,
        ];
    }
}
