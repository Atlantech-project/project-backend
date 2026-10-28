<?php

namespace App\Modules\MasterData\Models;

use App\Modules\MasterData\Enums\ContainerSizeTypeGroup;
use Database\Factories\MasterData\ContainerSizeTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `container_size_types` table.
 * Generated, never written: `is_reefer`.
 */
#[Table(name: 'container_size_types', timestamps: false)]
#[Fillable(['code', 'length_ft', 'is_high_cube', 'type_group', 'teu_factor'])]
#[UseFactory(ContainerSizeTypeFactory::class)]
class ContainerSizeType extends Model
{
    /** @use HasFactory<ContainerSizeTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_high_cube' => 'boolean',
            'type_group' => ContainerSizeTypeGroup::class,
            'teu_factor' => 'decimal:2',
            'is_reefer' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Container, $this>
     */
    public function containersBySizeType(): HasMany
    {
        return $this->hasMany(Container::class, 'size_type_id');
    }
}
