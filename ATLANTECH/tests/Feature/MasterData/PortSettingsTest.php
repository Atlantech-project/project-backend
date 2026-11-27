<?php

use App\Modules\Access\Enums\Role;
use App\Modules\MasterData\Enums\PortSettingValueType;
use App\Modules\MasterData\Models\PortSetting;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Facades\DB;

/*
 * P2-F1 typed port settings editor (FR-MD-18, CT-18 §4, DEC-12): only the Port Admin, the value checked by its type,
 * its limits and the cross-field rules, stored as the text the AI workers parse. Rolled back after each test.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    $this->admin = makeUserWithRole(Role::PortGm);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function setting(string $key, PortSettingValueType $type, string $value, array $attributes = []): PortSetting
{
    return PortSetting::factory()
        ->create(['setting_key' => $key, 'value_type' => $type, 'setting_value' => $value, ...$attributes])
        ->refresh();
}

function putSetting(PortSetting $setting, mixed $value)
{
    return test()->actingAs(test()->admin, 'web')
        ->putJson("/api/v1/port-settings/{$setting->setting_key}", ['value' => $value, 'version' => VersionToken::of($setting)]);
}

it('lists the settings by key with typed values and limits', function () {
    setting('solver_time_limit_seconds', PortSettingValueType::Int, '10', ['min_value' => 1, 'max_value' => 120]);
    setting('min_app_version', PortSettingValueType::String, '1.0.0');

    $this->actingAs($this->admin, 'web')->getJson('/api/v1/port-settings')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.setting_key', 'min_app_version')
        ->assertJsonPath('data.0.value', '1.0.0')
        ->assertJsonPath('data.0.min', null)
        ->assertJsonPath('data.0.updated_by', null)
        ->assertJsonPath('data.1.value', 10)
        ->assertJsonPath('data.1.min', 1)
        ->assertJsonPath('data.1.max', 120);
});

it('stores each type as text, fills updated_by and audits before and after', function (PortSettingValueType $type, string $old, mixed $sent, string $stored, mixed $returned) {
    $setting = setting('sample_key', $type, $old);

    $response = putSetting($setting, $sent)->assertOk()->assertJsonPath('updated_by', $this->admin->id);

    $row = AuditLog::query()->where('action', AuditLogAction::Update)->where('entity_type', 'port_settings')->sole();

    expect($setting->fresh()->setting_value)->toBe($stored)
        ->and($response->json('value'))->toEqual($returned)
        ->and($response->json('version'))->toBe(VersionToken::of($setting->fresh()))
        ->and($row->before_state['setting_value'])->toBe($old)
        ->and($row->after_state['setting_value'])->toBe($stored);
})->with([
    'int' => [PortSettingValueType::Int, '10', 20, '20', 20],
    'int as text' => [PortSettingValueType::Int, '10', '30', '30', 30],
    'decimal' => [PortSettingValueType::Decimal, '1.0', 2.5, '2.5', 2.5],
    'decimal as text' => [PortSettingValueType::Decimal, '1', '0.25', '0.25', 0.25],
    'bool' => [PortSettingValueType::Bool, 'true', false, 'false', false],
    'string' => [PortSettingValueType::String, 'a', '0 6 * * *', '0 6 * * *', '0 6 * * *'],
    'json' => [PortSettingValueType::Json, '[]', ['hours' => [6, 18]], '{"hours":[6,18]}', ['hours' => [6, 18]]],
]);

it('refuses a value of the wrong type with 422 and keeps the old one', function (PortSettingValueType $type, mixed $sent, string $key) {
    $setting = setting('sample_key', $type, '1');

    putSetting($setting, $sent)->assertStatus(422)->assertJsonPath('errors.value.0', 'errors.port_settings.'.$key);

    $row = AuditLog::query()->where('action', AuditLogAction::RejectedAttempt)->sole();

    expect($setting->fresh()->setting_value)->toBe('1')
        ->and($setting->fresh()->updated_by)->toBeNull()
        ->and($row->entity_id)->toBe($setting->id)
        ->and($row->after_state)->toMatchArray(['status' => 422, 'code' => 'VALIDATION_FAILED', 'reason' => $key]);
})->with([
    'int with a fraction' => [PortSettingValueType::Int, 1.5, 'invalid_int'],
    'int from words' => [PortSettingValueType::Int, 'ten', 'invalid_int'],
    'int from a boolean' => [PortSettingValueType::Int, true, 'invalid_int'],
    'decimal from words' => [PortSettingValueType::Decimal, 'x', 'invalid_decimal'],
    'decimal with 7 places' => [PortSettingValueType::Decimal, '0.1234567', 'invalid_decimal'],
    'bool from text' => [PortSettingValueType::Bool, 'yes', 'invalid_bool'],
    'empty string' => [PortSettingValueType::String, '', 'invalid_string'],
    'null' => [PortSettingValueType::String, null, 'invalid_string'],
    'string too long' => [PortSettingValueType::String, str_repeat('a', 256), 'too_long'],
    'json from text' => [PortSettingValueType::Json, '{"a":1}', 'invalid_json'],
]);

it('enforces min and max, edges included', function (mixed $sent, int $status, ?string $key) {
    $setting = setting('solver_option_equivalence_pct', PortSettingValueType::Decimal, '10', ['min_value' => 0, 'max_value' => 50]);

    $response = putSetting($setting, $sent)->assertStatus($status);

    if ($key !== null) {
        $response->assertJsonPath('errors.value.0', 'errors.port_settings.'.$key);
        expect($setting->fresh()->setting_value)->toBe('10');
    }
})->with([
    'at the minimum' => [0, 200, null],
    'at the maximum' => [50, 200, null],
    'below' => [-0.5, 422, 'below_min'],
    'above' => [50.5, 422, 'above_max'],
]);

it('keeps min_app_version as x.y.z', function () {
    $setting = setting('min_app_version', PortSettingValueType::String, '1.0.0');

    putSetting($setting, '2.0')->assertStatus(422)->assertJsonPath('errors.value.0', 'errors.port_settings.app_version_format');
    putSetting($setting, '1.10.2')->assertOk()->assertJsonPath('value', '1.10.2');
});

it('keeps the part 1 window a multiple of the time step, from either side', function () {
    $window = setting('part1_min_window_minutes', PortSettingValueType::Int, '60');
    $step = setting('part1_time_step_minutes', PortSettingValueType::Int, '15');

    putSetting($window, 50)->assertStatus(422)->assertJsonPath('errors.value.0', 'errors.port_settings.window_not_multiple_of_step');
    putSetting($step, 25)->assertStatus(422);
    putSetting($step, 0)->assertStatus(422);
    putSetting($window, 90)->assertOk();
});

it('keeps the validation result lifetime greater than the timeout, from either side', function () {
    $ttl = setting('validation_result_ttl_seconds', PortSettingValueType::Int, '600');
    $timeout = setting('validation_timeout_seconds', PortSettingValueType::Int, '15');

    putSetting($ttl, 15)->assertStatus(422)->assertJsonPath('errors.value.0', 'errors.port_settings.ttl_not_greater_than_timeout');
    putSetting($timeout, 600)->assertStatus(422);
    putSetting($ttl, 16)->assertOk();
});

it('applies a cross-field rule only when both keys exist', function () {
    putSetting(setting('part1_min_window_minutes', PortSettingValueType::Int, '60'), 50)->assertOk();
});

it('writes nothing when the value does not change, so the seeder may still update the default', function () {
    $setting = setting('reminder_lead_minutes', PortSettingValueType::Int, '120');

    putSetting($setting, 120)->assertOk()->assertJsonPath('updated_by', null)->assertJsonPath('version', VersionToken::of($setting));

    expect(AuditLog::query()->where('action', AuditLogAction::Update)->count())->toBe(0);
});

it('refuses to change anything but the value', function (string $field) {
    $setting = setting('reminder_lead_minutes', PortSettingValueType::Int, '120');

    $this->actingAs($this->admin, 'web')
        ->putJson('/api/v1/port-settings/reminder_lead_minutes', ['value' => 90, $field => 'x', 'version' => VersionToken::of($setting)])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => [$field]]);

    expect($setting->fresh()->setting_value)->toBe('120');
})->with(['setting_key', 'value_type', 'min', 'max', 'description', 'updated_by']);

it('answers 409 VERSION_CONFLICT to a stale version', function () {
    $setting = setting('reminder_lead_minutes', PortSettingValueType::Int, '120');
    putSetting($setting, 90)->assertOk();

    putSetting($setting, 60)->assertStatus(409)->assertJsonPath('code', 'VERSION_CONFLICT');

    expect($setting->fresh()->setting_value)->toBe('90');
});

it('is the Port Admin only: other roles 403, guests 401, an unknown key 404', function () {
    $setting = setting('reminder_lead_minutes', PortSettingValueType::Int, '120');
    $body = ['value' => 90, 'version' => VersionToken::of($setting)];

    $this->getJson('/api/v1/port-settings')->assertUnauthorized();
    $this->actingAs(makeUserWithRole(Role::PortStaff), 'web')->getJson('/api/v1/port-settings')->assertForbidden();
    $this->actingAs(makeUserWithRole(Role::MarineSafetyOfficer), 'web')->putJson('/api/v1/port-settings/reminder_lead_minutes', $body)->assertForbidden();
    $this->actingAs($this->admin, 'web')->putJson('/api/v1/port-settings/no_such_key', $body)->assertNotFound();

    expect($setting->fresh()->setting_value)->toBe('120');
});
