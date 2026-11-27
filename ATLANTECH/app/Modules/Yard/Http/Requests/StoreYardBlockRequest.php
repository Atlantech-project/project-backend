<?php

namespace App\Modules\Yard\Http\Requests;

use App\Modules\Yard\Models\YardZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of POST /api/v1/terminals/{terminal}/yard-zones/{yardZone}/blocks (P2-F4). The code is unique per zone.
 */
class StoreYardBlockRequest extends FormRequest
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

        return [
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('yard_blocks', 'code')->where('yard_zone_id', $zone instanceof YardZone ? $zone->id : null),
            ],
        ];
    }
}
