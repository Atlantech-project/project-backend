<?php

use App\Modules\Access\Enums\Role;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * P2-D4 version token on a test route over Tugboat (`updated_at DATETIME(6)`): 409 with `current` for a stale token,
 * 422 for a missing or malformed one, a new token after a save, and the locked re-check. Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);

    Route::middleware(['api', 'port.api', 'ability:tug-fleet.manage', 'version.token:tugboat'])
        ->prefix('api/v1')
        ->patch('test/tugboats/{tugboat}', function (Request $request, Tugboat $tugboat) {
            $locked = DB::transaction(function () use ($request, $tugboat) {
                $locked = VersionToken::lockAndAssert($tugboat, $request->input('version'));
                $locked->update(['name' => $request->input('name')]);

                return $locked;
            });

            return ['id' => $locked->id, 'version' => VersionToken::of($locked->refresh())];
        });

    Route::middleware(['api', 'port.api', 'ability:tug-fleet.manage', 'version.token:tugboat'])
        ->prefix('api/v1')
        ->get('test/tugboats/{tugboat}', fn (Tugboat $tugboat) => ['id' => $tugboat->id, 'version' => VersionToken::of($tugboat)]);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('accepts the current version, saves, and returns a new version', function () {
    $tugboat = Tugboat::factory()->create();
    $before = VersionToken::of($tugboat->refresh());

    usleep(2000);

    $response = $this->actingAs(makeUserWithRole(Role::TugManager), 'web')
        ->patchJson("/api/v1/test/tugboats/{$tugboat->id}", ['version' => $before, 'name' => 'Renamed'])
        ->assertOk();

    expect($response->json('version'))->not->toBe($before)
        ->and($tugboat->refresh()->name)->toBe('Renamed');
});

it('refuses a stale version with 409 and the stored id and version, and does not save', function () {
    $tugboat = Tugboat::factory()->create();
    $first = VersionToken::of($tugboat->refresh());
    $user = makeUserWithRole(Role::TugManager);

    usleep(2000);
    $this->actingAs($user, 'web')
        ->patchJson("/api/v1/test/tugboats/{$tugboat->id}", ['version' => $first, 'name' => 'First'])
        ->assertOk();
    $current = VersionToken::of($tugboat->refresh());

    $this->actingAs($user, 'web')
        ->patchJson("/api/v1/test/tugboats/{$tugboat->id}", ['version' => $first, 'name' => 'Second'])
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'VERSION_CONFLICT')
        ->assertJsonPath('current.id', $tugboat->id)
        ->assertJsonPath('current.version', $current);

    expect($tugboat->refresh()->name)->toBe('First');
});

it('answers 422 with errors.version when the version is missing or malformed', function (mixed $version, string $key) {
    $tugboat = Tugboat::factory()->create();

    $this->actingAs(makeUserWithRole(Role::TugManager), 'web')
        ->patchJson("/api/v1/test/tugboats/{$tugboat->id}", ['version' => $version, 'name' => 'X'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonPath('errors.version.0', $key);
})->with([
    'missing' => [null, 'validation.required'],
    'not the token shape' => ['2026-11-02', 'validation.regex'],
]);

it('lets a read through without a version', function () {
    $tugboat = Tugboat::factory()->create();

    $this->actingAs(makeUserWithRole(Role::TugManager), 'web')
        ->getJson("/api/v1/test/tugboats/{$tugboat->id}")
        ->assertOk();
});

it('refuses an unauthorised caller with 403 before it looks at the version', function () {
    $tugboat = Tugboat::factory()->create();

    $this->actingAs(makeUserWithRole(Role::PortStaff), 'web')
        ->patchJson("/api/v1/test/tugboats/{$tugboat->id}", ['name' => 'X'])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('re-checks the version on the locked row and issues FOR UPDATE', function () {
    $tugboat = Tugboat::factory()->create();
    $stale = Tugboat::query()->findOrFail($tugboat->id);
    $staleToken = VersionToken::of($stale);

    usleep(2000);
    $tugboat->update(['name' => 'Changed elsewhere']);

    DB::enableQueryLog();

    expect(fn () => DB::transaction(fn () => VersionToken::lockAndAssert($stale, $staleToken)))
        ->toThrow(ApiProblemException::class);

    expect(collect(DB::getQueryLog())->pluck('query')->implode(' '))->toContain('for update');
});

it('fails loudly when version.token names a route parameter that is not a bound model', function () {
    Route::middleware(['api', 'port.api', 'ability:tug-fleet.manage', 'version.token:tug'])
        ->prefix('api/v1')
        ->patch('test/typo/{tugboat}', fn () => ['ok' => true]);
    $tugboat = Tugboat::factory()->create();

    $this->withoutExceptionHandling()
        ->actingAs(makeUserWithRole(Role::TugManager), 'web')
        ->patchJson("/api/v1/test/typo/{$tugboat->id}", ['version' => VersionToken::of($tugboat->refresh())]);
})->throws(LogicException::class, 'version.token:tug');
