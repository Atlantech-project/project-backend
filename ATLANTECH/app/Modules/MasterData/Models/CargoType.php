<?php

namespace App\Modules\MasterData\Models;

use App\Modules\MasterData\Enums\CargoTypeCategory;
use Database\Factories\MasterData\CargoTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `cargo_types` table.
 */
#[Table(name: 'cargo_types', timestamps: false)]
#[Fillable(['code', 'name', 'category', 'imdg_class', 'min_temperature', 'max_temperature'])]
#[UseFactory(CargoTypeFactory::class)]
class CargoType extends Model
{
    /** @use HasFactory<CargoTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => CargoTypeCategory::class,
            'min_temperature' => 'decimal:2',
            'max_temperature' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ImdgClass, $this>
     */
    public function imdgClass(): BelongsTo
    {
        return $this->belongsTo(ImdgClass::class, 'imdg_class');
    }

    /**
     * @return HasMany<TerminalCargoType, $this>
     */
    public function terminalCargoTypes(): HasMany
    {
        return $this->hasMany(TerminalCargoType::class, 'cargo_type_id');
    }
}
