<?php

namespace Database\Factories\Berthing;

use App\Modules\Access\Models\User;
use App\Modules\Berthing\Enums\BerthingRequestSource;
use App\Modules\Berthing\Enums\BerthingRequestStatus;
use App\Modules\Berthing\Enums\DataSource;
use App\Modules\Berthing\Models\BerthingRequest;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Models\Vessel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BerthingRequest>
 */
class BerthingRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vessel_id' => Vessel::factory(),
            'requested_terminal_id' => Terminal::factory(),
            'source' => BerthingRequestSource::Manual,
            'external_system_id' => null,
            'external_reference' => null,
            'submitted_by' => User::factory(),
            'requested_eta' => now(),
            'arrival_draft' => fake()->randomFloat(2, 1, 9),
            'departure_draft' => null,
            'previous_port' => null,
            'next_port' => null,
            'draft_source' => DataSource::Manual,
            'eta_source' => DataSource::Manual,
            'ports_source' => DataSource::Manual,
            'block_coefficient' => fake()->randomFloat(2, 0.55, 0.85),
            'gross_tonnage' => fake()->numberBetween(1, 10),
            'deadweight_tonnes' => fake()->numberBetween(1, 10),
            'status' => BerthingRequestStatus::Submitted,
        ];
    }
}
