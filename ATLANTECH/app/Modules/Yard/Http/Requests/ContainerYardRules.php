<?php

namespace App\Modules\Yard\Http\Requests;

use App\Modules\MasterData\Enums\FacilityStatus;
use Illuminate\Validation\Rule;

/**
 * Rules shared by the container yard requests (P2-F4). They repeat the storage_facilities CHECKs and column sizes. A
 * container yard has no capacity of its own: it is counted from its slots, so the capacity fields are refused.
 */
class ContainerYardRules
{
    /**
     * @return array<string, mixed>
     */
    public static function optionalFields(): array
    {
        return [
            'area' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'max_floor_load' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999.99'],
            'status' => ['sometimes', 'required', 'string', Rule::enum(FacilityStatus::class)],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'decimal:0,6', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'decimal:0,6', 'between:-180,180'],
            'capacity' => ['prohibited'],
            'capacity_unit' => ['prohibited'],
            'terminal_id' => ['prohibited'],
            'storage_type_id' => ['prohibited'],
        ];
    }
}
