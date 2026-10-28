<?php

namespace Database\Factories\Recommendation;

use App\Modules\Recommendation\Models\EntryWindowProposal;
use App\Modules\Recommendation\Models\FeasibleWindow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeasibleWindow>
 */
class FeasibleWindowFactory extends Factory
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
            'window_start' => now(),
            'window_end' => now()->addHours(2),
            'confidence' => null,
        ];
    }
}
