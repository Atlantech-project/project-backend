<?php

namespace App\Modules\Shared\Support;

/**
 * The mobile app version rule (P1-C3): `x.y.z` only (developer, 2026-11-05), compared as versions, so 1.10.0 is newer than 1.9.0.
 * Used by the sign-in headers and the 426 check; `user_devices.app_version` holds at most 20 characters.
 */
final class AppVersion
{
    public const string PATTERN = '/^\d+\.\d+\.\d+$/';

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['required', 'string', 'max:20', 'regex:'.self::PATTERN];
    }

    public static function isOlderThan(string $version, string $minimum): bool
    {
        return version_compare($version, $minimum, '<');
    }
}
