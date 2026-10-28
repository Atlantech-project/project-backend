<?php

namespace App\Modules\Berthing\Models;

use App\Modules\Access\Models\User;
use App\Modules\Berthing\Enums\BerthingRequestSource;
use App\Modules\Berthing\Enums\BerthingRequestStatus;
use App\Modules\Berthing\Enums\DataSource;
use App\Modules\MasterData\Models\ExternalSystem;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Models\Vessel;
use Database\Factories\Berthing\BerthingRequestFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * status = current state; history in berthing_request_status_history (same transaction). Return for revision only from SUBMITTED; ENTRY_WINDOW_CONFIRMED may be reopened to CUSTOMS_APPROVED while no plan is confirmed. Cancel only before Preliminary Approval.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'berthing_requests')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['vessel_id', 'requested_terminal_id', 'source', 'external_system_id', 'external_reference', 'submitted_by', 'requested_eta', 'arrival_draft', 'departure_draft', 'previous_port', 'next_port', 'draft_source', 'eta_source', 'ports_source', 'block_coefficient', 'gross_tonnage', 'deadweight_tonnes', 'status'])]
#[UseFactory(BerthingRequestFactory::class)]
class BerthingRequest extends Model
{
    /** @use HasFactory<BerthingRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => BerthingRequestSource::class,
            'requested_eta' => 'datetime',
            'arrival_draft' => 'decimal:2',
            'departure_draft' => 'decimal:2',
            'draft_source' => DataSource::class,
            'eta_source' => DataSource::class,
            'ports_source' => DataSource::class,
            'block_coefficient' => 'decimal:3',
            'status' => BerthingRequestStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ExternalSystem, $this>
     */
    public function externalSystem(): BelongsTo
    {
        return $this->belongsTo(ExternalSystem::class, 'external_system_id');
    }

    /**
     * @return BelongsTo<Terminal, $this>
     */
    public function requestedTerminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'requested_terminal_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<Vessel, $this>
     */
    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class, 'vessel_id');
    }

    /**
     * @return HasMany<BerthingRequestCargo, $this>
     */
    public function berthingRequestCargos(): HasMany
    {
        return $this->hasMany(BerthingRequestCargo::class, 'berthing_request_id');
    }

    /**
     * @return HasMany<BerthingRequestStatusHistory, $this>
     */
    public function berthingRequestStatusHistories(): HasMany
    {
        return $this->hasMany(BerthingRequestStatusHistory::class, 'berthing_request_id');
    }
}
