<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Models\Tugboat;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One tug (P2-F3) with its tug master, or null when it has none (DEC-32 rule 2(b): then the tug is not available).
 *
 * @mixin Tugboat
 */
class TugboatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $master = $this->tugMaster;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'tug_power' => $this->tug_power,
            'tug_power_unit' => $this->tug_power_unit,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'tug_master' => $master === null ? null : [
                'user_id' => $master->user_id,
                'name' => $master->user->name,
                'is_active' => $master->user->is_active,
            ],
            'version' => VersionToken::of($this->resource),
        ];
    }
}
