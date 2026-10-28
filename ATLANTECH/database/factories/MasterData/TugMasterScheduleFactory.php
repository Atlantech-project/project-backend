<?php

namespace Database\Factories\MasterData;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Models\PortShift;
use App\Modules\MasterData\Models\TugMasterProfile;
use App\Modules\MasterData\Models\TugMasterSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TugMasterSchedule>
 */
class TugMasterScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tug_master_user_id' => TugMasterProfile::factory(),
            'shift_date' => today(),
            'port_shift_id' => PortShift::factory(),
            'created_by' => User::factory(),
        ];
    }
}
