<?php

namespace App\Modules\Environment\Models;

use Database\Factories\Environment\EnvironmentForecastPointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Raw provider values; unit conversion and derived values (e.g. surge) are AI-side (P12-02).
 */
#[Table(name: 'environment_forecast_points', timestamps: false)]
#[Fillable(['forecast_run_id', 'valid_at', 'wind_speed', 'wind_gust', 'wind_direction_deg', 'visibility', 'wave_height', 'wave_period', 'wave_direction_deg', 'current_speed', 'sea_level'])]
#[UseFactory(EnvironmentForecastPointFactory::class)]
class EnvironmentForecastPoint extends Model
{
    /** @use HasFactory<EnvironmentForecastPointFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_at' => 'datetime',
            'wind_speed' => 'decimal:2',
            'wind_gust' => 'decimal:2',
            'wind_direction_deg' => 'decimal:1',
            'visibility' => 'decimal:2',
            'wave_height' => 'decimal:2',
            'wave_period' => 'decimal:1',
            'wave_direction_deg' => 'decimal:1',
            'current_speed' => 'decimal:2',
            'sea_level' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<ForecastRun, $this>
     */
    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class, 'forecast_run_id');
    }
}
