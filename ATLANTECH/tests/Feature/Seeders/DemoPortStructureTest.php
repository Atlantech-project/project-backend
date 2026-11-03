<?php

use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\ChannelWaypoint;
use App\Modules\MasterData\Models\NavigationChannel;
use App\Modules\MasterData\Models\PortShift;
use App\Modules\MasterData\Models\Terminal;
use Database\Seeders\DemoPortSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('builds a port of 8 terminals and 36 berths', function () {
    $this->seed(DemoPortSeeder::class);

    expect(Terminal::query()->count())->toBe(8)
        ->and(Terminal::query()->where('terminal_type', 'CONTAINER')->count())->toBe(3)
        ->and(Berth::query()->count())->toBe(36);
});

it('gives every berth at least one terminal and shares some', function () {
    $this->seed(DemoPortSeeder::class);

    $perBerth = DB::table('berth_terminals')->select('berth_id', DB::raw('count(*) as terminals'))->groupBy('berth_id')->pluck('terminals', 'berth_id');

    expect($perBerth)->toHaveCount(36)
        ->and($perBerth->filter(fn (int $count) => $count > 1)->count())->toBeGreaterThanOrEqual(1);
});

it('creates one organization unit per terminal plus the two port-wide units', function () {
    $this->seed(DemoPortSeeder::class);

    expect(OrganizationUnit::query()->where('unit_type', 'TERMINAL')->count())->toBe(8)
        ->and(OrganizationUnit::query()->where('unit_type', 'HARBOUR_AUTHORITY')->count())->toBe(1)
        ->and(OrganizationUnit::query()->where('unit_type', 'MARINE_SERVICES')->count())->toBe(1);
});

it('uses the channel datum for every berth and gives the channel its waypoints', function () {
    $this->seed(DemoPortSeeder::class);

    $channel = NavigationChannel::query()->sole();

    expect(Berth::query()->where('depth_datum', '!=', $channel->depth_datum)->count())->toBe(0)
        ->and(ChannelWaypoint::query()->where('channel_id', $channel->id)->count())->toBe(4);
});

it('defines three active shifts that do not overlap', function () {
    $this->seed(DemoPortSeeder::class);

    $minutes = PortShift::query()->get()->flatMap(function (PortShift $shift) {
        $start = (int) substr($shift->start_time, 0, 2) * 60;
        $end = (int) substr($shift->end_time, 0, 2) * 60 ?: 1440;

        return range($start, $end - 1);
    });

    expect(PortShift::query()->count())->toBe(3)
        ->and($minutes->count())->toBe($minutes->unique()->count())
        ->and($minutes->unique()->count())->toBe(1440);
});

it('changes nothing when it runs twice', function () {
    $this->seed(DemoPortSeeder::class);
    $this->seed(DemoPortSeeder::class);

    expect(Terminal::query()->count())->toBe(8)
        ->and(Berth::query()->count())->toBe(36)
        ->and(DB::table('berth_terminals')->count())->toBe(38)
        ->and(OrganizationUnit::query()->count())->toBe(10)
        ->and(ChannelWaypoint::query()->count())->toBe(4);
});
