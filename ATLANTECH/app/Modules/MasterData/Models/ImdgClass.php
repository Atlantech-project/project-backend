<?php

namespace App\Modules\MasterData\Models;

use Database\Factories\MasterData\ImdgClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fixed IMDG list; one source for every imdg_class column.
 */
#[Table(name: 'imdg_classes', timestamps: false, key: 'code', incrementing: false, keyType: 'string')]
#[Fillable(['code', 'name'])]
#[UseFactory(ImdgClassFactory::class)]
class ImdgClass extends Model
{
    /** @use HasFactory<ImdgClassFactory> */
    use HasFactory;

    /**
     * @return HasMany<SafetyConstraint, $this>
     */
    public function safetyConstraintsByImdgClass(): HasMany
    {
        return $this->hasMany(SafetyConstraint::class, 'imdg_class');
    }

    /**
     * @return HasMany<CargoType, $this>
     */
    public function cargoTypesByImdgClass(): HasMany
    {
        return $this->hasMany(CargoType::class, 'imdg_class');
    }
}
