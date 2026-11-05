<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\PortSetting;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Support\MinAppVersionSource;

/**
 * Reads `min_app_version` from `port_settings`. A missing row means the port is not provisioned yet: 503 (P1-C5, Q-C5-1).
 */
class PortSettingMinAppVersion implements MinAppVersionSource
{
    public const string SETTING_KEY = 'min_app_version';

    public function minAppVersion(): string
    {
        $value = PortSetting::query()->where('setting_key', self::SETTING_KEY)->value('setting_value');

        if ($value === null) {
            throw new ApiProblemException(ErrorCode::ServiceUnavailable);
        }

        return $value;
    }
}
