<?php

namespace App\Modules\MasterData\Models;

use App\Modules\Shared\Enums\StorageDetailKind;
use Database\Factories\MasterData\StorageTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `storage_types` table.
 */
#[Table(name: 'storage_types', timestamps: false)]
#[Fillable(['code', 'name', 'detail_kind'])]
#[UseFactory(StorageTypeFactory::class)]
class StorageType extends Model
{
    /** @use HasFactory<StorageTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'detail_kind' => StorageDetailKind::class,
        ];
    }

    /**
     * @return HasMany<StorageFacility, $this>
     */
    public function storageFacilities(): HasMany
    {
        return $this->hasMany(StorageFacility::class, 'storage_type_id');
    }
}
