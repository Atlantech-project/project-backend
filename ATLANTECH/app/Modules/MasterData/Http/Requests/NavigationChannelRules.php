<?php

namespace App\Modules\MasterData\Http\Requests;

/**
 * The channel measurements shared by create and update: `depth` DECIMAL(6,2), `width` DECIMAL(7,2) and
 * `speed_limit_knots` DECIMAL(4,1), all > 0 (`chk_channel_dimensions`).
 */
final class NavigationChannelRules
{
    /**
     * @param  bool  $required  True on create; on update every field is optional.
     * @return array<string, list<string>>
     */
    public static function measurements(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'depth' => [$presence, 'numeric', 'decimal:0,2', 'gt:0', 'max:9999.99'],
            'depth_datum' => [$presence, 'required', 'string', 'max:40'],
            'width' => [$presence, 'numeric', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'speed_limit_knots' => [$presence, 'numeric', 'decimal:0,1', 'gt:0', 'max:999.9'],
        ];
    }
}
