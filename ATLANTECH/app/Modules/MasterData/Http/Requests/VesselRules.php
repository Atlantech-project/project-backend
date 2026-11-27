<?php

namespace App\Modules\MasterData\Http\Requests;

/**
 * The vessel particulars within their columns: `loa` DECIMAL(7,2), `beam` DECIMAL(6,2), `max_draft` and `moulded_depth`
 * DECIMAL(5,2), all above 0 (`chk_vessel_dims`, `chk_vessel_tonnage`). `beam` below `loa` is checked by the caller,
 * which knows both values.
 */
final class VesselRules
{
    /**
     * @param  bool  $required  True when the vessel is being created; an update sends only what changes.
     * @return array<string, list<string>>
     */
    public static function particulars(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'name' => [$presence, 'string', 'max:120'],
            'vessel_type_id' => [$presence, 'integer', 'exists:vessel_types,id'],
            'loa' => [$presence, 'numeric', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'beam' => [$presence, 'numeric', 'decimal:0,2', 'gt:0', 'max:9999.99'],
            'max_draft' => [$presence, 'numeric', 'decimal:0,2', 'gt:0', 'max:999.99'],
            'net_tonnage' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:2147483647'],
            'moulded_depth' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:999.99'],
            'call_sign' => ['sometimes', 'nullable', 'string', 'max:10'],
            'flag_state' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Z]{2}$/'],
        ];
    }
}
