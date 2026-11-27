<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Models\Equipment;
use App\Modules\MasterData\Models\EquipmentCapability;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One equipment row of a terminal (P2-F4). Load `equipmentCategory` and `equipmentCapabilities.capabilityType` first.
 *
 * @mixin Equipment
 */
class EquipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'terminal_id' => $this->terminal_id,
            'equipment_category' => [
                'id' => $this->equipmentCategory->id,
                'code' => $this->equipmentCategory->code,
                'name' => $this->equipmentCategory->name,
                'equipment_role' => $this->equipmentCategory->equipment_role,
            ],
            'quantity' => (int) $this->quantity,
            'maintenance_quantity' => (int) $this->maintenance_quantity,
            'blocked_quantity' => (int) $this->blocked_quantity,
            'available_quantity' => (int) $this->available_quantity,
            'capabilities' => $this->equipmentCapabilities->sortBy('capability_type_id')->values()->map(fn (EquipmentCapability $capability): array => [
                'capability_type_id' => $capability->capability_type_id,
                'code' => $capability->capabilityType->code,
                'name' => $capability->capabilityType->name,
                'unit' => $capability->capabilityType->unit,
                'value' => (float) $capability->capability_value,
            ]),
            'version' => VersionToken::of($this->resource),
        ];
    }
}
