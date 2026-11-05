<?php

namespace App\Modules\MasterData;

use App\Modules\MasterData\Services\PortSettingMinAppVersion;
use App\Modules\Shared\Support\MinAppVersionSource;
use Illuminate\Support\ServiceProvider;

/**
 * Master data: port, channel, terminals, berths, tugs, pilots, shifts, safety limits and tide sets (P2).
 */
class MasterDataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MinAppVersionSource::class, PortSettingMinAppVersion::class);
    }
}
