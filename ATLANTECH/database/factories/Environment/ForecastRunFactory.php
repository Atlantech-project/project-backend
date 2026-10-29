<?php

namespace Database\Factories\Environment;

use App\Modules\Environment\Enums\ForecastRunPullType;
use App\Modules\Environment\Enums\ForecastRunStatus;
use App\Modules\Environment\Enums\ForecastRunVisibilityUnit;
use App\Modules\Environment\Enums\SpeedUnit;
use App\Modules\Environment\Models\ForecastRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForecastRun>
 */
class ForecastRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => fake()->lexify(str_repeat('?', 10)),
            'pull_type' => ForecastRunPullType::Scheduled,
            'provider_rank' => null,
            'data_stale' => false,
            'berthing_request_id' => null,
            'submitted_by' => null,
            'status' => ForecastRunStatus::Success,
            'pulled_at' => now(),
            'horizon_start' => null,
            'horizon_end' => null,
            'wind_speed_unit' => SpeedUnit::Knots,
            'current_speed_unit' => SpeedUnit::Knots,
            'visibility_unit' => ForecastRunVisibilityUnit::NauticalMiles,
        ];
    }
}
