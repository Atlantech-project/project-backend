<?php

namespace App\Modules\Plan\Models;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\Plan\Enums\PlanComponentReservationState;
use App\Modules\Shared\Enums\ComponentType;
use Database\Factories\Plan\PlanComponentFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per dispatched unit (one per tug). Berth/tug occupancy = RESERVED rows overlapping a window (D-20).
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'plan_components')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['operational_plan_id', 'component_type', 'berth_id', 'tugboat_id', 'terminal_id', 'assigned_user_id', 'assigned_by', 'assigned_at', 'planned_start', 'planned_end', 'reservation_state'])]
#[UseFactory(PlanComponentFactory::class)]
class PlanComponent extends Model
{
    /** @use HasFactory<PlanComponentFactory> */
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
            'component_type' => ComponentType::class,
            'assigned_at' => 'datetime',
            'planned_start' => 'datetime',
            'planned_end' => 'datetime',
            'reservation_state' => PlanComponentReservationState::class,
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * @return BelongsTo<Berth, $this>
     */
    public function berth(): BelongsTo
    {
        return $this->belongsTo(Berth::class, 'berth_id');
    }

    /**
     * @return BelongsTo<OperationalPlan, $this>
     */
    public function operationalPlan(): BelongsTo
    {
        return $this->belongsTo(OperationalPlan::class, 'operational_plan_id');
    }

    /**
     * @return BelongsTo<Terminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    /**
     * @return BelongsTo<Tugboat, $this>
     */
    public function tugboat(): BelongsTo
    {
        return $this->belongsTo(Tugboat::class, 'tugboat_id');
    }
}
