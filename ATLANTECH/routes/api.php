<?php

/*
|--------------------------------------------------------------------------
| Port API routes
|--------------------------------------------------------------------------
|
| Served under /api/v1 with the "api" middleware group (bootstrap/app.php).
| Conventions every route follows: CT-01 (contracts/api-conventions.md).
|
*/

use App\Modules\Access\Http\Controllers\AuthController;
use App\Modules\Access\Http\Controllers\MobileAuthController;
use App\Modules\Access\Http\Controllers\UserController;
use App\Modules\Access\Http\Controllers\UserDeviceController;
use App\Modules\Access\Http\Controllers\UserRoleController;
use App\Modules\Access\Http\Middleware\EnsureMobileHeaders;
use App\Modules\MasterData\Http\Controllers\BerthController;
use App\Modules\MasterData\Http\Controllers\EquipmentController;
use App\Modules\MasterData\Http\Controllers\EquipmentReferenceController;
use App\Modules\MasterData\Http\Controllers\SystemInfoController;
use App\Modules\MasterData\Http\Controllers\TerminalController;
use App\Modules\Shared\Http\Controllers\AuditLogController;
use App\Modules\Yard\Http\Controllers\ContainerYardController;
use App\Modules\Yard\Http\Controllers\YardBlockController;
use App\Modules\Yard\Http\Controllers\YardReferenceController;
use App\Modules\Yard\Http\Controllers\YardSlotController;
use App\Modules\Yard\Http\Controllers\YardZoneController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
Route::post('auth/mobile/login', [MobileAuthController::class, 'login'])
    ->middleware([EnsureMobileHeaders::class, 'app.version'])
    ->name('auth.mobile.login');
Route::get('system/info', SystemInfoController::class)->name('system.info');

Route::middleware('port.api')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('auth/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1')->name('auth.change-password');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('ability:audit.read')->name('audit-logs.index');

    Route::middleware('ability:users.manage')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->middleware('version.token:user')->name('users.deactivate');
        Route::post('users/{user}/activate', [UserController::class, 'activate'])->middleware('version.token:user')->name('users.activate');
        Route::post('users/{user}/roles', [UserRoleController::class, 'assign'])->middleware('version.token:user')->name('users.roles.assign');
        Route::post('users/{user}/roles/end', [UserRoleController::class, 'end'])->middleware('version.token:user')->name('users.roles.end');
    });

    Route::middleware('ability:port-state.read')->group(function () {
        Route::get('terminals', [TerminalController::class, 'index'])->name('terminals.index');
        Route::get('terminals/{terminal}', [TerminalController::class, 'show'])->middleware('terminal.scope')->name('terminals.show');
        Route::get('berths', [BerthController::class, 'index'])->name('berths.index');
        Route::get('berths/{berth}', [BerthController::class, 'show'])->name('berths.show');

        Route::get('equipment-categories', [EquipmentReferenceController::class, 'categories'])->name('equipment-categories.index');
        Route::get('capability-types', [EquipmentReferenceController::class, 'capabilityTypes'])->name('capability-types.index');
        Route::get('terminals/{terminal}/equipment', [EquipmentController::class, 'index'])->middleware('terminal.scope')->name('terminals.equipment.index');
        Route::get('terminals/{terminal}/equipment/{equipment}', [EquipmentController::class, 'show'])->middleware('terminal.scope')->scopeBindings()->name('terminals.equipment.show');
    });

    Route::middleware('ability:port-state.read')->group(function () {
        Route::get('yard-zone-types', [YardReferenceController::class, 'zoneTypes'])->name('yard-zone-types.index');
        Route::get('imdg-classes', [YardReferenceController::class, 'imdgClasses'])->name('imdg-classes.index');

        Route::prefix('terminals/{terminal}')->middleware('terminal.scope')->name('terminals.')->group(function () {
            Route::get('yards', [ContainerYardController::class, 'index'])->name('yards.index');
            Route::get('yards/{containerYard}', [ContainerYardController::class, 'show'])->name('yards.show');
            Route::get('yards/{containerYard}/zones', [YardZoneController::class, 'index'])->name('yard-zones.index');
            Route::get('yard-zones/{yardZone}', [YardZoneController::class, 'show'])->name('yard-zones.show');
            Route::get('yard-zones/{yardZone}/blocks', [YardBlockController::class, 'index'])->name('yard-blocks.index');
            Route::get('yard-blocks/{yardBlock}', [YardBlockController::class, 'show'])->name('yard-blocks.show');
            Route::get('yard-blocks/{yardBlock}/slots', [YardSlotController::class, 'index'])->name('yard-slots.index');
        });
    });

    Route::middleware('ability:yard.manage')->prefix('terminals/{terminal}')->name('terminals.')->group(function () {
        Route::middleware('terminal.scope')->group(function () {
            Route::post('yards', [ContainerYardController::class, 'store'])->name('yards.store');
            Route::put('yards/{containerYard}', [ContainerYardController::class, 'update'])->middleware('version.token:containerYard')->name('yards.update');
            Route::post('yards/{containerYard}/zones', [YardZoneController::class, 'store'])->name('yard-zones.store');
            Route::put('yard-zones/{yardZone}', [YardZoneController::class, 'update'])->middleware('version.token:yardZone')->name('yard-zones.update');
            Route::delete('yard-zones/{yardZone}', [YardZoneController::class, 'destroy'])->middleware('version.token:yardZone')->name('yard-zones.destroy');
            Route::post('yard-zones/{yardZone}/blocks', [YardBlockController::class, 'store'])->name('yard-blocks.store');
            Route::put('yard-blocks/{yardBlock}', [YardBlockController::class, 'update'])->middleware('version.token:yardBlock')->name('yard-blocks.update');
            Route::delete('yard-blocks/{yardBlock}', [YardBlockController::class, 'destroy'])->middleware('version.token:yardBlock')->name('yard-blocks.destroy');
            Route::put('yard-blocks/{yardBlock}/layout', [YardBlockController::class, 'layout'])->middleware('version.token:yardBlock')->name('yard-blocks.layout');
        });
    });

    Route::middleware('ability:yard-slot-status.update')->prefix('terminals/{terminal}')->name('terminals.')->group(function () {
        Route::middleware('terminal.scope')->group(function () {
            Route::put('yard-slots/{yardSlot}', [YardSlotController::class, 'update'])->middleware('version.token:yardSlot')->name('yard-slots.update');
            Route::post('yard-blocks/{yardBlock}/stack-status', [YardSlotController::class, 'stack'])->name('yard-blocks.stack-status');
        });
    });

    Route::middleware('ability:equipment.manage')->group(function () {
        Route::post('terminals/{terminal}/equipment', [EquipmentController::class, 'store'])->middleware('terminal.scope')->name('terminals.equipment.store');
        Route::put('terminals/{terminal}/equipment/{equipment}', [EquipmentController::class, 'update'])->middleware(['terminal.scope', 'version.token:equipment'])->scopeBindings()->name('terminals.equipment.update');
    });

    Route::middleware('ability:port-setup.manage')->group(function () {
        Route::post('terminals', [TerminalController::class, 'store'])->name('terminals.store');
        Route::put('terminals/{terminal}', [TerminalController::class, 'update'])->middleware(['terminal.scope', 'version.token:terminal'])->name('terminals.update');
        Route::post('terminals/{terminal}/deactivate', [TerminalController::class, 'deactivate'])->middleware(['terminal.scope', 'version.token:terminal'])->name('terminals.deactivate');
        Route::post('terminals/{terminal}/activate', [TerminalController::class, 'activate'])->middleware(['terminal.scope', 'version.token:terminal'])->name('terminals.activate');
        Route::post('berths', [BerthController::class, 'store'])->name('berths.store');
        Route::put('berths/{berth}', [BerthController::class, 'update'])->middleware('version.token:berth')->name('berths.update');
        Route::post('berths/{berth}/deactivate', [BerthController::class, 'deactivate'])->middleware('version.token:berth')->name('berths.deactivate');
        Route::post('berths/{berth}/activate', [BerthController::class, 'activate'])->middleware('version.token:berth')->name('berths.activate');
        Route::post('berths/{berth}/maintenance', [BerthController::class, 'maintenance'])->middleware('version.token:berth')->name('berths.maintenance');
    });

    Route::middleware('ability:devices.manage')->scopeBindings()->group(function () {
        Route::get('users/{user}/devices', [UserDeviceController::class, 'index'])->name('users.devices.index');
        Route::post('users/{user}/devices/revoke-all', [UserDeviceController::class, 'revokeAll'])->name('users.devices.revoke-all');
        Route::post('users/{user}/devices/{device}/revoke', [UserDeviceController::class, 'revoke'])->name('users.devices.revoke');
        Route::post('users/{user}/devices/{device}/restore', [UserDeviceController::class, 'restore'])->name('users.devices.restore');
    });
});
