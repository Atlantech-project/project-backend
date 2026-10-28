<?php

namespace App\Modules\MasterData\Models;

use App\Modules\MasterData\Enums\ConstraintTypeCategory;
use App\Modules\MasterData\Enums\ConstraintTypeComparator;
use App\Modules\MasterData\Enums\ConstraintTypeUnit;
use Database\Factories\MasterData\ConstraintTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `constraint_types` table.
 */
#[Table(name: 'constraint_types', timestamps: false)]
#[Fillable(['code', 'category', 'unit', 'comparator', 'is_hard_safety_limit', 'description'])]
#[UseFactory(ConstraintTypeFactory::class)]
class ConstraintType extends Model
{
    /** @use HasFactory<ConstraintTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ConstraintTypeCategory::class,
            'unit' => ConstraintTypeUnit::class,
            'comparator' => ConstraintTypeComparator::class,
            'is_hard_safety_limit' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SafetyConstraint, $this>
     */
    public function safetyConstraints(): HasMany
    {
        return $this->hasMany(SafetyConstraint::class, 'constraint_type_id');
    }
}
