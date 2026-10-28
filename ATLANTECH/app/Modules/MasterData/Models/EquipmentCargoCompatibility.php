<?php

namespace App\Modules\MasterData\Models;

use Database\Factories\MasterData\EquipmentCargoCompatibilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The `equipment_cargo_compatibilities` table.
 */
#[Table(name: 'equipment_cargo_compatibilities', timestamps: false, incrementing: false)]
#[Fillable(['equipment_id', 'terminal_id', 'cargo_type_id'])]
#[UseFactory(EquipmentCargoCompatibilityFactory::class)]
class EquipmentCargoCompatibility extends Pivot
{
    /** @use HasFactory<EquipmentCargoCompatibilityFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    /**
     * @return BelongsTo<TerminalCargoType, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(TerminalCargoType::class, 'terminal_id', 'terminal_id');
    }
}
