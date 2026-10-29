<?php

use App\Modules\MasterData\Models\Tugboat;
use App\Modules\Shared\Http\Validation\ZonedDateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * Times are UTC end to end (CT-01; P1-A2 questions 5 and 8). The round trip goes through a DATETIME(6) column
 * (tugboats.updated_at) on a test-only route; every test is rolled back.
 */

beforeEach(function () {
    Route::middleware('api')->post('api/v1/_datetime/tugboats/{tugboat}', function (Request $request, Tugboat $tugboat) {
        $validated = $request->validate(['at' => ['required', ...ZonedDateTime::rules()]]);

        $tugboat->timestamps = false;
        $tugboat->updated_at = ZonedDateTime::toUtc($validated['at']);
        $tugboat->save();

        return ['at' => $tugboat->fresh()->updated_at];
    });
});

it('runs the application in UTC', function () {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(date_default_timezone_get())->toBe('UTC')
        ->and(now()->getTimezone()->getName())->toBe('UTC');
});

it('runs the MySQL session in UTC', function () {
    useSchemaDatabase();

    expect(DB::selectOne('SELECT @@session.time_zone AS tz')->tz)->toBe('+00:00');

    DB::disconnect();
})->group('infra');

describe('round trip through the API', function () {
    beforeEach(function () {
        useSchemaDatabase();
        DB::beginTransaction();
        $this->tugboat = Tugboat::factory()->create();
    });

    afterEach(function () {
        DB::rollBack();
        DB::disconnect();
    });

    it('stores an offset time as UTC and returns it with microseconds and Z', function (string $input, string $output) {
        $this->postJson("/api/v1/_datetime/tugboats/{$this->tugboat->id}", ['at' => $input])
            ->assertOk()
            ->assertExactJson(['at' => $output]);

        expect(DB::table('tugboats')->where('id', $this->tugboat->id)->value('updated_at'))
            ->toBe(str_replace(['T', 'Z'], [' ', ''], $output));
    })->with([
        'offset +02:00' => ['2026-11-02T12:15:04+02:00', '2026-11-02T10:15:04.000000Z'],
        'negative offset' => ['2026-11-02T05:15:04-05:00', '2026-11-02T10:15:04.000000Z'],
        'Z with microseconds' => ['2026-11-02T10:15:04.123456Z', '2026-11-02T10:15:04.123456Z'],
        'offset across midnight' => ['2026-11-02T01:00:00.5+03:00', '2026-11-01T22:00:00.500000Z'],
    ]);

    it('refuses a datetime without a timezone or in another shape with 422', function (mixed $input, string $key) {
        $this->postJson("/api/v1/_datetime/tugboats/{$this->tugboat->id}", ['at' => $input])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('errors.at.0', $key);
    })->with([
        'naive' => ['2026-11-02T10:15:04', 'validation.regex'],
        'naive with a space' => ['2026-11-02 10:15:04', 'validation.regex'],
        'date only' => ['2026-11-02', 'validation.regex'],
        'offset without colon' => ['2026-11-02T10:15:04+0200', 'validation.regex'],
        'more than microseconds' => ['2026-11-02T10:15:04.1234567Z', 'validation.regex'],
        'not a calendar date' => ['2026-02-30T10:15:04Z', 'validation.date'],
        'not a time' => ['2026-11-02T25:15:04Z', 'validation.date'],
        'a number' => [1730542504, 'validation.string'],
    ]);
});
