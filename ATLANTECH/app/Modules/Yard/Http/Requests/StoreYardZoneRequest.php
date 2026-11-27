<?php

namespace App\Modules\Yard\Http\Requests;

use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\Yard\Models\YardZoneType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of POST /api/v1/terminals/{terminal}/yards/{containerYard}/zones (P2-F4). The code is unique per yard.
 */
class StoreYardZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $yard = $this->route('containerYard');
        $typeId = $this->input('yard_zone_type_id');
        $type = is_numeric($typeId) ? YardZoneType::query()->find((int) $typeId) : null;

        return [
            'yard_zone_type_id' => ['required', 'integer', 'exists:yard_zone_types,id'],
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('yard_zones', 'code')->where('container_yard_id', $yard instanceof StorageFacility ? $yard->id : null),
            ],
            'zone_detail' => ['prohibited'],
            ...($type === null ? [] : YardZoneDetailRules::for($type->zone_detail)),
        ];
    }
}
