<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Models\CapabilityType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CapabilityType
 */
class CapabilityTypeResource extends JsonResource
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
            'unit' => $this->unit,
        ];
    }
}
