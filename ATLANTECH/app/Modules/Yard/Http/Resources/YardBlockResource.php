<?php

namespace App\Modules\Yard\Http\Resources;

use App\Modules\Shared\Support\VersionToken;
use App\Modules\Yard\Models\YardBlock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A yard block with its size, counted from its slots (P2-F4). Load the slot count and the maximum bay, row and tier first.
 *
 * @mixin YardBlock
 */
class YardBlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'yard_zone_id' => $this->yard_zone_id,
            'code' => $this->code,
            'bays' => (int) $this->yard_slots_max_bay,
            'rows' => (int) $this->yard_slots_max_row_no,
            'tiers' => (int) $this->yard_slots_max_tier,
            'slots_count' => (int) $this->yard_slots_count,
            'version' => VersionToken::of($this->resource),
        ];
    }
}
