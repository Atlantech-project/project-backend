<?php

namespace App\Modules\Plan\Models;

use App\Modules\Access\Models\User;
use App\Modules\Plan\Enums\OperationalPlanStatus;
use App\Modules\Recommendation\Models\Recommendation;
use App\Modules\Recommendation\Models\RecommendationOption;
use Database\Factories\Plan\OperationalPlanFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `operational_plans` table.
 * Generated, never written: `active_key`.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'operational_plans')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['berthing_request_id', 'recommendation_id', 'recommendation_option_id', 'status', 'confirmed_by', 'confirmed_at'])]
#[UseFactory(OperationalPlanFactory::class)]
class OperationalPlan extends Model
{
    /** @use HasFactory<OperationalPlanFactory> */
    use HasFactory;

    /**
     * The table has no `created_at` column.
     *
     * @var string|null
     */
    public const CREATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OperationalPlanStatus::class,
            'confirmed_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RecommendationOption, $this>
     */
    public function recommendationOption(): BelongsTo
    {
        return $this->belongsTo(RecommendationOption::class, 'recommendation_option_id');
    }

    /**
     * @return BelongsTo<Recommendation, $this>
     */
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class, 'recommendation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * @return HasMany<PlanComponent, $this>
     */
    public function planComponents(): HasMany
    {
        return $this->hasMany(PlanComponent::class, 'operational_plan_id');
    }
}
