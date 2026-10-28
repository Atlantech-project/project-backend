<?php

namespace App\Modules\MasterData\Models;

use App\Modules\Shared\Enums\ComponentType;
use Database\Factories\MasterData\StatusTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Allowed field statuses per component type (P19-01). New status = INSERT, not ALTER.
 */
#[Table(name: 'status_types', timestamps: false, incrementing: false)]
#[Fillable(['component_type', 'code', 'name'])]
#[UseFactory(StatusTypeFactory::class)]
class StatusType extends Pivot
{
    /** @use HasFactory<StatusTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'component_type' => ComponentType::class,
        ];
    }
}
