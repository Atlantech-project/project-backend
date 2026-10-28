<?php

namespace App\Modules\MasterData\Models;

use App\Modules\MasterData\Enums\CapabilityTypeUnit;
use Database\Factories\MasterData\CapabilityTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `capability_types` table.
 */
#[Table(name: 'capability_types', timestamps: false)]
#[Fillable(['code', 'name', 'unit'])]
#[UseFactory(CapabilityTypeFactory::class)]
class CapabilityType extends Model
{
    /** @use HasFactory<CapabilityTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => CapabilityTypeUnit::class,
        ];
    }

    /**
     * @return HasMany<EquipmentCapability, $this>
     */
    public function equipmentCapabilities(): HasMany
    {
        return $this->hasMany(EquipmentCapability::class, 'capability_type_id');
    }
}
