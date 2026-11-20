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
use App\Modules\Access\Http\Middleware\EnsureMobileHeaders;
use App\Modules\MasterData\Http\Controllers\SystemInfoController;
use App\Modules\Shared\Http\Controllers\AuditLogController;
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
    });
});
