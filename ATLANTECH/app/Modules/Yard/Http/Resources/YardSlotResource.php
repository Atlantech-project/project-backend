<?php

namespace App\Modules\Yard\Http\Resources;

use App\Modules\Shared\Support\VersionToken;
use App\Modules\Yard\Models\YardSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One slot of a block (P2-F4). occupied says only that a container is recorded in it.
 *
 * @mixin YardSlot
 */
class YardSlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'yard_block_id' => $this->yard_block_id,
            'bay' => $this->bay,
            'row_no' => $this->row_no,
            'tier' => $this->tier,
            'status' => $this->status,
            'occupied' => $this->container_id !== null,
            'version' => VersionToken::of($this->resource),
        ];
    }
}
