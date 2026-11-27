<?php

namespace App\Modules\Yard\Http\Resources;

use App\Modules\Yard\Models\YardZoneType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A yard zone type reference row.
 *
 * @mixin YardZoneType
 */
class YardZoneTypeResource extends JsonResource
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
            'zone_detail' => $this->zone_detail,
        ];
    }
}
