<?php

use App\Modules\MasterData\Rules\GeoJsonPolygon;

/**
 * P2-F2: `terminals.boundary_coordinates` must be a GeoJSON Polygon of closed `[lon, lat]` rings (Q7: no port-boundary check).
 */
function polygonFails(mixed $value): bool
{
    $failed = false;
    (new GeoJsonPolygon)->validate('boundary_coordinates', $value, function () use (&$failed): void {
        $failed = true;
    });

    return $failed;
}

$square = [[31.80, 31.40], [31.81, 31.40], [31.81, 31.41], [31.80, 31.41], [31.80, 31.40]];

it('accepts a closed polygon ring of [lon, lat] positions', function () use ($square) {
    expect(polygonFails(['type' => 'Polygon', 'coordinates' => [$square]]))->toBeFalse();
});

it('accepts integer coordinates and a polygon with a hole', function () use ($square) {
    $hole = [[31, 31], [32, 31], [32, 32], [31, 31]];

    expect(polygonFails(['type' => 'Polygon', 'coordinates' => [$square, $hole]]))->toBeFalse();
});

it('refuses a value that is not a GeoJSON Polygon', function (mixed $value) {
    expect(polygonFails($value))->toBeTrue();
})->with([
    'a string' => ['POLYGON((31 31, 32 31, 32 32, 31 31))'],
    'another geometry type' => [['type' => 'Point', 'coordinates' => [31.8, 31.4]]],
    'no coordinates' => [['type' => 'Polygon']],
    'empty coordinates' => [['type' => 'Polygon', 'coordinates' => []]],
]);

it('refuses a ring that is not closed, too short or has bad positions', function (array $ring) {
    expect(polygonFails(['type' => 'Polygon', 'coordinates' => [$ring]]))->toBeTrue();
})->with([
    'open ring' => [[[31, 31], [32, 31], [32, 32], [31, 32]]],
    'three positions' => [[[31, 31], [32, 31], [31, 31]]],
    'longitude out of range' => [[[181, 31], [32, 31], [32, 32], [181, 31]]],
    'latitude out of range' => [[[31, 91], [32, 31], [32, 32], [31, 91]]],
    'position with altitude' => [[[31, 31, 5], [32, 31, 5], [32, 32, 5], [31, 31, 5]]],
    'string numbers' => [[['31', '31'], ['32', '31'], ['32', '32'], ['31', '31']]],
]);
