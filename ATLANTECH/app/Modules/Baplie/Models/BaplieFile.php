<?php

namespace App\Modules\Baplie\Models;

use App\Modules\Access\Models\User;
use App\Modules\Baplie\Enums\BaplieFileParseStatus;
use App\Modules\Baplie\Enums\BaplieFileStatus;
use App\Modules\Berthing\Models\BerthingRequest;
use App\Modules\MasterData\Models\Vessel;
use Database\Factories\Baplie\BaplieFileFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Exactly one ACTIVE file per request (active_request_key). Replacing a revision: old ACTIVE -> REPLACED, new -> ACTIVE, in one transaction. Mistaken upload -> WITHDRAWN.
Diagnostics rows live in baplie_parse_issue (no JSON copy here).
 * Columns written by an AI worker are not fillable (CT-15): `parse_status`, `staged_payload`.
 * Generated, never written: `live_hash_key`, `active_request_key`.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'baplie_files')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['vessel_id', 'berthing_request_id', 'uploaded_by', 'file_hash_sha256', 'storage_path', 'status', 'is_manually_corrected', 'corrected_by', 'corrected_at', 'uploaded_at'])]
#[UseFactory(BaplieFileFactory::class)]
class BaplieFile extends Model
{
    /** @use HasFactory<BaplieFileFactory> */
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
            'parse_status' => BaplieFileParseStatus::class,
            'status' => BaplieFileStatus::class,
            'staged_payload' => 'array',
            'is_manually_corrected' => 'boolean',
            'corrected_at' => 'datetime',
            'uploaded_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BerthingRequest, $this>
     */
    public function berthingRequest(): BelongsTo
    {
        return $this->belongsTo(BerthingRequest::class, 'berthing_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<Vessel, $this>
     */
    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class, 'vessel_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    /**
     * @return HasMany<BaplieParseIssue, $this>
     */
    public function baplieParseIssues(): HasMany
    {
        return $this->hasMany(BaplieParseIssue::class, 'baplie_file_id');
    }

    /**
     * @return HasMany<ContainerManifestEntry, $this>
     */
    public function containerManifestEntries(): HasMany
    {
        return $this->hasMany(ContainerManifestEntry::class, 'baplie_file_id');
    }

    /**
     * @return HasMany<BaplieParseRun, $this>
     */
    public function baplieParseRuns(): HasMany
    {
        return $this->hasMany(BaplieParseRun::class, 'baplie_file_id');
    }
}
