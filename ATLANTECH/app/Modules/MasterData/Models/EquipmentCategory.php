<?php

namespace App\Modules\MasterData\Models;

use App\Modules\MasterData\Enums\EquipmentRole;
use Database\Factories\MasterData\EquipmentCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `equipment_categories` table.
 */
#[Table(name: 'equipment_categories', timestamps: false)]
#[Fillable(['code', 'name', 'equipment_role'])]
#[UseFactory(EquipmentCategoryFactory::class)]
class EquipmentCategory extends Model
{
    /** @use HasFactory<EquipmentCategoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'equipment_role' => EquipmentRole::class,
        ];
    }

    /**
     * @return HasMany<SafetyConstraint, $this>
     */
    public function safetyConstraints(): HasMany
    {
        return $this->hasMany(SafetyConstraint::class, 'equipment_category_id');
    }

    /**
     * @return HasMany<Equipment, $this>
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'equipment_category_id');
    }
}
