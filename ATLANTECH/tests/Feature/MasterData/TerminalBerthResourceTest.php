<?php

use App\Modules\MasterData\Http\Resources\BerthResource;
use App\Modules\MasterData\Http\Resources\TerminalResource;
use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

/**
 * P2-F2 resources: the shape the frontend reads (P-27, P-28), with `version` for the 409 check.
 */
it('renders a berth with numbers, its version and its terminals', function () {
    $terminal = Terminal::factory()->create(['is_active' => false]);
    $berth = Berth::factory()->create(['length' => 300, 'max_vessel_loa' => 280.5, 'latitude' => null]);
    $berth->terminals()->attach($terminal->id);

    $data = (new BerthResource($berth->fresh()->load('terminals')))->toArray(Request::create('/'));

    expect($data['length'])->toBe(300.0)
        ->and($data['max_vessel_loa'])->toBe(280.5)
        ->and($data['latitude'])->toBeNull()
        ->and($data['version'])->toBe(VersionToken::of($berth->fresh()))
        ->and($data['terminals']->all())->toBe([[
            'id' => $terminal->id,
            'code' => $terminal->code,
            'name' => $terminal->name,
            'terminal_type' => $terminal->terminal_type,
            'is_active' => false,
        ]]);
});

it('renders a terminal with its berths only when they are loaded', function () {
    $terminal = Terminal::factory()->create();
    $berth = Berth::factory()->create();
    $terminal->berths()->attach($berth->id);

    $loaded = (new TerminalResource($terminal->fresh()->load('berths')))->resolve(Request::create('/'));
    $bare = (new TerminalResource($terminal->fresh()))->resolve(Request::create('/'));

    expect($loaded['berths'][0]['id'])->toBe($berth->id)
        ->and($loaded['version'])->toBe(VersionToken::of($terminal->fresh()))
        ->and($bare)->not->toHaveKey('berths');
});
