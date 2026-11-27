<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Models\EquipmentCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EquipmentCategory
 */
class EquipmentCategoryResource extends JsonResource
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
            'equipment_role' => $this->equipment_role,
        ];
    }
}
