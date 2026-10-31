<?php

namespace App\Modules\Shared;

use App\Modules\Shared\Console\PublishOpenApi;
use App\Modules\Shared\Console\PublishStatusVocabulary;
use Illuminate\Support\ServiceProvider;

/**
 * Shared kernel used by every module: audit in the same transaction, the SM-4 transition service, the ResourceChanged event, status enums, terminal-scope filters and the version token. Depends on no other module.
 */
class SharedServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PublishOpenApi::class, PublishStatusVocabulary::class]);
        }
    }
}
