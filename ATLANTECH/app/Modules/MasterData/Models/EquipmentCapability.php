<?php

namespace App\Modules\MasterData\Models;

use Database\Factories\MasterData\EquipmentCapabilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `equipment_capabilities` table.
 */
#[Table(name: 'equipment_capabilities', timestamps: false)]
#[Fillable(['equipment_id', 'capability_type_id', 'capability_value'])]
#[UseFactory(EquipmentCapabilityFactory::class)]
class EquipmentCapability extends Model
{
    /** @use HasFactory<EquipmentCapabilityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capability_value' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    /**
     * @return BelongsTo<CapabilityType, $this>
     */
    public function capabilityType(): BelongsTo
    {
        return $this->belongsTo(CapabilityType::class, 'capability_type_id');
    }
}
