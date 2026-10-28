<?php

namespace Database\Factories\Recommendation;

use App\Modules\MasterData\Models\ConstraintType;
use App\Modules\Recommendation\Models\BlockedPeriod;
use App\Modules\Recommendation\Models\EntryWindowProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlockedPeriod>
 */
class BlockedPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proposal_id' => EntryWindowProposal::factory(),
            'period_start' => now(),
            'period_end' => now()->addHours(2),
            'limiting_constraint_type_id' => ConstraintType::factory(),
        ];
    }
}
