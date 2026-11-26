<?php

use App\Modules\Shared\Support\PeriodOverlap;
use Carbon\CarbonImmutable;

/*
 * SHARED-01: the three overlap rules (P2-F3 shifts, P2-F5 safety constraints, P2-F6 productivity profiles). Each case is checked in
 * both orders, since overlap is symmetric.
 */

function overlapAt(?string $value): ?CarbonImmutable
{
    return $value === null ? null : CarbonImmutable::parse($value, 'UTC');
}

it('compares datetime periods with an exclusive end', function (string $aStart, ?string $aEnd, string $bStart, ?string $bEnd, bool $overlaps) {
    expect(PeriodOverlap::instants(overlapAt($aStart), overlapAt($aEnd), overlapAt($bStart), overlapAt($bEnd)))->toBe($overlaps)
        ->and(PeriodOverlap::instants(overlapAt($bStart), overlapAt($bEnd), overlapAt($aStart), overlapAt($aEnd)))->toBe($overlaps);
})->with([
    'partly overlapping' => ['2026-12-01 10:00', '2026-12-01 12:00', '2026-12-01 11:00', '2026-12-01 13:00', true],
    'one inside the other' => ['2026-12-01 08:00', '2026-12-01 18:00', '2026-12-01 10:00', '2026-12-01 11:00', true],
    'the same period' => ['2026-12-01 10:00', '2026-12-01 12:00', '2026-12-01 10:00', '2026-12-01 12:00', true],
    'touching at the end' => ['2026-12-01 10:00', '2026-12-01 12:00', '2026-12-01 12:00', '2026-12-01 14:00', false],
    'apart' => ['2026-12-01 10:00', '2026-12-01 12:00', '2026-12-01 13:00', '2026-12-01 14:00', false],
    'open period started before' => ['2026-12-01 10:00', null, '2026-12-05 10:00', '2026-12-06 10:00', true],
    'open period starting at the end' => ['2026-12-01 10:00', '2026-12-01 12:00', '2026-12-01 12:00', null, false],
    'open period starting after' => ['2026-12-01 10:00', '2026-12-01 12:00', '2026-12-02 10:00', null, false],
    'two open periods' => ['2026-12-01 10:00', null, '2027-01-01 00:00', null, true],
]);

it('compares calendar-day periods with an inclusive end', function (string $aFrom, ?string $aTo, string $bFrom, ?string $bTo, bool $overlaps) {
    expect(PeriodOverlap::dates(overlapAt($aFrom), overlapAt($aTo), overlapAt($bFrom), overlapAt($bTo)))->toBe($overlaps)
        ->and(PeriodOverlap::dates(overlapAt($bFrom), overlapAt($bTo), overlapAt($aFrom), overlapAt($aTo)))->toBe($overlaps);
})->with([
    'the same day at the edge' => ['2026-11-01', '2026-11-30', '2026-11-30', '2026-12-31', true],
    'the next day' => ['2026-11-01', '2026-11-30', '2026-12-01', '2026-12-31', false],
    'one-day periods on the same day' => ['2026-11-15', '2026-11-15', '2026-11-15', '2026-11-15', true],
    'one inside the other' => ['2026-01-01', '2026-12-31', '2026-06-01', '2026-06-30', true],
    'the time part is ignored' => ['2026-11-01 00:00', '2026-11-30 08:00', '2026-11-30 20:00', '2026-12-31 00:00', true],
    'open period started before' => ['2026-01-01', null, '2026-11-01', '2026-11-30', true],
    'open period starting the next day' => ['2026-11-01', '2026-11-30', '2026-12-01', null, false],
    'two open periods' => ['2026-01-01', null, '2027-01-01', null, true],
]);

it('compares daily shifts, a shift that ends before it starts crossing midnight', function (string $aStart, string $aEnd, string $bStart, string $bEnd, bool $overlaps) {
    expect(PeriodOverlap::timesOfDay($aStart, $aEnd, $bStart, $bEnd))->toBe($overlaps)
        ->and(PeriodOverlap::timesOfDay($bStart, $bEnd, $aStart, $aEnd))->toBe($overlaps);
})->with([
    'morning and evening touching' => ['06:00', '14:00', '14:00', '22:00', false],
    'evening and night touching' => ['14:00', '22:00', '22:00', '06:00', false],
    'night and morning touching' => ['22:00', '06:00', '06:00', '14:00', false],
    'one hour shared' => ['06:00', '14:00', '13:00', '22:00', true],
    'night against an early shift' => ['22:00', '06:00', '05:00', '13:00', true],
    'night against a late evening shift' => ['22:00', '06:00', '18:00', '23:00', true],
    'two night shifts' => ['22:00', '06:00', '23:00', '05:00', true],
    'one inside the other' => ['06:00', '18:00', '08:00', '10:00', true],
    'two twelve-hour shifts' => ['07:00', '19:00', '19:00', '07:00', false],
    'ending at midnight' => ['16:00', '00:00', '00:00', '08:00', false],
    'with seconds' => ['06:00:00', '14:00:00', '13:59:59', '22:00:00', true],
]);

it('refuses a shift that starts and ends at the same time', function () {
    PeriodOverlap::timesOfDay('06:00', '06:00', '14:00', '22:00');
})->throws(InvalidArgumentException::class);

it('refuses a time that is not HH:MM or HH:MM:SS', function (string $time) {
    PeriodOverlap::timesOfDay($time, '14:00', '14:00', '22:00');
})->with(['24:00', '6:00', '06:60', '06:00:60', 'morning'])->throws(InvalidArgumentException::class);
