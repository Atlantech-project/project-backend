<?php

use Illuminate\Support\Facades\DB;

/*
 * The port schema as built by the migrations matches CT-17 (P1-B1, reviewed in P1-B2).
 * Runs against MySQL from docker-compose.yml; run only these with `--group=schema`.
 */

beforeEach(function () {
    useSchemaDatabase();
});

/**
 * @return array<string, string> PortSync (singular) name => table name, from contracts/db/table-names.md
 */
function contractTableNames(): array
{
    preg_match_all(
        '/^\|\s*\d+\s*\|\s*`([a-z0-9_]+)`\s*\|[^|]+\|\s*`([a-z0-9_]+)`\s*\|/m',
        file_get_contents(base_path('docs/reference/contracts/db/table-names.md')),
        $matches,
    );

    return array_combine($matches[1], $matches[2]);
}

/**
 * @return list<string>
 */
function databaseTables(): array
{
    return DB::table('information_schema.TABLES')
        ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
        ->pluck('TABLE_NAME')
        ->all();
}

test('every CT-17 table exists with its plural name and no singular PortSync name is left', function () {
    $names = contractTableNames();
    $tables = databaseTables();

    expect($names)->toHaveCount(92)
        ->and(array_diff($names, $tables))->toBe([])
        ->and(array_intersect(array_diff(array_keys($names), $names), $tables))->toBe([]);
})->group('schema');

test('the Laravel framework tables sit next to the port tables', function () {
    expect(databaseTables())->toContain(
        'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens',
    );
})->group('schema');

test('every port table is InnoDB with utf8mb4_0900_ai_ci', function () {
    $rows = DB::table('information_schema.TABLES')
        ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
        ->whereIn('TABLE_NAME', contractTableNames())
        ->select('ENGINE', 'TABLE_COLLATION')
        ->distinct()
        ->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->ENGINE)->toBe('InnoDB')
        ->and($rows->first()->TABLE_COLLATION)->toBe('utf8mb4_0900_ai_ci');
})->group('schema');

test('every id and foreign key column is BIGINT UNSIGNED', function () {
    $signed = DB::table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
        ->whereIn('TABLE_NAME', contractTableNames())
        ->where('DATA_TYPE', 'bigint')
        ->where('COLUMN_TYPE', 'not like', '%unsigned%')
        ->pluck('COLUMN_NAME', 'TABLE_NAME')
        ->all();

    expect($signed)->toBe([]);
})->group('schema');

test('every id column is BIGINT UNSIGNED, the singletons included (CT-17)', function () {
    $otherTypes = DB::table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
        ->whereIn('TABLE_NAME', contractTableNames())
        ->where('COLUMN_NAME', 'id')
        ->where('COLUMN_TYPE', '!=', 'bigint unsigned')
        ->pluck('COLUMN_TYPE', 'TABLE_NAME')
        ->all();

    expect($otherTypes)->toBe([]);
})->group('schema');

test('the user_devices foreign key keeps its Report A name', function () {
    $names = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
        ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))
        ->where('TABLE_NAME', 'user_devices')
        ->pluck('CONSTRAINT_NAME')
        ->all();

    expect($names)->toBe(['fk_device_user']);
})->group('schema');

test('CHECK constraints and generated columns are real', function () {
    $checks = DB::table('information_schema.CHECK_CONSTRAINTS')
        ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))
        ->count();
    $generated = DB::table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
        ->where('EXTRA', 'like', '%STORED GENERATED%')
        ->selectRaw("CONCAT(TABLE_NAME, '.', COLUMN_NAME) AS name")
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    // 113 DDL fixes + 8 Report A + 2 FCM + 3 yard_runs + 3 baplie_parse_runs
    expect($checks)->toBe(129)
        ->and($generated)->toBe([
            'baplie_files.active_request_key',
            'baplie_files.live_hash_key',
            'baplie_parse_runs.active_key',
            'container_size_types.is_reefer',
            'drift_measurements.is_drifted',
            'entry_window_proposals.confirmed_key',
            'equipment.available_quantity',
            'ml_model_versions.champion_key',
            'operational_plans.active_key',
            'organization_units.single_key',
            'rationale_templates.active_code',
            'recommendations.active_key',
            'retraining_runs.review_pending',
            'storage_facilities.available_capacity',
            'tide_constituent_sets.active_key',
            'user_role_assignments.active_assignment_key',
            'yard_runs.active_key',
        ]);
})->group('schema');

test('users sign in with a username and have no email column (DEC-35)', function () {
    $columns = DB::table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
        ->where('TABLE_NAME', 'users')
        ->pluck('COLUMN_NAME')
        ->all();

    expect($columns)->toContain('username')->not->toContain('email');
})->group('schema');

test('the version token updated_at is DATETIME(6) and moves on every update on the master-data tables', function () {
    $tables = [
        'navigation_channels', 'port_profiles', 'port_settings', 'license_states', 'users', 'terminals',
        'berths', 'tugboats', 'safety_constraints', 'port_shifts', 'equipment', 'storage_facilities',
        'pilot_profiles', 'tug_master_profiles', 'pilot_rank_limits', 'yard_slots',
    ];

    $tokens = DB::table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
        ->where('COLUMN_NAME', 'updated_at')
        ->whereIn('TABLE_NAME', $tables)
        ->selectRaw("TABLE_NAME, CONCAT(COLUMN_TYPE, ' | ', IS_NULLABLE, ' | ', COLUMN_DEFAULT, ' | ', EXTRA) AS token")
        ->pluck('token', 'TABLE_NAME');

    expect($tokens)->toHaveCount(count($tables))
        ->and($tokens->unique()->values()->all())->toBe([
            'datetime(6) | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED on update CURRENT_TIMESTAMP(6)',
        ]);
})->group('schema');

test('the connection runs in UTC', function () {
    expect(DB::scalar('SELECT @@session.time_zone'))->toBe('+00:00');
})->group('schema');
