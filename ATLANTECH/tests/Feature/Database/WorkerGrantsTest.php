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

/** Load workers.sql under the test-only role names t_r_*. */
function loadTestWorkerRoles(): void
{
    DB::unprepared(str_replace("'r_", "'t_r_", file_get_contents(database_path('grants/workers.sql'))));
}

function dropTestWorkerRoles(): void
{
    $roles = array_map(fn (string $name): string => "'t_{$name}'", array_values(CreateWorkerAccounts::ACCOUNTS));

    DB::statement('DROP ROLE IF EXISTS '.implode(', ', $roles));
}

/**
 * Every right MySQL holds for a role, from SHOW GRANTS: table => list of "VERB" (whole table) or "VERB(column)".
 * Any line that is not a table grant on the current database (another role, a database-wide or global right) fails.
 *
 * @return array<string, list<string>>
 */
function heldRights(string $role): array
{
    $database = DB::connection()->getDatabaseName();
    $rights = [];

    foreach (DB::select("SHOW GRANTS FOR '{$role}'") as $row) {
        $line = (string) array_values((array) $row)[0];

        if (preg_match('/^GRANT USAGE ON \*\.\* TO `[^`]+`@`%`$/', $line) === 1) {
            continue;
        }

        $matched = preg_match('/^GRANT (.+) ON `'.preg_quote($database, '/').'`\.`([a-z0-9_]+)` TO `[^`]+`@`%`$/', $line, $grant);
        expect($matched)->toBe(1, "unexpected grant: {$line}");

        preg_match_all('/([A-Z][A-Z ]*[A-Z])(?: \(([^)]*)\))?(?:, |$)/', $grant[1], $privileges, PREG_SET_ORDER);

        foreach ($privileges as $privilege) {
            if (! isset($privilege[2])) {
                $rights[$grant[2]][] = $privilege[1];

                continue;
            }

            foreach (explode(', ', $privilege[2]) as $column) {
                $rights[$grant[2]][] = $privilege[1].'('.trim($column, '`').')';
            }
        }
    }

    return sortedRights($rights);
}

/**
 * @param  array<string, list<string>>  $rights
 * @return array<string, list<string>>
 */
function sortedRights(array $rights): array
{
    foreach ($rights as &$held) {
        sort($held);
    }
    ksort($rights);

    return $rights;
}

/** Port tables no worker may read (DEC-08 "Read grants"). */
const UNREADABLE_BY_WORKERS = ['audit_logs', 'notification_logs', 'recommendation_reviews', 'user_devices'];

/** Laravel's own tables: not port tables, so no worker has a right on them. */
const FRAMEWORK_TABLES = [
    'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'migrations',
    'password_reset_tokens', 'personal_access_tokens', 'sessions',
];

/** Tables a worker reads column by column (DEC-08 "Read grants"). */
const COLUMN_READS = ['users' => ['id', 'name', 'is_active'], 'license_states' => ['mode', 'read_only_reason']];

/**
 * The DEC-08 "Final grants" writes. `yard_runs.retry_count` was added by P1-B3 (CT-11 column, approved by both leads).
 *
 * @return array<string, array{insert: list<string>, update: array<string, list<string>>}>
 */
function dec08Writes(): array
{
    return [
        'r_part1' => [
            'insert' => ['entry_window_proposals', 'feasible_windows', 'blocked_periods', 'forecast_runs', 'environment_forecast_points', 'tide_prediction_runs', 'tide_prediction_points', 'demand_forecast_slots'],
            'update' => ['part1_runs' => ['status', 'outcome', 'retry_count', 'entry_window_proposal_id', 'started_at', 'finished_at']],
        ],
        'r_part2' => [
            'insert' => ['solver_run_model_versions', 'recommendations', 'recommendation_options', 'recommendation_option_tugs', 'recommendation_rationale_factors'],
            'update' => ['solver_runs' => ['status', 'objective_value', 'solve_time_ms', 'solver_version', 'result_detail', 'started_at', 'finished_at']],
        ],
        'r_yard' => [
            'insert' => ['yard_placement_items'],
            'update' => [
                'yard_runs' => ['status', 'outcome', 'error_category', 'retry_count', 'result_detail', 'worker_version', 'started_at', 'finished_at'],
                'terminal_recommendations' => ['lifecycle_status', 'confidence', 'rationale_template_id', 'rationale_params', 'failure_reason'],
            ],
        ],
        'r_edi' => [
            'insert' => ['baplie_parse_issues', 'containers', 'container_manifest_entries'],
            'update' => [
                'baplie_parse_runs' => ['status', 'outcome', 'error_category', 'retry_count', 'container_count', 'result_detail', 'worker_version', 'started_at', 'finished_at'],
                'baplie_files' => ['parse_status', 'staged_payload'],
                'vessels' => ['call_sign', 'flag_state'],
            ],
        ],
        'r_validate' => ['insert' => [], 'update' => []],
        'r_part3' => ['insert' => ['evaluation_metrics', 'drift_measurements'], 'update' => []],
    ];
}

/** @return list<string> */
function schemaTables(): array
{
    return array_map(
        fn (object $row): string => $row->name,
        DB::select("SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name"),
    );
}

/**
 * Load workers.sql as t_r_* roles, create a test account holding $role, and run $check on its connection.
 *
 * @param  Closure(Connection): void  $check
 */
function asTestWorker(string $role, Closure $check): void
{
    $password = Str::random(24);

    try {
        loadTestWorkerRoles();
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
        dropTestWorkerRoles();
    }
}

test('each role holds exactly its DEC-08 rights and nothing else', function (string $role) {
    $expected = [];

    foreach (array_diff(schemaTables(), UNREADABLE_BY_WORKERS, FRAMEWORK_TABLES, array_keys(COLUMN_READS)) as $table) {
        $expected[$table][] = 'SELECT';
    }
    foreach (COLUMN_READS as $table => $columns) {
        foreach ($columns as $column) {
            $expected[$table][] = "SELECT({$column})";
        }
    }
    foreach (dec08Writes()[$role]['insert'] as $table) {
        $expected[$table][] = 'INSERT';
    }
    foreach (dec08Writes()[$role]['update'] as $table => $columns) {
        foreach ($columns as $column) {
            $expected[$table][] = "UPDATE({$column})";
        }
    }

    try {
        loadTestWorkerRoles();

        expect(heldRights("t_{$role}"))->toBe(sortedRights($expected));
    } finally {
        dropTestWorkerRoles();
    }
})->with(array_values(CreateWorkerAccounts::ACCOUNTS))->group('schema');

test('every table in the schema is either readable by workers or excluded on purpose', function () {
    preg_match_all("/^GRANT SELECT(?: \([^)]*\))? ON `([a-z0-9_]+)` TO 'r_validate';/m", file_get_contents(database_path('grants/workers.sql')), $granted);

    $undecided = array_diff(schemaTables(), $granted[1], UNREADABLE_BY_WORKERS, FRAMEWORK_TABLES);

    expect(array_values($undecided))->toBe([], 'a new table needs a grant in workers.sql, or a place on the excluded list (DEC-08)');
})->group('schema');

test('the DEC-08 matrix of this test covers the six accounts the command creates', function () {
    expect(array_keys(dec08Writes()))->toBe(array_values(CreateWorkerAccounts::ACCOUNTS));
});

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

/**
 * The rows are committed, not rolled back: the worker signs in on its own connection and
 * cannot see an open transaction. They are deleted in `finally`, and a killed run is wiped
 * by the next schema rebuild.
 */
test('a worker with column grants still updates a table that carries the version token', function () {
    $typeId = DB::table('vessel_types')->insertGetId(['code' => 'T_TOKEN_'.Str::random(8), 'name' => 'Token probe']);
    $vesselId = DB::table('vessels')->insertGetId([
        'imo_number' => (string) random_int(1000000, 9999999), 'name' => 'Token Probe', 'vessel_type_id' => $typeId,
        'loa' => 100, 'beam' => 20, 'max_draft' => 8,
        'updated_at' => '2000-01-01 00:00:00.000000',
    ]);

    try {
        $before = DB::table('vessels')->where('id', $vesselId)->value('updated_at');

        asTestWorker('r_edi', function ($worker) use ($vesselId) {
            expect($worker->table('vessels')->where('id', $vesselId)->update(['call_sign' => 'SUXX']))->toBe(1);
        });

        expect(DB::table('vessels')->where('id', $vesselId)->value('updated_at'))->toBeGreaterThan($before);
    } finally {
        DB::table('vessels')->where('id', $vesselId)->delete();
        DB::table('vessel_types')->where('id', $typeId)->delete();
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

test('workers.sql carries no demo or placeholder line, because it is published into schema.sql', function () {
    $lines = file(database_path('grants/workers.sql'), FILE_IGNORE_NEW_LINES);

    foreach ($lines as $number => $line) {
        expect($line)->not->toMatch('/\b(DEMO|do not merge)\b/i', 'line '.($number + 1));
    }
});
