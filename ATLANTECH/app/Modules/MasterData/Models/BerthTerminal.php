<?php

namespace App\Modules\MasterData\Models;

use Database\Factories\MasterData\BerthTerminalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * One row per terminal allowed to use the berth. Normal berth = 1 row, shared berth = more. Backend: every berth has at least one row.
 */
#[Table(name: 'berth_terminals', timestamps: false, incrementing: false)]
#[Fillable(['berth_id', 'terminal_id'])]
#[UseFactory(BerthTerminalFactory::class)]
class BerthTerminal extends Pivot
{
    /** @use HasFactory<BerthTerminalFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Berth, $this>
     */
    public function berth(): BelongsTo
    {
        return $this->belongsTo(Berth::class, 'berth_id');
    }

    /**
     * @return BelongsTo<Terminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }
}
