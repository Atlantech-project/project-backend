<?php

namespace App\Modules\Recommendation\Models;

use App\Modules\Berthing\Models\BerthingRequest;
use App\Modules\Recommendation\Enums\Part1RunOutcome;
use App\Modules\Shared\Enums\RunStatus;
use Database\Factories\Recommendation\Part1RunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `part1_runs` table.
 * Columns written by an AI worker are not fillable (CT-15): `status`, `outcome`, `retry_count`, `entry_window_proposal_id`, `started_at`, `finished_at`.
 */
#[Table(name: 'part1_runs', timestamps: false)]
#[Fillable(['berthing_request_id', 'queued_at', 'result_handled_at'])]
#[UseFactory(Part1RunFactory::class)]
class Part1Run extends Model
{
    /** @use HasFactory<Part1RunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'outcome' => Part1RunOutcome::class,
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'result_handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EntryWindowProposal, $this>
     */
    public function entryWindowProposal(): BelongsTo
    {
        return $this->belongsTo(EntryWindowProposal::class, 'entry_window_proposal_id');
    }

    /**
     * @return BelongsTo<BerthingRequest, $this>
     */
    public function berthingRequest(): BelongsTo
    {
        return $this->belongsTo(BerthingRequest::class, 'berthing_request_id');
    }
}
