<?php

namespace App\Modules\Recommendation\Models;

use App\Modules\Access\Models\User;
use App\Modules\Berthing\Models\BerthingRequest;
use App\Modules\Environment\Models\ForecastRun;
use App\Modules\Environment\Models\TidePredictionRun;
use App\Modules\MasterData\Models\MlModelVersion;
use App\Modules\Recommendation\Enums\EntryWindowProposalStatus;
use Database\Factories\Recommendation\EntryWindowProposalFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Part 1 output (D2). Confirmed window feeds Part 2 (D3). data_versions = the three run FKs.
 * Rows are inserted by an AI worker; the Backend may change only the fillable columns (CT-15).
 * Generated, never written: `confirmed_key`.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'entry_window_proposals')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['status', 'confirmed_window_id', 'confirmed_by', 'confirmed_at', 'rejection_reason', 'rejected_by', 'rejected_at'])]
#[UseFactory(EntryWindowProposalFactory::class)]
class EntryWindowProposal extends Model
{
    /** @use HasFactory<EntryWindowProposalFactory> */
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
            'peak_congestion_score' => 'decimal:3',
            'status' => EntryWindowProposalStatus::class,
            'generated_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MlModelVersion, $this>
     */
    public function biasCorrectionVersion(): BelongsTo
    {
        return $this->belongsTo(MlModelVersion::class, 'bias_correction_version_id');
    }

    /**
     * @return BelongsTo<ForecastRun, $this>
     */
    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class, 'forecast_run_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @return BelongsTo<BerthingRequest, $this>
     */
    public function berthingRequest(): BelongsTo
    {
        return $this->belongsTo(BerthingRequest::class, 'berthing_request_id');
    }

    /**
     * @return BelongsTo<TidePredictionRun, $this>
     */
    public function tidePredictionRun(): BelongsTo
    {
        return $this->belongsTo(TidePredictionRun::class, 'tide_prediction_run_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * @return BelongsTo<FeasibleWindow, $this>
     */
    public function confirmedWindow(): BelongsTo
    {
        return $this->belongsTo(FeasibleWindow::class, 'confirmed_window_id');
    }

    /**
     * @return HasMany<Part1Run, $this>
     */
    public function part1Runs(): HasMany
    {
        return $this->hasMany(Part1Run::class, 'entry_window_proposal_id');
    }

    /**
     * @return HasMany<FeasibleWindow, $this>
     */
    public function feasibleWindowsByProposal(): HasMany
    {
        return $this->hasMany(FeasibleWindow::class, 'proposal_id');
    }

    /**
     * @return HasMany<BlockedPeriod, $this>
     */
    public function blockedPeriodsByProposal(): HasMany
    {
        return $this->hasMany(BlockedPeriod::class, 'proposal_id');
    }

    /**
     * @return HasMany<Recommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class, 'entry_window_proposal_id');
    }
}
