<?php

namespace App\Modules\Shared\Support;

use App\Modules\Shared\Errors\ApiProblemException;

/**
 * The port's minimum mobile app version (`port_settings.min_app_version`, P1-B2 answer 6). Read by `GET /system/info` (P1-C5) and by the 426
 * check (`EnsureMinAppVersion`, P1-C3). Defined here because the check lives in Access, which may not use MasterData; MasterData binds it.
 */
interface MinAppVersionSource
{
    /**
     * @return string An `x.y.z` version, e.g. "1.0.0".
     *
     * @throws ApiProblemException 503 SERVICE_UNAVAILABLE when the setting is missing.
     */
    public function minAppVersion(): string;
}
