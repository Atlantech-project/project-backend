<?php

use App\Modules\Access\AccessServiceProvider;
use App\Modules\AiBridge\AiBridgeServiceProvider;
use App\Modules\Baplie\BaplieServiceProvider;
use App\Modules\Berthing\BerthingServiceProvider;
use App\Modules\Dispatch\DispatchServiceProvider;
use App\Modules\Environment\EnvironmentServiceProvider;
use App\Modules\Execution\ExecutionServiceProvider;
use App\Modules\Learning\LearningServiceProvider;
use App\Modules\License\LicenseServiceProvider;
use App\Modules\MasterData\MasterDataServiceProvider;
use App\Modules\Plan\PlanServiceProvider;
use App\Modules\Realtime\RealtimeServiceProvider;
use App\Modules\Recommendation\RecommendationServiceProvider;
use App\Modules\Shared\SharedServiceProvider;
use App\Modules\Yard\YardServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    AccessServiceProvider::class,
    LicenseServiceProvider::class,
    MasterDataServiceProvider::class,
    EnvironmentServiceProvider::class,
    BerthingServiceProvider::class,
    BaplieServiceProvider::class,
    AiBridgeServiceProvider::class,
    RecommendationServiceProvider::class,
    PlanServiceProvider::class,
    YardServiceProvider::class,
    DispatchServiceProvider::class,
    ExecutionServiceProvider::class,
    LearningServiceProvider::class,
    RealtimeServiceProvider::class,
];
