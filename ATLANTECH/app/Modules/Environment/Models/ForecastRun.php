<?php

namespace App\Modules\Environment\Models;

use App\Modules\Access\Models\User;
use App\Modules\Environment\Enums\ForecastRunProviderRank;
use App\Modules\Environment\Enums\ForecastRunPullType;
use App\Modules\Environment\Enums\ForecastRunStatus;
use App\Modules\Environment\Enums\ForecastRunVisibilityUnit;
use App\Modules\Environment\Enums\SpeedUnit;
use Database\Factories\Environment\ForecastRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Location = port_profile lat/lon (single port).
 */
#[Table(name: 'forecast_runs', timestamps: false)]
#[Fillable(['source', 'pull_type', 'provider_rank', 'data_stale', 'berthing_request_id', 'submitted_by', 'status', 'pulled_at', 'horizon_start', 'horizon_end', 'wind_speed_unit', 'current_speed_unit', 'visibility_unit'])]
#[UseFactory(ForecastRunFactory::class)]
class ForecastRun extends Model
{
    /** @use HasFactory<ForecastRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pull_type' => ForecastRunPullType::class,
            'provider_rank' => ForecastRunProviderRank::class,
            'data_stale' => 'boolean',
            'status' => ForecastRunStatus::class,
            'pulled_at' => 'datetime',
            'horizon_start' => 'datetime',
            'horizon_end' => 'datetime',
            'wind_speed_unit' => SpeedUnit::class,
            'current_speed_unit' => SpeedUnit::class,
            'visibility_unit' => ForecastRunVisibilityUnit::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return HasMany<EnvironmentForecastPoint, $this>
     */
    public function environmentForecastPoints(): HasMany
    {
        return $this->hasMany(EnvironmentForecastPoint::class, 'forecast_run_id');
    }
}
