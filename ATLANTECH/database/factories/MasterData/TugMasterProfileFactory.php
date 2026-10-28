<?php

namespace Database\Factories\MasterData;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Models\TugMasterProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TugMasterProfile>
 */
class TugMasterProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'assigned_tugboat_id' => null,
        ];
    }
}
