<?php

namespace Database\Factories\Yard;

use App\Modules\Baplie\Models\ContainerManifestEntry;
use App\Modules\Yard\Enums\TerminalRecommendationType;
use App\Modules\Yard\Models\YardBlock;
use App\Modules\Yard\Models\YardPlacementItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YardPlacementItem>
 */
class YardPlacementItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'terminal_recommendation_id' => fake()->numberBetween(1, 1_000_000),
            'rec_type' => TerminalRecommendationType::YardPlacement,
            'manifest_entry_id' => ContainerManifestEntry::factory(),
            'yard_block_id' => YardBlock::factory(),
            'yard_slot_id' => null,
        ];
    }
}
