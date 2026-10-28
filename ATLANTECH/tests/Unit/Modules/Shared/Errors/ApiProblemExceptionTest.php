<?php

use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

it('takes its HTTP status from the catalogue code', function (ErrorCode $code) {
    $exception = new ApiProblemException($code);

    expect($exception)->toBeInstanceOf(HttpExceptionInterface::class)
        ->and($exception->getStatusCode())->toBe($code->status())
        ->and($exception->errorCode)->toBe($code)
        ->and($exception->detail)->toBeNull()
        ->and($exception->extensions)->toBe([]);
})->with([
    ErrorCode::AccountDisabled,
    ErrorCode::LicenseReadOnly,
    ErrorCode::PlanLimitExceeded,
    ErrorCode::PasswordChangeRequired,
]);

it('keeps a detail override, translation replacements and response headers', function () {
    $exception = new ApiProblemException(
        ErrorCode::TooManyAttempts,
        detail: 'Try again in 60 seconds.',
        replace: ['seconds' => 60],
        headers: ['Retry-After' => '60'],
    );

    expect($exception->detail)->toBe('Try again in 60 seconds.')
        ->and($exception->getMessage())->toBe('Try again in 60 seconds.')
        ->and($exception->replace)->toBe(['seconds' => 60])
        ->and($exception->getHeaders())->toBe(['Retry-After' => '60']);
});

it('builds a 409 VERSION_CONFLICT with the current record as the "current" member', function () {
    $current = ['id' => 12, 'version' => '2026-11-02T10:15:04.123456Z'];

    $exception = ApiProblemException::versionConflict($current);

    expect($exception->errorCode)->toBe(ErrorCode::VersionConflict)
        ->and($exception->getStatusCode())->toBe(409)
        ->and($exception->extensions)->toBe(['current' => $current]);
});

it('builds a 426 APP_UPDATE_REQUIRED with the "min_app_version" member', function () {
    $exception = ApiProblemException::appUpdateRequired('1.1.0');

    expect($exception->errorCode)->toBe(ErrorCode::AppUpdateRequired)
        ->and($exception->getStatusCode())->toBe(426)
        ->and($exception->extensions)->toBe(['min_app_version' => '1.1.0']);
});

it('rejects an extension member that would replace a problem member', function (string $member) {
    new ApiProblemException(ErrorCode::Forbidden, extensions: [$member => 'x']);
})->with(ApiProblemException::RESERVED_MEMBERS)->throws(InvalidArgumentException::class);
