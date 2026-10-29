<?php

namespace App\Modules\Shared\Http\Validation;

use Carbon\CarbonImmutable;

/**
 * A datetime sent by a client: ISO 8601 with `Z` or an explicit offset, converted to UTC (CT-01; P1-A2 question 8).
 *
 * A value without a timezone is refused with 422, since a naive time is ambiguous for a port in its own timezone.
 * Shift times (local `TIME` columns) are not datetimes and do not use this. Output needs nothing: Laravel already
 * serialises dates as `2026-10-28T10:15:04.123456Z` (question 5).
 */
final class ZonedDateTime
{
    /**
     * `YYYY-MM-DDTHH:MM:SS`, optional fraction up to microseconds, then `Z` or `±HH:MM`.
     */
    public const string PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/';

    /**
     * The rules for one datetime field: the shape first (`validation.regex` for a naive or malformed value),
     * then a real calendar date and time (`validation.date`).
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['string', 'regex:'.self::PATTERN, 'date'];
    }

    /**
     * A validated value as a UTC instant.
     */
    public static function toUtc(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value)->utc();
    }
}
