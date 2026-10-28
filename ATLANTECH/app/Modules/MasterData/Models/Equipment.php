<?php

namespace App\Modules\MasterData\Models;

use Database\Factories\MasterData\EquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `equipment` table.
 * Generated, never written: `available_quantity`.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'equipment')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['terminal_id', 'equipment_category_id', 'quantity', 'maintenance_quantity', 'blocked_quantity'])]
#[UseFactory(EquipmentFactory::class)]
class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
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
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EquipmentCategory, $this>
     */
    public function equipmentCategory(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    /**
     * @return BelongsTo<Terminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    /**
     * @return HasMany<EquipmentCapability, $this>
     */
    public function equipmentCapabilities(): HasMany
    {
        return $this->hasMany(EquipmentCapability::class, 'equipment_id');
    }

    /**
     * @return HasMany<EquipmentCargoCompatibility, $this>
     */
    public function equipmentCargoCompatibilities(): HasMany
    {
        return $this->hasMany(EquipmentCargoCompatibility::class, 'equipment_id');
    }

    /**
     * Through `equipment_cargo_compatibilities` (EquipmentCargoCompatibility).
     *
     * @return BelongsToMany<CargoType, $this, EquipmentCargoCompatibility>
     */
    public function cargoTypes(): BelongsToMany
    {
        return $this->belongsToMany(CargoType::class, 'equipment_cargo_compatibilities', 'equipment_id', 'cargo_type_id')->using(EquipmentCargoCompatibility::class);
    }
}
