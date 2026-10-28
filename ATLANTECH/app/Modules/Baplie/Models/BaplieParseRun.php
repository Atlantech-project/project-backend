<?php

namespace App\Modules\Baplie\Models;

use App\Modules\Access\Models\User;
use App\Modules\Baplie\Enums\BaplieParseRunErrorCategory;
use App\Modules\Baplie\Enums\BaplieParseRunOutcome;
use App\Modules\Baplie\Enums\BaplieParseRunSource;
use App\Modules\Shared\Enums\RunStatus;
use Database\Factories\Baplie\BaplieParseRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `baplie_parse_runs` table.
 * Columns written by an AI worker are not fillable (CT-15): `status`, `outcome`, `error_category`, `retry_count`, `container_count`, `result_detail`, `worker_version`, `started_at`, `finished_at`.
 * Generated, never written: `active_key`.
 */
#[Table(name: 'baplie_parse_runs', timestamps: false)]
#[Fillable(['baplie_file_id', 'source', 'triggered_by', 'queued_at', 'result_handled_at'])]
#[UseFactory(BaplieParseRunFactory::class)]
class BaplieParseRun extends Model
{
    /** @use HasFactory<BaplieParseRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => BaplieParseRunSource::class,
            'status' => RunStatus::class,
            'outcome' => BaplieParseRunOutcome::class,
            'error_category' => BaplieParseRunErrorCategory::class,
            'result_detail' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'result_handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BaplieFile, $this>
     */
    public function baplieFile(): BelongsTo
    {
        return $this->belongsTo(BaplieFile::class, 'baplie_file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
