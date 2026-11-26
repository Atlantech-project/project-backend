<?php

namespace App\Modules\MasterData\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A GeoJSON Polygon with `[lon, lat]` positions (terminals.boundary_coordinates, port_profiles.boundary_coordinates):
 * `type` is `Polygon`, every ring has at least four positions and is closed (first position equals the last),
 * longitude is within -180..180 and latitude within -90..90. Whether the polygon lies inside the port boundary
 * is not checked (P2-F2 Q7, known gap).
 */
class GeoJsonPolygon implements ValidationRule
{
    private const int MIN_RING_POSITIONS = 4;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ($value['type'] ?? null) !== 'Polygon' || ! isset($value['coordinates']) || ! is_array($value['coordinates']) || $value['coordinates'] === []) {
            $fail('The :attribute must be a GeoJSON Polygon.');

            return;
        }

        foreach ($value['coordinates'] as $ring) {
            if (! $this->isClosedRing($ring)) {
                $fail('The :attribute must contain closed rings of [longitude, latitude] positions.');

                return;
            }
        }
    }

    private function isClosedRing(mixed $ring): bool
    {
        if (! is_array($ring) || ! array_is_list($ring) || count($ring) < self::MIN_RING_POSITIONS) {
            return false;
        }

        foreach ($ring as $position) {
            if (! $this->isPosition($position)) {
                return false;
            }
        }

        return $ring[0] == $ring[count($ring) - 1];
    }

    private function isPosition(mixed $position): bool
    {
        if (! is_array($position) || ! array_is_list($position) || count($position) !== 2) {
            return false;
        }

        [$longitude, $latitude] = $position;

        if (! is_int($longitude) && ! is_float($longitude)) {
            return false;
        }

        if (! is_int($latitude) && ! is_float($latitude)) {
            return false;
        }

        return $longitude >= -180 && $longitude <= 180 && $latitude >= -90 && $latitude <= 90;
    }
}
