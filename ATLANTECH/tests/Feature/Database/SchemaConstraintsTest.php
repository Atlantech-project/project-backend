<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * CHECK constraints and generated unique keys refuse bad rows (P1-B1, CT-17).
 * Foreign-key checks are off inside each test so a test only exercises the rule it names;
 * every test runs in a transaction that is rolled back.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
    DB::statement('SET FOREIGN_KEY_CHECKS = 1');
});

function insertUser(string $username): int
{
    return DB::table('users')->insertGetId(['name' => 'Port Staff', 'username' => $username, 'password_hash' => 'x']);
}

function insertHarbourRole(int $userId, string $role): void
{
    DB::table('user_role_assignments')->insert([
        'user_id' => $userId, 'role' => $role, 'organization_unit_id' => 1, 'unit_type' => 'HARBOUR_AUTHORITY',
    ]);
}

test('a username must be lowercase and at least 3 characters', function (string $username) {
    expect(fn () => insertUser($username))->toThrow(QueryException::class, 'chk_user_username_lower');
})->with(['Ahmed', 'STAFF.AHMED', 'ab'])->group('schema');

test('a lowercase username is accepted', function () {
    expect(insertUser('staff.ahmed'))->toBeInt();
})->group('schema');

test('a role must belong to the unit type it is assigned in', function () {
    $userId = insertUser('pilot.k');

    expect(fn () => insertHarbourRole($userId, 'MARINE_PILOT'))
        ->toThrow(QueryException::class, 'chk_ura_role_matches_unit');
})->group('schema');

test('a user holds one active role at a time (DEC-36)', function () {
    $userId = insertUser('staff.mona');
    insertHarbourRole($userId, 'PORT_STAFF');

    expect(fn () => insertHarbourRole($userId, 'PORT_GM'))->toThrow(QueryException::class, 'uq_ura_active');

    DB::table('user_role_assignments')->where('user_id', $userId)->update(['active_to' => now()->addMinute()]);
    insertHarbourRole($userId, 'PORT_GM');

    expect(DB::table('user_role_assignments')->where('user_id', $userId)->whereNull('active_to')->value('role'))
        ->toBe('PORT_GM');
})->group('schema');

test('previous and next ports must be upper-case UN/LOCODEs', function (string $port, bool $accepted) {
    $insert = fn () => DB::table('berthing_requests')->insert([
        'vessel_id' => 1, 'requested_terminal_id' => 1, 'source' => 'MANUAL', 'submitted_by' => 1,
        'requested_eta' => '2026-11-01 08:00:00', 'arrival_draft' => 10.5, 'block_coefficient' => 0.7,
        'gross_tonnage' => 50000, 'deadweight_tonnes' => 60000, 'previous_port' => $port,
    ]);

    $accepted
        ? expect($insert())->toBeTrue()
        : expect($insert)->toThrow(QueryException::class, 'chk_br_ports');
})->with([
    'upper case' => ['EGDAM', true],
    'lower case' => ['egdam', false],
    'too short' => ['EGDA', false],
])->group('schema');

test('only one tide constituent set is active, through the generated key', function () {
    $set = fn (string $label, int $active) => DB::table('tide_constituent_sets')->insert([
        'version_label' => $label, 'datum_reference' => 'CD', 'valid_from' => '2026-01-01', 'is_active' => $active,
    ]);

    $set('v1', 1);
    $set('v2', 0);

    expect(fn () => $set('v3', 1))->toThrow(QueryException::class, 'active_key');
})->group('schema');

test('a generated column cannot be written', function () {
    expect(fn () => DB::table('tide_constituent_sets')->insert([
        'version_label' => 'v1', 'datum_reference' => 'CD', 'valid_from' => '2026-01-01', 'is_active' => 1, 'active_key' => 1,
    ]))->toThrow(QueryException::class, 'generated column');
})->group('schema');

test('a revoked device keeps no push token (FCM)', function () {
    expect(fn () => DB::table('user_devices')->insert([
        'user_id' => 1, 'device_id' => 'a1b2c3', 'platform' => 'ANDROID', 'app_version' => '1.0.0',
        'revoked_at' => now(), 'push_token' => 'token',
    ]))->toThrow(QueryException::class, 'chk_ud_push_revoked');
})->group('schema');
