<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Models\PortShift;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One port shift (P2-F3): times as `HH:MM` in port local time; `ends_next_day` when the end is before the start.
 *
 * @mixin PortShift
 */
class PortShiftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $start = substr($this->start_time, 0, 5);
        $end = substr($this->end_time, 0, 5);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'start_time' => $start,
            'end_time' => $end,
            'ends_next_day' => $end < $start,
            'is_active' => $this->is_active,
            'version' => VersionToken::of($this->resource),
        ];
    }
}
