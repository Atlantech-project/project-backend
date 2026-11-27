<?php

use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Models\TerminalProductivityProfile;
use App\Modules\MasterData\Services\ProductivityProfileService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * P2-F6 lock proof, the same technique as BerthUpdateConcurrencyTest: every profile write locks its terminal row before
 * the overlap check, so two requests cannot both pass it. A second connection holds that lock, so the write must wait (cut
 * to one second, ending in "Lock wait timeout", which proves it waited) and writes nothing. Runs on committed data,
 * because another connection cannot see uncommitted rows; the rows are deleted afterwards.
 */

beforeEach(function () {
    useSchemaDatabase();
    config(['database.connections.mysql_b' => config('database.connections.mysql')]);
    DB::purge('mysql_b');
});

afterEach(function () {
    DB::connection('mysql_b')->rollBack();
    DB::disconnect('mysql_b');
    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');

    if (isset($this->terminal)) {
        DB::table('terminal_productivity_profiles')->where('terminal_id', $this->terminal->id)->delete();
        Terminal::query()->whereKey($this->terminal->id)->delete();
    }

    DB::disconnect();
});

it('waits for the lock on the terminal before it checks and adds a profile', function () {
    $this->terminal = Terminal::factory()->create();

    $other = DB::connection('mysql_b');
    $other->beginTransaction();
    $other->select('select id from terminals where id = ? for update', [$this->terminal->id]);

    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    expect(fn () => app(ProductivityProfileService::class)->create($this->terminal, [
        'productivity_rate' => 20,
        'rate_unit' => 'MOVES_PER_HOUR',
        'fixed_overhead_minutes' => 60,
        'valid_from' => '2027-01-01',
    ], 1))->toThrow(QueryException::class, 'Lock wait timeout');

    expect(TerminalProductivityProfile::query()->where('terminal_id', $this->terminal->id)->count())->toBe(0);
});
