<?php

namespace App\Modules\AiBridge;

use App\Modules\AiBridge\Console\CreateWorkerAccounts;
use Illuminate\Support\ServiceProvider;

/**
 * AI bridge: job publisher (JSON envelope v1 on an unprefixed Redis connection), run rows, completion consumer and mock workers (P3).
 */
class AiBridgeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CreateWorkerAccounts::class]);
        }
    }
}
