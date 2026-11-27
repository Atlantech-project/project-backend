<?php

namespace App\Modules\Yard\Http\Requests;

use App\Modules\Shared\Support\VersionToken;
use App\Modules\Yard\Models\YardZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of PUT /api/v1/terminals/{terminal}/yard-zones/{yardZone} (P2-F4): the code and the full detail of the zone kind. The zone type never changes.
 */
class UpdateYardZoneRequest extends FormRequest
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
        $zone = $this->route('yardZone');
        $zone = $zone instanceof YardZone ? $zone : null;

        return [
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('yard_zones', 'code')->where('container_yard_id', $zone?->container_yard_id)->ignore($zone?->id),
            ],
            'yard_zone_type_id' => ['prohibited'],
            'zone_detail' => ['prohibited'],
            ...YardZoneDetailRules::for($zone?->zone_detail),
            'version' => VersionToken::rules(),
        ];
    }
}
