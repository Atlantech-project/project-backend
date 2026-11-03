<?php

use App\Modules\MasterData\Models\Tugboat;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Support\VersionToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/*
 * P2-D4 version token (FR-GL-05, DEC-57, CT-01 section 5): `updated_at` as an opaque UTC string with microseconds.
 */

function tugboatUpdatedAt(string $at): Tugboat
{
    $tugboat = new Tugboat;
    $tugboat->id = 12;
    $tugboat->updated_at = CarbonImmutable::parse($at);

    return $tugboat;
}

it('formats updated_at as UTC ISO 8601 with microseconds', function () {
    expect(VersionToken::of(tugboatUpdatedAt('2026-11-02 10:15:04.123456')))->toBe('2026-11-02T10:15:04.123456Z')
        ->and(VersionToken::of(tugboatUpdatedAt('2026-11-02 10:15:04')))->toBe('2026-11-02T10:15:04.000000Z')
        ->and(VersionToken::format(CarbonImmutable::parse('2026-11-02T12:15:04.123456+02:00')))->toBe('2026-11-02T10:15:04.123456Z');
});

it('matches only the exact token, microseconds included', function () {
    $tugboat = tugboatUpdatedAt('2026-11-02 10:15:04.123456');

    expect(VersionToken::matches($tugboat, '2026-11-02T10:15:04.123456Z'))->toBeTrue()
        ->and(VersionToken::matches($tugboat, '2026-11-02T10:15:04.123000Z'))->toBeFalse()
        ->and(VersionToken::matches($tugboat, '2026-11-02T10:15:04Z'))->toBeFalse();
});

it('throws a 409 VERSION_CONFLICT carrying the stored id and version for a stale token', function () {
    $tugboat = tugboatUpdatedAt('2026-11-02 10:15:04.123456');

    try {
        VersionToken::assertCurrent($tugboat, '2026-11-02T10:00:00.000000Z');
        $this->fail('A stale token must be refused.');
    } catch (ApiProblemException $exception) {
        expect($exception->errorCode)->toBe(ErrorCode::VersionConflict)
            ->and($exception->getStatusCode())->toBe(409)
            ->and($exception->extensions['current'])->toBe(['id' => 12, 'version' => '2026-11-02T10:15:04.123456Z']);
    }

    VersionToken::assertCurrent($tugboat, '2026-11-02T10:15:04.123456Z');
});

it('validates the shape of the version a client sends back', function (mixed $value, bool $valid) {
    $fails = Validator::make(['version' => $value], ['version' => VersionToken::rules()])->fails();

    expect($fails)->toBe(! $valid);
})->with([
    'exact token' => ['2026-11-02T10:15:04.123456Z', true],
    'missing' => [null, false],
    'no microseconds' => ['2026-11-02T10:15:04Z', false],
    'with offset' => ['2026-11-02T12:15:04.123456+02:00', false],
    'a number' => [5, false],
]);

it('refuses to lock outside a transaction', function () {
    DB::shouldReceive('transactionLevel')->once()->andReturn(0);

    VersionToken::lockAndAssert(tugboatUpdatedAt('2026-11-02 10:15:04.123456'), '2026-11-02T10:15:04.123456Z');
})->throws(LogicException::class);
