<?php

namespace App\Modules\MasterData\Models;

use Database\Factories\MasterData\VesselFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Block coefficient (Cb), GT and DWT are per call: they live on berthing_request.
 * Columns written by an AI worker are not fillable (CT-15): `call_sign`, `flag_state`.
 * The Backend may still set them only while NULL (CT-15): it writes them explicitly with `forceFill()`.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'vessels')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['imo_number', 'name', 'vessel_type_id', 'loa', 'beam', 'max_draft', 'net_tonnage', 'moulded_depth'])]
#[UseFactory(VesselFactory::class)]
class Vessel extends Model
{
    /** @use HasFactory<VesselFactory> */
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
            'loa' => 'decimal:2',
            'beam' => 'decimal:2',
            'max_draft' => 'decimal:2',
            'moulded_depth' => 'decimal:2',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<VesselType, $this>
     */
    public function vesselType(): BelongsTo
    {
        return $this->belongsTo(VesselType::class, 'vessel_type_id');
    }
}
