<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\PortProfile;
use Carbon\CarbonImmutable;

/**
 * The port's own calendar: "today" in `port_profiles.timezone` (P2-F3). Schedule dates (`shift_date`) are port-local
 * dates, so "from today on" is compared with this date, never with the UTC date.
 */
class PortClock
{
    public const int PORT_PROFILE_ID = 1;

    public static function timezone(): string
    {
        return PortProfile::query()->findOrFail(self::PORT_PROFILE_ID)->timezone;
    }

    public static function today(): string
    {
        return CarbonImmutable::now(self::timezone())->toDateString();
    }
}
