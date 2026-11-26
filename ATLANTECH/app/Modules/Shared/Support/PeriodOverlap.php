<?php

namespace App\Modules\Shared\Support;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * The one definition of "two periods overlap" for the no-overlap rules (SHARED-01). Pure functions, no database access: each caller
 * writes its own query with the same comparison, given below in SQL.
 */
final class PeriodOverlap
{
    private const SECONDS_PER_DAY = 86400;

    /**
     * Datetime periods with an exclusive end, `[start, end)`; a null end never ends (`safety_constraints`, P2-F5).
     * Periods that touch (`aEnd == bStart`) do not overlap.
     *
     * SQL: `active_from < :end AND (active_to IS NULL OR active_to > :start)`, with `:end` left out when the new period is open.
     */
    public static function instants(CarbonInterface $aStart, ?CarbonInterface $aEnd, CarbonInterface $bStart, ?CarbonInterface $bEnd): bool
    {
        return ($bEnd === null || $aStart->lt($bEnd))
            && ($aEnd === null || $bStart->lt($aEnd));
    }

    /**
     * Calendar-day periods with an inclusive end, `[from, to]`; a null end never ends; the time part is ignored
     * (`terminal_productivity_profiles`, P2-F6). The same day at the edge (`aTo == bFrom`) is an overlap.
     *
     * SQL: `valid_from <= :to AND (valid_to IS NULL OR valid_to >= :from)`, with `:to` left out when the new period is open.
     */
    public static function dates(CarbonInterface $aFrom, ?CarbonInterface $aTo, CarbonInterface $bFrom, ?CarbonInterface $bTo): bool
    {
        return ($bTo === null || $aFrom->toDateString() <= $bTo->toDateString())
            && ($aTo === null || $bFrom->toDateString() <= $aTo->toDateString());
    }

    /**
     * Daily shifts given as `HH:MM` or `HH:MM:SS` in port local time; an end before the start means the shift ends the next day
     * (`port_shifts`, P2-F3). Shifts that touch (`06:00-14:00` and `14:00-22:00`) do not overlap.
     *
     * Compare in PHP: load the active shifts and call this for each one.
     *
     * @throws InvalidArgumentException For a time that is not `HH:MM[:SS]`, or a shift whose start equals its end (`chk_port_shift_times`).
     */
    public static function timesOfDay(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        foreach (self::rangesInDay($aStart, $aEnd) as [$aFrom, $aTo]) {
            foreach (self::rangesInDay($bStart, $bEnd) as [$bFrom, $bTo]) {
                if ($aFrom < $bTo && $bFrom < $aTo) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A shift as one or two half-open ranges of seconds within one day; a shift that crosses midnight is split at midnight.
     *
     * @return list<array{0: int, 1: int}>
     */
    private static function rangesInDay(string $start, string $end): array
    {
        $from = self::secondsOfDay($start);
        $to = self::secondsOfDay($end);

        if ($from === $to) {
            throw new InvalidArgumentException("A shift cannot start and end at the same time ({$start}).");
        }

        if ($from < $to) {
            return [[$from, $to]];
        }

        return [[$from, self::SECONDS_PER_DAY], [0, $to]];
    }

    private static function secondsOfDay(string $time): int
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $time, $parts) !== 1) {
            throw new InvalidArgumentException("Not a time of day: {$time}.");
        }

        return (int) $parts[1] * 3600 + (int) $parts[2] * 60 + (int) ($parts[3] ?? 0);
    }
}
