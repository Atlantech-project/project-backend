<?php

namespace Database\Factories\MasterData;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Models\PilotProfile;
use App\Modules\MasterData\Models\PilotSchedule;
use App\Modules\MasterData\Models\PortShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PilotSchedule>
 */
class PilotScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pilot_user_id' => PilotProfile::factory(),
            'shift_date' => today(),
            'port_shift_id' => PortShift::factory(),
            'created_by' => User::factory(),
        ];
    }
}
