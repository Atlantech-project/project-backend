<?php

use App\Modules\AiBridge\Console\CreateWorkerAccounts;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * database/grants/workers.sql gives each worker exactly its DEC-08 rights.
 * The file is loaded under test-only role names (t_r_*) and checked by signing in as a
 * test account, so the real r_* roles and w_* accounts on the server are not touched.
 */

beforeEach(function () {
    useSchemaDatabase();
});

/**
 * Load workers.sql as t_r_* roles, create a test account holding $role, and run $check on its connection.
 *
 * @param  Closure(Connection): void  $check
 */
function asTestWorker(string $role, Closure $check): void
{
    $roles = array_map(fn (string $name): string => 't_'.$name, array_values(CreateWorkerAccounts::ACCOUNTS));
    $password = Str::random(24);

    try {
        DB::unprepared(str_replace("'r_", "'t_r_", file_get_contents(database_path('grants/workers.sql'))));
        DB::statement("CREATE USER 't_worker'@'%' IDENTIFIED BY '{$password}'");
        DB::statement("GRANT 't_{$role}' TO 't_worker'@'%'");
        DB::statement("SET DEFAULT ROLE 't_{$role}' TO 't_worker'@'%'");

        config(['database.connections.worker_test' => array_merge(
            config('database.connections.mysql'),
            ['username' => 't_worker', 'password' => $password],
        )]);

        $check(DB::connection('worker_test'));
    } finally {
        DB::purge('worker_test');
        DB::statement("DROP USER IF EXISTS 't_worker'@'%'");
        DB::statement('DROP ROLE IF EXISTS '.implode(', ', array_map(fn (string $name): string => "'{$name}'", $roles)));
    }
}

test('a worker reads port state but not secrets, audit or devices', function () {
    asTestWorker('r_yard', function ($worker) {
        expect($worker->table('berths')->count())->toBe(0)
            ->and($worker->table('users')->select('id', 'name', 'is_active')->count())->toBe(0)
            ->and(fn () => $worker->table('users')->value('password_hash'))->toThrow(QueryException::class, 'denied')
            ->and(fn () => $worker->table('audit_logs')->count())->toThrow(QueryException::class, 'denied')
            ->and(fn () => $worker->table('user_devices')->count())->toThrow(QueryException::class, 'denied');
    });
})->group('schema');

test('the yard worker writes only its own results', function () {
    asTestWorker('r_yard', function ($worker) {
        expect($worker->table('yard_runs')->where('id', 0)->update(['status' => 'RUNNING']))->toBe(0)
            ->and(fn () => $worker->table('yard_runs')->where('id', 0)->update(['triggered_by' => null]))
            ->toThrow(QueryException::class, 'denied')
            ->and(fn () => $worker->table('berths')->insert(['code' => 'X']))->toThrow(QueryException::class, 'denied')
            ->and(fn () => $worker->table('yard_placement_items')->where('id', 0)->delete())
            ->toThrow(QueryException::class, 'denied')
            ->and(fn () => $worker->statement('CREATE TABLE t_probe (id int)'))->toThrow(QueryException::class, 'denied');
    });
})->group('schema');

test('the BAPLIE worker counts its own retries (CT-15)', function () {
    asTestWorker('r_edi', function ($worker) {
        expect($worker->table('baplie_parse_runs')->where('id', 0)->update(['retry_count' => 1]))->toBe(0)
            ->and(fn () => $worker->table('baplie_parse_runs')->where('id', 0)->update(['triggered_by' => null]))
            ->toThrow(QueryException::class, 'denied');
    });
})->group('schema');

test('the yard worker counts its own retries (CT-08, P1-B3)', function () {
    asTestWorker('r_yard', function ($worker) {
        expect($worker->table('yard_runs')->where('id', 0)->update(['retry_count' => 1]))->toBe(0);
    });
})->group('schema');

test('a worker with column grants still updates a table that carries the version token', function () {
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    $vesselId = DB::table('vessels')->insertGetId([
        'imo_number' => '9999999', 'name' => 'Token Probe', 'vessel_type_id' => 0,
        'loa' => 100, 'beam' => 20, 'max_draft' => 8,
        'updated_at' => '2000-01-01 00:00:00.000000',
    ]);
    DB::statement('SET FOREIGN_KEY_CHECKS = 1');

    try {
        asTestWorker('r_edi', function ($worker) use ($vesselId) {
            expect($worker->table('vessels')->where('id', $vesselId)->update(['call_sign' => 'SUXX']))->toBe(1);
        });

        expect(DB::table('vessels')->where('id', $vesselId)->value('updated_at'))
            ->not->toBe('2000-01-01 00:00:00.000000');
    } finally {
        DB::table('vessels')->where('id', $vesselId)->delete();
    }
})->group('schema');

test('the validation worker has no write rights at all', function () {
    asTestWorker('r_validate', function ($worker) {
        expect(fn () => $worker->table('part1_runs')->where('id', 0)->update(['status' => 'RUNNING']))
            ->toThrow(QueryException::class, 'denied');
    });
})->group('schema');

test('the worker-accounts command refuses to run without every password', function () {
    config(['services.ai_workers.db_passwords.w_yard' => null]);

    $this->artisan('atlantech:worker-accounts')
        ->expectsOutputToContain('WORKER_YARD_PASSWORD')
        ->assertFailed();
})->group('schema');
