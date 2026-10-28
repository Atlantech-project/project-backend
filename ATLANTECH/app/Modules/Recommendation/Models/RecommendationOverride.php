<?php

namespace App\Modules\Recommendation\Models;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\PilotProfile;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\Recommendation\Enums\RecommendationOverrideComponentType;
use Database\Factories\Recommendation\RecommendationOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Typed override of a recommendation option (FR-1.4.7). Signal for Part 3, not an automatic retrain trigger (D12).
 */
#[Table(name: 'recommendation_overrides', timestamps: false)]
#[Fillable(['recommendation_option_id', 'component_type', 'original_berth_id', 'override_berth_id', 'original_pilot_user_id', 'override_pilot_user_id', 'original_tugboat_id', 'override_tugboat_id', 'original_start', 'override_start', 'override_reason', 'overridden_by', 'overridden_at'])]
#[UseFactory(RecommendationOverrideFactory::class)]
class RecommendationOverride extends Model
{
    /** @use HasFactory<RecommendationOverrideFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'component_type' => RecommendationOverrideComponentType::class,
            'original_start' => 'datetime',
            'override_start' => 'datetime',
            'overridden_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Berth, $this>
     */
    public function overrideBerth(): BelongsTo
    {
        return $this->belongsTo(Berth::class, 'override_berth_id');
    }

    /**
     * @return BelongsTo<PilotProfile, $this>
     */
    public function overridePilotUser(): BelongsTo
    {
        return $this->belongsTo(PilotProfile::class, 'override_pilot_user_id');
    }

    /**
     * @return BelongsTo<Tugboat, $this>
     */
    public function overrideTugboat(): BelongsTo
    {
        return $this->belongsTo(Tugboat::class, 'override_tugboat_id');
    }

    /**
     * @return BelongsTo<RecommendationOption, $this>
     */
    public function recommendationOption(): BelongsTo
    {
        return $this->belongsTo(RecommendationOption::class, 'recommendation_option_id');
    }

    /**
     * @return BelongsTo<Berth, $this>
     */
    public function originalBerth(): BelongsTo
    {
        return $this->belongsTo(Berth::class, 'original_berth_id');
    }

    /**
     * @return BelongsTo<PilotProfile, $this>
     */
    public function originalPilotUser(): BelongsTo
    {
        return $this->belongsTo(PilotProfile::class, 'original_pilot_user_id');
    }

    /**
     * @return BelongsTo<Tugboat, $this>
     */
    public function originalTugboat(): BelongsTo
    {
        return $this->belongsTo(Tugboat::class, 'original_tugboat_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
