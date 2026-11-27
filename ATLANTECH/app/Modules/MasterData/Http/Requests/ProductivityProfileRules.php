<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\MasterData\Enums\TerminalProductivityProfileRateUnit;
use Illuminate\Validation\Rule;

/**
 * The fields of a productivity profile shared by create and update: `productivity_rate` DECIMAL(10,2) > 0 and
 * `fixed_overhead_minutes` >= 0 (`chk_tpp_values`). Only container terminals write, so the unit is MOVES_PER_HOUR
 * (`chk_tpp_unit`). The terminal and its type come from the URL and are refused in the body.
 */
final class ProductivityProfileRules
{
    /**
     * @param  bool  $required  True on create; on update every field is optional.
     * @return array<string, list<mixed>>
     */
    public static function fields(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'vessel_type_id' => ['sometimes', 'nullable', 'integer', 'exists:vessel_types,id'],
            'productivity_rate' => [$presence, 'required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'rate_unit' => [$presence, 'required', 'string', Rule::in([TerminalProductivityProfileRateUnit::MovesPerHour->value])],
            'fixed_overhead_minutes' => [$presence, 'required', 'integer', 'min:0', 'max:2147483647'],
            'valid_from' => [$presence, 'required', 'date_format:Y-m-d'],
            'valid_to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'terminal_id' => ['prohibited'],
            'terminal_type' => ['prohibited'],
        ];
    }
}
