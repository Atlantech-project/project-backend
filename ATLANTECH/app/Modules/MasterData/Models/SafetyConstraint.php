<?php

namespace App\Modules\MasterData\Models;

use App\Modules\Access\Models\User;
use Database\Factories\MasterData\SafetyConstraintFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Overlap of the same type + scope is rejected by the backend. Several scopes apply to one case: the STRICTEST value wins (Moustafa, OQ-P9-2).
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'safety_constraints')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['constraint_type_id', 'terminal_id', 'berth_id', 'vessel_type_id', 'imdg_class', 'equipment_category_id', 'value', 'created_by', 'active_from', 'active_to'])]
#[UseFactory(SafetyConstraintFactory::class)]
class SafetyConstraint extends Model
{
    /** @use HasFactory<SafetyConstraintFactory> */
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
            'value' => 'decimal:3',
            'active_from' => 'datetime',
            'active_to' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Berth, $this>
     */
    public function berth(): BelongsTo
    {
        return $this->belongsTo(Berth::class, 'berth_id');
    }

    /**
     * @return BelongsTo<EquipmentCategory, $this>
     */
    public function equipmentCategory(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    /**
     * @return BelongsTo<ImdgClass, $this>
     */
    public function imdgClass(): BelongsTo
    {
        return $this->belongsTo(ImdgClass::class, 'imdg_class');
    }

    /**
     * @return BelongsTo<Terminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    /**
     * @return BelongsTo<ConstraintType, $this>
     */
    public function constraintType(): BelongsTo
    {
        return $this->belongsTo(ConstraintType::class, 'constraint_type_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<VesselType, $this>
     */
    public function vesselType(): BelongsTo
    {
        return $this->belongsTo(VesselType::class, 'vessel_type_id');
    }
}
