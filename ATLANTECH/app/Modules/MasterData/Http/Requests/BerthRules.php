<?php

namespace App\Modules\MasterData\Http\Requests;

/**
 * The berth measurements shared by create and update: `length` and `max_vessel_loa` are DECIMAL(7,2), `water_depth`
 * DECIMAL(6,2), all > 0 (`chk_berth_dimensions`); latitude and longitude DECIMAL(9,6) within their ranges.
 */
final class BerthRules
{
    /**
     * @param  bool  $required  True on create; on update every field is optional.
     * @param  bool  $loaWithinLength  Compare `max_vessel_loa` with the sent `length` (`chk_berth_loa_fits`).
     * @return array<string, list<string>>
     */
    public static function dimensions(bool $required, bool $loaWithinLength): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'length' => [$presence, 'numeric', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'water_depth' => [$presence, 'numeric', 'decimal:0,2', 'gt:0', 'max:9999.99'],
            'depth_datum' => [$presence, 'string', 'max:40'],
            'max_vessel_loa' => [$presence, 'numeric', 'decimal:0,2', 'gt:0', 'max:99999.99', ...($loaWithinLength ? ['lte:length'] : [])],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'decimal:0,6', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'decimal:0,6', 'between:-180,180'],
        ];
    }
}
