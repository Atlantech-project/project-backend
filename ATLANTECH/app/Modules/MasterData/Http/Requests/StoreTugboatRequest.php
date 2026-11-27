<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\MasterData\Enums\TugboatStatus;
use App\Modules\MasterData\Enums\TugPowerUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of `POST /api/v1/tugboats` (P2-F3, FR-MD-07). Power only in bollard pull for now (Q11, DEC-20 item 11); the
 * Tug Manager never sets `BUSY`, which comes from the Tug Master's tablet (Q10).
 */
class StoreTugboatRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('tugboats', 'name')],
            'tug_power' => ['required', 'decimal:0,2', 'gt:0', 'max:999999.99'],
            'tug_power_unit' => ['required', Rule::in([TugPowerUnit::BollardPullTonnes->value])], // عشان قولنا ال ai بيشتغل باليونت دي
            'capacity' => ['nullable', 'decimal:0,2', 'gt:0', 'max:999999.99'],
            'status' => ['sometimes', Rule::in(self::settableStatuses())],
        ];
    }

    /**
     * @return list<string>
     */
    public static function settableStatuses(): array
    {
        return [TugboatStatus::Available->value, TugboatStatus::Maintenance->value, TugboatStatus::Inactive->value];
    }
}
