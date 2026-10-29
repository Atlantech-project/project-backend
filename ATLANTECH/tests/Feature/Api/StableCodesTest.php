<?php

use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use Illuminate\Support\Facades\Route;

/*
 * The stable codes later tasks throw (P1-C1, P2-D4, P2-D5, P1-C3) render through ApiProblemException.
 */

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1/_stable')->group(function () {
        Route::get('account-disabled', fn () => throw new ApiProblemException(ErrorCode::AccountDisabled));
        Route::get('license-read-only', fn () => throw new ApiProblemException(ErrorCode::LicenseReadOnly));
        Route::get('plan-limit', fn () => throw new ApiProblemException(ErrorCode::PlanLimitExceeded));
        Route::get('version-conflict', fn () => throw ApiProblemException::versionConflict([
            'id' => 7,
            'version' => '2026-11-02T10:15:04.123456Z',
        ]));
        Route::get('app-update', fn () => throw ApiProblemException::appUpdateRequired('2.4.0'));
        Route::get('own-detail', fn () => throw new ApiProblemException(ErrorCode::Forbidden, detail: 'Only the berth planner may do this.'));
        Route::get('throttled', fn () => throw new ApiProblemException(ErrorCode::TooManyAttempts, headers: ['Retry-After' => '60']));
    });
});

it('renders each stable code with its status, key and English detail', function (string $path, ErrorCode $code, int $status) {
    $this->getJson("/api/v1/_stable/{$path}")
        ->assertStatus($status)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertExactJson([
            'type' => $code->type(),
            'title' => $code->title(),
            'status' => $status,
            'code' => $code->value,
            'message_key' => $code->messageKey(),
            'detail' => __($code->messageKey()),
        ]);
})->with([
    'ACCOUNT_DISABLED' => ['account-disabled', ErrorCode::AccountDisabled, 403],
    'LICENSE_READ_ONLY' => ['license-read-only', ErrorCode::LicenseReadOnly, 423],
    'PLAN_LIMIT_EXCEEDED' => ['plan-limit', ErrorCode::PlanLimitExceeded, 422],
]);

it('renders 409 VERSION_CONFLICT with the current record', function () {
    $this->getJson('/api/v1/_stable/version-conflict')
        ->assertStatus(409)
        ->assertJsonPath('code', 'VERSION_CONFLICT')
        ->assertJsonPath('message_key', 'errors.version_conflict')
        ->assertJsonPath('current', ['id' => 7, 'version' => '2026-11-02T10:15:04.123456Z']);
});

it('renders 426 APP_UPDATE_REQUIRED with min_app_version', function () {
    $this->getJson('/api/v1/_stable/app-update')
        ->assertStatus(426)
        ->assertJsonPath('code', 'APP_UPDATE_REQUIRED')
        ->assertJsonPath('min_app_version', '2.4.0');
});

it('uses the thrower\'s detail when one is given', function () {
    $this->getJson('/api/v1/_stable/own-detail')
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN')
        ->assertJsonPath('message_key', 'errors.forbidden')
        ->assertJsonPath('detail', 'Only the berth planner may do this.');
});

it('sends the thrower\'s headers', function () {
    $this->getJson('/api/v1/_stable/throttled')
        ->assertStatus(429)
        ->assertHeader('Retry-After', '60');
});
