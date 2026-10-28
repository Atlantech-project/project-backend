<?php

namespace App\Modules\MasterData\Models;

use Database\Factories\MasterData\ContainerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Identity only. Per-call attributes (weight, stowage, ports) live in container_manifest_entry.
 * Rows are inserted by an AI worker; the Backend never changes them (CT-15).
 */
#[Table(name: 'containers', timestamps: false)]
#[Fillable([])]
#[UseFactory(ContainerFactory::class)]
class Container extends Model
{
    /** @use HasFactory<ContainerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ContainerSizeType, $this>
     */
    public function sizeType(): BelongsTo
    {
        return $this->belongsTo(ContainerSizeType::class, 'size_type_id');
    }
}
