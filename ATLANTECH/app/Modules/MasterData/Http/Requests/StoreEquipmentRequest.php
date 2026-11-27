<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\MasterData\Models\Terminal;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of `POST /api/v1/terminals/{terminal}/equipment` (P2-F4). A terminal has one row per category. The terminal type is
 * checked by `EquipmentService`.
 */
class StoreEquipmentRequest extends FormRequest
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
        $terminal = $this->route('terminal');

        return [
            'equipment_category_id' => [
                'required', 'integer', 'exists:equipment_categories,id',
                Rule::unique('equipment', 'equipment_category_id')->where('terminal_id', $terminal instanceof Terminal ? $terminal->id : null),
            ],
            ...EquipmentRules::quantitiesAndCapabilities(),
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
