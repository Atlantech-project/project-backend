<?php

namespace App\Modules\Yard\Models;

use App\Modules\Access\Models\User;
use App\Modules\Shared\Enums\RunStatus;
use App\Modules\Yard\Enums\YardRunErrorCategory;
use App\Modules\Yard\Enums\YardRunOutcome;
use Database\Factories\Yard\YardRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `yard_runs` table.
 * Columns written by an AI worker are not fillable (CT-15): `status`, `outcome`, `error_category`, `retry_count`, `result_detail`, `worker_version`, `started_at`, `finished_at`.
 * Generated, never written: `active_key`.
 */
#[Table(name: 'yard_runs', timestamps: false)]
#[Fillable(['terminal_recommendation_id', 'triggered_by', 'queued_at', 'result_handled_at'])]
#[UseFactory(YardRunFactory::class)]
class YardRun extends Model
{
    /** @use HasFactory<YardRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'outcome' => YardRunOutcome::class,
            'error_category' => YardRunErrorCategory::class,
            'result_detail' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'result_handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TerminalRecommendation, $this>
     */
    public function terminalRecommendation(): BelongsTo
    {
        return $this->belongsTo(TerminalRecommendation::class, 'terminal_recommendation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
