<?php

use App\Modules\MasterData\Models\CargoType;
use App\Modules\Shared\Http\Pagination\PageRequest;
use App\Modules\Shared\Http\Resources\PaginatedCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * A test-only list route over cargo_types; every test is rolled back.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();

    CargoType::query()->delete();
    CargoType::factory()->count(30)->create();

    Route::middleware('api')->get('api/v1/_page/cargo-types', fn (Request $request) => new PaginatedCollection(
        PageRequest::fromRequest($request)->paginate(CargoType::query()->select(['id', 'code'])->orderBy('id')),
    ));
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('returns exactly data and meta with page, per_page and total, default 25', function () {
    $response = $this->getJson('/api/v1/_page/cargo-types')
        ->assertOk()
        ->assertJsonCount(25, 'data')
        ->assertExactJsonStructure(['data' => ['*' => ['id', 'code']], 'meta' => ['page', 'per_page', 'total']]);

    expect($response->json('meta'))->toBe(['page' => 1, 'per_page' => 25, 'total' => 30]);
});

it('returns the requested page', function () {
    $ids = CargoType::query()->orderBy('id')->pluck('id')->all();

    $response = $this->getJson('/api/v1/_page/cargo-types?page=2&per_page=10')->assertOk();

    expect($response->json('meta'))->toBe(['page' => 2, 'per_page' => 10, 'total' => 30])
        ->and(array_column($response->json('data'), 'id'))->toBe(array_slice($ids, 10, 10));
});

it('accepts per_page up to 100', function () {
    $this->getJson('/api/v1/_page/cargo-types?per_page=100')
        ->assertOk()
        ->assertJsonCount(30, 'data')
        ->assertJsonPath('meta.per_page', 100);
});

it('returns 200 with empty data for a page past the end', function (int $page) {
    $this->getJson("/api/v1/_page/cargo-types?page={$page}")
        ->assertOk()
        ->assertExactJson(['data' => [], 'meta' => ['page' => $page, 'per_page' => 25, 'total' => 30]]);
})->with([9, PageRequest::MAX_PAGE]);

it('refuses an invalid page or per_page with 422 VALIDATION_FAILED', function (string $query, string $field, string $key) {
    $this->getJson("/api/v1/_page/cargo-types?{$query}")
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonPath("errors.{$field}", [$key]);
})->with([
    'per_page above the maximum' => ['per_page=101', 'per_page', 'validation.max'],
    'per_page zero' => ['per_page=0', 'per_page', 'validation.min'],
    'per_page not a number' => ['per_page=abc', 'per_page', 'validation.integer'],
    'per_page a fraction' => ['per_page=2.5', 'per_page', 'validation.integer'],
    'page zero' => ['page=0', 'page', 'validation.min'],
    'page negative' => ['page=-1', 'page', 'validation.min'],
    'page not a number' => ['page=abc', 'page', 'validation.integer'],
    'page above the maximum' => ['page=1000001', 'page', 'validation.max'],
    'page that would overflow the offset' => ['page=9223372036854775807', 'page', 'validation.max'],
]);
