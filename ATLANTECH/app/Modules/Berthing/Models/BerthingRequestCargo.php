<?php

namespace App\Modules\Berthing\Models;

use App\Modules\Berthing\Enums\BerthingRequestCargoOperation;
use App\Modules\Berthing\Enums\BerthingRequestCargoQuantityUnit;
use App\Modules\MasterData\Models\TerminalCargoType;
use Database\Factories\Berthing\BerthingRequestCargoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Cargo of non-container calls (container calls use the BAPLIE manifest).
 */
#[Table(name: 'berthing_request_cargos', timestamps: false, incrementing: false)]
#[Fillable(['berthing_request_id', 'terminal_id', 'cargo_type_id', 'operation', 'quantity', 'quantity_unit'])]
#[UseFactory(BerthingRequestCargoFactory::class)]
class BerthingRequestCargo extends Pivot
{
    /** @use HasFactory<BerthingRequestCargoFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operation' => BerthingRequestCargoOperation::class,
            'quantity' => 'decimal:2',
            'quantity_unit' => BerthingRequestCargoQuantityUnit::class,
        ];
    }

    /**
     * @return BelongsTo<BerthingRequest, $this>
     */
    public function berthingRequest(): BelongsTo
    {
        return $this->belongsTo(BerthingRequest::class, 'berthing_request_id');
    }

    /**
     * @return BelongsTo<TerminalCargoType, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(TerminalCargoType::class, 'terminal_id', 'terminal_id');
    }
}
