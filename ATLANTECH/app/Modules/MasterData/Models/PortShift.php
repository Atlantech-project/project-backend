<?php

namespace App\Modules\MasterData\Models;

use Database\Factories\MasterData\PortShiftFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fixed port shifts, defined on the settings screen. App rule: active shifts must not overlap.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'port_shifts')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['code', 'name', 'start_time', 'end_time', 'is_active'])]
#[UseFactory(PortShiftFactory::class)]
class PortShift extends Model
{
    /** @use HasFactory<PortShiftFactory> */
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
            'is_active' => 'boolean',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PilotSchedule, $this>
     */
    public function pilotSchedules(): HasMany
    {
        return $this->hasMany(PilotSchedule::class, 'port_shift_id');
    }

    /**
     * @return HasMany<TugMasterSchedule, $this>
     */
    public function tugMasterSchedules(): HasMany
    {
        return $this->hasMany(TugMasterSchedule::class, 'port_shift_id');
    }
}
