<?php

namespace App\Modules\MasterData\Http\Requests;

use Closure;
use Illuminate\Validation\Validator;

/**
 * Rules shared by the equipment requests (P2-F4, FR-MD-11). They repeat `chk_eq_quantity`, `chk_ecap_value` and the column
 * sizes, so a bad value is a 422 and never a database error. `available_quantity` is computed by the database.
 */
class EquipmentRules
{
    private const int MAX_QUANTITY = 100000;

    /**
     * @return array<string, mixed>
     */
    public static function quantitiesAndCapabilities(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
            'maintenance_quantity' => ['required', 'integer', 'min:0', 'max:'.self::MAX_QUANTITY],
            'blocked_quantity' => ['required', 'integer', 'min:0', 'max:'.self::MAX_QUANTITY],
            'capabilities' => ['present', 'array', 'max:50'],
            'capabilities.*.capability_type_id' => ['required', 'integer', 'distinct', 'exists:capability_types,id'],
            'capabilities.*.value' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'available_quantity' => ['prohibited'],
        ];
    }

    /**
     * Units in maintenance plus blocked units may not exceed the quantity. Checked only when the three fields are valid.
     *
     * @return Closure(Validator): void
     */
    public static function unitsWithinQuantity(): Closure
    {
        return function (Validator $validator): void {
            if ($validator->errors()->hasAny(['quantity', 'maintenance_quantity', 'blocked_quantity'])) {
                return;
            }

            $data = $validator->getData();

            if ((int) $data['maintenance_quantity'] + (int) $data['blocked_quantity'] > (int) $data['quantity']) {
                $validator->errors()->add('quantity', 'errors.equipment.units_exceed_quantity');
            }
        };
    }
}
