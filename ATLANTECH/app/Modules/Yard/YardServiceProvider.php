<?php

namespace App\Modules\Yard;

use App\Modules\Yard\Support\YardRouteBindings;
use Illuminate\Support\ServiceProvider;

/**
 * Yard: zones, blocks, slots, yard recommendation and the TOS hand-off (P6).
 */
class YardServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        YardRouteBindings::register();
    }
}
