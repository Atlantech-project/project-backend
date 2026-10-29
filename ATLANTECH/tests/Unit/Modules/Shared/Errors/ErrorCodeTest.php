<?php

use App\Modules\Shared\Errors\ErrorCode;

/*
 * The catalogue is a contract (CT-01, contracts/error-codes.md): Frontend, Mobile and QA program against
 * each code, status and message key, so a change to any of them must fail here first.
 */
test('the catalogue holds exactly the agreed codes with their HTTP status', function () {
    $statusByCode = collect(ErrorCode::cases())
        ->mapWithKeys(fn (ErrorCode $code) => [$code->value => $code->status()])
        ->all();

    expect($statusByCode)->toBe([
        'VALIDATION_FAILED' => 422,
        'VERSION_CONFLICT' => 409,
        'UNAUTHENTICATED' => 401,
        'FORBIDDEN' => 403,
        'NOT_FOUND' => 404,
        'METHOD_NOT_ALLOWED' => 405,
        'ACCOUNT_DISABLED' => 403,
        'PASSWORD_CHANGE_REQUIRED' => 403,
        'APP_UPDATE_REQUIRED' => 426,
        'LICENSE_READ_ONLY' => 423,
        'PLAN_LIMIT_EXCEEDED' => 422,
        'TOO_MANY_ATTEMPTS' => 429,
        'IDEMPOTENCY_KEY_REUSED' => 422,
        'SERVICE_UNAVAILABLE' => 503,
        'SERVER_ERROR' => 500,
        'BAD_REQUEST' => 400,
    ]);
});

test('every code is UPPER_SNAKE with an error status, an errors.* message key, a type URI and a title', function (ErrorCode $code) {
    expect($code->value)->toMatch('/^[A-Z][A-Z0-9]*(_[A-Z0-9]+)*$/')
        ->and($code->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(600)
        ->and($code->messageKey())->toMatch('/^errors\.[a-z0-9_]+(\.[a-z0-9_]+)*$/')
        ->and($code->type())->toStartWith(ErrorCode::TYPE_BASE_URI)->toMatch('/\/[a-z0-9]+(-[a-z0-9]+)*$/')
        ->and($code->title())->not->toBeEmpty();
})->with(ErrorCode::cases());

test('message keys and type URIs follow the CT-01 and CT-03 examples', function (ErrorCode $code, string $messageKey, string $type) {
    expect($code->messageKey())->toBe($messageKey)
        ->and($code->type())->toBe($type);
})->with([
    'CT-01 409 example' => [ErrorCode::VersionConflict, 'errors.version_conflict', 'https://atlantech.io/errors/version-conflict'],
    'CT-01 422 example' => [ErrorCode::ValidationFailed, 'errors.validation_failed', 'https://atlantech.io/errors/validation-failed'],
    'CT-03 403 example' => [ErrorCode::AccountDisabled, 'errors.account_disabled', 'https://atlantech.io/errors/account-disabled'],
]);

test('no two codes share a message key or a type URI', function () {
    $cases = collect(ErrorCode::cases());

    expect($cases->map->messageKey()->duplicates())->toBeEmpty()
        ->and($cases->map->type()->duplicates())->toBeEmpty();
});
