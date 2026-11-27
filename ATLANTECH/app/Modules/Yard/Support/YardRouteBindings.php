<?php

namespace App\Modules\Yard\Support;

use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\Shared\Enums\StorageDetailKind;
use App\Modules\Yard\Models\YardBlock;
use App\Modules\Yard\Models\YardSlot;
use App\Modules\Yard\Models\YardZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as CurrentRoute;
use Illuminate\Support\Facades\Route;

/**
 * Binds {containerYard}, {yardZone}, {yardBlock} and {yardSlot} only inside the {terminal} of the URL, so a record of
 * another terminal is 404 (P2-F4). scopeBindings() cannot be used: Terminal belongs to MasterData, which may not know the
 * Yard models (ModuleArchitectureTest). Bindings run after terminal.scope and before version.token.
 */
class YardRouteBindings
{
    public static function register(): void
    {
        Route::bind('containerYard', fn (string $value, CurrentRoute $route): Model => self::find(
            StorageFacility::query()->where('detail_kind', StorageDetailKind::ContainerYard), '', $value, $route,
        ));
        Route::bind('yardZone', fn (string $value, CurrentRoute $route): Model => self::find(
            YardZone::query(), 'containerYard', $value, $route,
        ));
        Route::bind('yardBlock', fn (string $value, CurrentRoute $route): Model => self::find(
            YardBlock::query(), 'yardZone.containerYard', $value, $route,
        ));
        Route::bind('yardSlot', fn (string $value, CurrentRoute $route): Model => self::find(
            YardSlot::query(), 'yardBlock.yardZone.containerYard', $value, $route,
        ));
    }

    /**
     * @param  string  $pathToYard  The relation from the record to its container yard; empty for the yard itself.
     */
    private static function find(Builder $query, string $pathToYard, string $value, CurrentRoute $route): Model
    {
        $terminal = $route->parameter('terminal');
        $terminalId = $terminal instanceof Model ? (string) $terminal->getKey() : (string) $terminal;

        abort_unless(ctype_digit($value) && ctype_digit($terminalId), 404);

        if ($pathToYard === '') {
            $query->where('terminal_id', $terminalId);
        } else {
            $query->whereHas($pathToYard, fn (Builder $yard) => $yard->where('terminal_id', $terminalId));
        }

        return $query->findOrFail($value);
    }
}
