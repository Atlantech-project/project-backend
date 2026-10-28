<?php

namespace App\Modules\Baplie\Models;

use App\Modules\Baplie\Enums\ContainerManifestEntryFullEmpty;
use App\Modules\MasterData\Models\Container;
use App\Modules\MasterData\Models\ImdgClass;
use Database\Factories\Baplie\ContainerManifestEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-call container data from BAPLIE. Container identity in container (Module 5).
 * Rows are inserted by an AI worker; the Backend never changes them (CT-15).
 */
#[Table(name: 'container_manifest_entries', timestamps: false)]
#[Fillable([])]
#[UseFactory(ContainerManifestEntryFactory::class)]
class ContainerManifestEntry extends Model
{
    /** @use HasFactory<ContainerManifestEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'full_empty' => ContainerManifestEntryFullEmpty::class,
            'reefer_set_temperature' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Container, $this>
     */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class, 'container_id');
    }

    /**
     * @return BelongsTo<BaplieFile, $this>
     */
    public function baplieFile(): BelongsTo
    {
        return $this->belongsTo(BaplieFile::class, 'baplie_file_id');
    }

    /**
     * @return BelongsTo<ImdgClass, $this>
     */
    public function imdgClass(): BelongsTo
    {
        return $this->belongsTo(ImdgClass::class, 'imdg_class');
    }
}
