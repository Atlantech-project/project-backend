<?php

namespace App\Modules\MasterData\Models;

use App\Modules\MasterData\Enums\TerminalProductivityProfileRateUnit;
use App\Modules\Shared\Enums\TerminalType;
use Database\Factories\MasterData\TerminalProductivityProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inputs for the AI service-time calculation (Module 15). Overlapping periods rejected by the backend.
 */
#[Table(name: 'terminal_productivity_profiles', timestamps: false)]
#[Fillable(['terminal_id', 'terminal_type', 'vessel_type_id', 'productivity_rate', 'rate_unit', 'fixed_overhead_minutes', 'valid_from', 'valid_to'])]
#[UseFactory(TerminalProductivityProfileFactory::class)]
class TerminalProductivityProfile extends Model
{
    /** @use HasFactory<TerminalProductivityProfileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'terminal_type' => TerminalType::class,
            'productivity_rate' => 'decimal:2',
            'rate_unit' => TerminalProductivityProfileRateUnit::class,
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Terminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    /**
     * @return BelongsTo<VesselType, $this>
     */
    public function vesselType(): BelongsTo
    {
        return $this->belongsTo(VesselType::class, 'vessel_type_id');
    }
}
