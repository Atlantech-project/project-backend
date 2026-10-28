<?php

namespace Database\Factories\Baplie;

use App\Modules\Access\Models\User;
use App\Modules\Baplie\Enums\BaplieFileParseStatus;
use App\Modules\Baplie\Enums\BaplieFileStatus;
use App\Modules\Baplie\Models\BaplieFile;
use App\Modules\MasterData\Models\Vessel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BaplieFile>
 */
class BaplieFileFactory extends Factory
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
            'berthing_request_id' => fake()->numberBetween(1, 1_000_000),
            'uploaded_by' => User::factory(),
            'file_hash_sha256' => fake()->numerify(str_repeat('#', 64)),
            'storage_path' => fake()->lexify(str_repeat('?', 10)),
            'parse_status' => BaplieFileParseStatus::Pending,
            'status' => BaplieFileStatus::Staged,
            'staged_payload' => null,
            'is_manually_corrected' => false,
            'corrected_by' => null,
            'corrected_at' => null,
            'uploaded_at' => now(),
        ];
    }
}
