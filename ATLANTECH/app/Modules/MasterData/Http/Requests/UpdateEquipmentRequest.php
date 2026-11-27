<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Shared\Support\VersionToken;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of `PUT /api/v1/terminals/{terminal}/equipment/{equipment}` (P2-F4): the three quantities and the full capability
 * list (it replaces the stored one), with the `version`. The category and the terminal never change.
 */
class UpdateEquipmentRequest extends FormRequest
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
            ...EquipmentRules::quantitiesAndCapabilities(),
            'equipment_category_id' => ['prohibited'],
            'terminal_id' => ['prohibited'],
            'version' => VersionToken::rules(),
        ];
    }

    /**
     * @return list<Closure>
     */
    public function after(): array
    {
        return [EquipmentRules::unitsWithinQuantity()];
    }
}
