<?php

namespace Database\Factories\Environment;

use App\Modules\Environment\Models\EnvironmentForecastPoint;
use App\Modules\Environment\Models\ForecastRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnvironmentForecastPoint>
 */
class EnvironmentForecastPointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'forecast_run_id' => ForecastRun::factory(),
            'valid_at' => now(),
            'wind_speed' => null,
            'wind_gust' => null,
            'wind_direction_deg' => null,
            'visibility' => null,
            'wave_height' => null,
            'wave_period' => null,
            'wave_direction_deg' => null,
            'current_speed' => null,
            'sea_level' => null,
        ];
    }
}
