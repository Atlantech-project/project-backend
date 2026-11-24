<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A berth (P2-F2, P-28). Measurements are JSON numbers in metres; coordinates are nullable numbers. `terminals` is present
 * when loaded, as a short list (id, code, name, terminal_type, is_active), so the frontend sees a shared berth (DEC-51) and
 * a link to an inactive terminal.
 *
 * @mixin Berth
 */
class BerthResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'length' => (float) $this->length,
            'water_depth' => (float) $this->water_depth,
            'depth_datum' => $this->depth_datum,
            'max_vessel_loa' => (float) $this->max_vessel_loa,
            'status' => $this->status,
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'version' => VersionToken::of($this->resource),
            'terminals' => $this->whenLoaded('terminals', fn () => $this->terminals->map(fn (Terminal $terminal): array => [
                'id' => $terminal->id,
                'code' => $terminal->code,
                'name' => $terminal->name,
                'terminal_type' => $terminal->terminal_type,
                'is_active' => $terminal->is_active,
            ])->values()),
        ];
    }
}
