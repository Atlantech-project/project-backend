<?php

namespace App\Modules\Yard\Http\Resources;

use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A container yard (P2-F4). It has no capacity of its own.
 *
 * @mixin StorageFacility
 */
class ContainerYardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'terminal_id' => $this->terminal_id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'area' => $this->area === null ? null : (float) $this->area,
            'max_floor_load' => $this->max_floor_load === null ? null : (float) $this->max_floor_load,
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'version' => VersionToken::of($this->resource),
        ];
    }
}
