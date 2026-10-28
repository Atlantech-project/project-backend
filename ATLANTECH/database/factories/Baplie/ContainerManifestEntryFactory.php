<?php

namespace Database\Factories\Baplie;

use App\Modules\Baplie\Models\BaplieFile;
use App\Modules\Baplie\Models\ContainerManifestEntry;
use App\Modules\MasterData\Models\Container;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContainerManifestEntry>
 */
class ContainerManifestEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'baplie_file_id' => BaplieFile::factory(),
            'container_id' => Container::factory(),
            'full_empty' => null,
            'weight_kg' => null,
            'imdg_class' => null,
            'oog_over_front_cm' => null,
            'oog_over_back_cm' => null,
            'oog_over_right_cm' => null,
            'oog_over_left_cm' => null,
            'oog_over_height_cm' => null,
            'reefer_set_temperature' => null,
            'stowage_bay' => null,
            'stowage_row' => null,
            'stowage_tier' => null,
            'port_of_load' => null,
            'port_of_discharge' => null,
        ];
    }
}
