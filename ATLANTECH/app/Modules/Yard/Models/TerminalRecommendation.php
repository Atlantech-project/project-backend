<?php

namespace App\Modules\Yard\Models;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Recommendation\Models\RationaleTemplate;
use App\Modules\Recommendation\Models\Recommendation;
use App\Modules\Shared\Enums\TerminalType;
use App\Modules\Yard\Enums\TerminalRecommendationDecision;
use App\Modules\Yard\Enums\TerminalRecommendationLifecycleStatus;
use App\Modules\Yard\Enums\TerminalRecommendationType;
use Database\Factories\Yard\TerminalRecommendationFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Common terminal output contract (FR-3.2.2). Visible to that terminal only (§42).
 * Columns written by an AI worker are not fillable (CT-15): `lifecycle_status`, `confidence`, `rationale_template_id`, `rationale_params`, `failure_reason`.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'terminal_recommendations')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['recommendation_id', 'terminal_id', 'terminal_type', 'rec_type', 'window_start', 'window_end', 'decision', 'decided_by', 'decided_at', 'decision_note', 'exported_at', 'exported_by'])]
#[UseFactory(TerminalRecommendationFactory::class)]
class TerminalRecommendation extends Model
{
    /** @use HasFactory<TerminalRecommendationFactory> */
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
            'terminal_type' => TerminalType::class,
            'rec_type' => TerminalRecommendationType::class,
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'decision' => TerminalRecommendationDecision::class,
            'decided_at' => 'datetime',
            'lifecycle_status' => TerminalRecommendationLifecycleStatus::class,
            'exported_at' => 'datetime',
            'confidence' => 'decimal:3',
            'rationale_params' => 'array',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }

    /**
     * @return BelongsTo<Recommendation, $this>
     */
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class, 'recommendation_id');
    }

    /**
     * @return BelongsTo<RationaleTemplate, $this>
     */
    public function rationaleTemplate(): BelongsTo
    {
        return $this->belongsTo(RationaleTemplate::class, 'rationale_template_id');
    }

    /**
     * @return BelongsTo<Terminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return HasMany<YardPlacementItem, $this>
     */
    public function yardPlacementItems(): HasMany
    {
        return $this->hasMany(YardPlacementItem::class, 'terminal_recommendation_id');
    }

    /**
     * @return HasMany<StorageAllocationItem, $this>
     */
    public function storageAllocationItems(): HasMany
    {
        return $this->hasMany(StorageAllocationItem::class, 'terminal_recommendation_id');
    }

    /**
     * @return HasMany<YardRun, $this>
     */
    public function yardRuns(): HasMany
    {
        return $this->hasMany(YardRun::class, 'terminal_recommendation_id');
    }
}
