<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * The database itself refuses rows that break the design's rules (P1-B4).
 * A sample of each kind of CHECK is broken on purpose; the name guard keeps all 129 in place.
 * Foreign-key checks are off and every test is rolled back, as in SchemaConstraintsTest.
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

/**
 * A row that satisfies every rule of its table; foreign keys point at any id because FK checks are off.
 *
 * @return array<string, mixed>
 */
function validRow(string $table): array
{
    return match ($table) {
        'port_profiles' => [
            'public_id' => '00000000-0000-0000-0000-000000000001', 'governorate_code' => 'EG-DT', 'name' => 'Damietta Port',
            'latitude' => 31.47, 'longitude' => 31.76, 'timezone' => 'Africa/Cairo',
        ],
        'license_states' => ['mode' => 'FULL', 'read_only_reason' => 'NONE'],
        'organization_units' => ['unit_type' => 'HARBOUR_AUTHORITY', 'terminal_id' => null],
        'user_role_assignments' => [
            'user_id' => 1, 'role' => 'PORT_STAFF', 'organization_unit_id' => 1, 'unit_type' => 'HARBOUR_AUTHORITY',
            'active_from' => '2026-10-01 08:00:00', 'active_to' => null,
        ],
        'vessels' => [
            'imo_number' => '9074729', 'name' => 'MSC Probe', 'vessel_type_id' => 1, 'loa' => 300, 'beam' => 48, 'max_draft' => 14,
        ],
        'berths' => ['code' => 'B-01', 'length' => 350, 'water_depth' => 15, 'depth_datum' => 'Chart Datum', 'max_vessel_loa' => 300],
        'storage_facilities' => [
            'terminal_id' => 1, 'terminal_type' => 'NON_CONTAINER', 'storage_type_id' => 1, 'detail_kind' => 'WAREHOUSE',
            'name' => 'Warehouse 1', 'code' => 'WH-01', 'capacity' => 100, 'capacity_unit' => 'TONNES',
        ],
        'baplie_files' => [
            'vessel_id' => 1, 'berthing_request_id' => 1, 'uploaded_by' => 1,
            'file_hash_sha256' => str_repeat('a1', 32), 'storage_path' => 'baplie/1.edi',
        ],
        'notification_logs' => [
            'user_id' => 1, 'related_entity_type' => 'PLAN_COMPONENT', 'related_entity_id' => 1,
            'type' => 'PLAN_ASSIGNMENT', 'channel' => 'MOBILE', 'status' => 'SENT',
        ],
        'part1_runs' => ['berthing_request_id' => 1, 'retry_count' => 0],
        'drift_measurements' => [
            'model_version_id' => 1, 'metric_type' => 'ROLLING_MAE', 'feature_name' => null,
            'metric_value' => 0.5, 'threshold' => 1, 'measured_at' => '2026-10-01 08:00:00',
        ],
        'recommendation_options' => [
            'recommendation_id' => 1, 'terminal_id' => 1, 'option_rank' => 1, 'berth_id' => 1,
            'planned_start' => '2026-10-01 08:00:00', 'planned_end' => '2026-10-01 20:00:00',
            'service_time_method' => 'FORMULA', 'service_time_p50_hours' => 12, 'service_time_p90_hours' => null,
        ],
        'yard_slots' => ['yard_block_id' => 1, 'bay' => 1, 'row_no' => 1, 'tier' => 1, 'status' => 'ACTIVE', 'container_id' => null],
        'environment_forecast_points' => ['forecast_run_id' => 1, 'valid_at' => '2026-10-01 08:00:00', 'wind_direction_deg' => 270],
        'operational_plans' => [
            'berthing_request_id' => 1, 'recommendation_id' => 1, 'recommendation_option_id' => 1, 'status' => 'CONFIRMED', 'confirmed_by' => 1,
        ],
        'recommendations' => [
            'solver_run_id' => 1, 'berthing_request_id' => 1, 'terminal_id' => 1, 'entry_window_proposal_id' => 1,
            'status' => 'PENDING_REVIEW', 'purpose' => 'INITIAL', 'generated_at' => '2026-10-01 08:00:00',
        ],
        'entry_window_proposals' => [
            'berthing_request_id' => 1, 'forecast_run_id' => 1, 'tide_prediction_run_id' => 1,
            'status' => 'PROPOSED', 'generated_at' => '2026-10-01 08:00:00',
        ],
        'ml_model_versions' => [
            'ml_model_id' => 1, 'version_no' => 1, 'status' => 'CHALLENGER', 'artifact_path' => 'models/1', 'trained_at' => '2026-10-01 08:00:00',
        ],
        'baplie_parse_runs' => ['baplie_file_id' => 1, 'status' => 'QUEUED'],
        'yard_runs' => ['terminal_recommendation_id' => 1, 'status' => 'QUEUED'],
        'rationale_templates' => ['code' => 'P2_DEFAULT', 'version' => 1, 'template_text' => 'Berth {berth}', 'is_active' => 1],
    };
}

/**
 * One sampled CHECK per kind: constraint => [table, the columns that break only that rule].
 *
 * @return array<string, array{0: string, 1: array<string, mixed>}>
 */
function brokenRows(): array
{
    return [
        'range: chk_port_profile_lat' => ['port_profiles', ['latitude' => 91]],
        'singleton: chk_port_profile_singleton' => ['port_profiles', ['id' => 2]],
        'state consistency: chk_license_state_reason' => ['license_states', ['mode' => 'FULL', 'read_only_reason' => 'NO_LICENSE']],
        'mutually exclusive: chk_org_unit_terminal' => ['organization_units', ['unit_type' => 'HARBOUR_AUTHORITY', 'terminal_id' => 7]],
        'cross-column order: chk_ura_period' => ['user_role_assignments', ['active_to' => '2026-09-30 08:00:00']],
        'format: chk_vessel_imo' => ['vessels', ['imo_number' => '907472A']],
        'cross-column limit: chk_berth_loa_fits' => ['berths', ['max_vessel_loa' => 360]],
        'sum within capacity: chk_sf_capacity_sum' => ['storage_facilities', ['current_occupancy' => 90, 'reserved_capacity' => 20]],
        'case-sensitive format: chk_bf_hash' => ['baplie_files', ['file_hash_sha256' => str_repeat('A1', 32)]],
        'mutually exclusive: chk_nl_push_no_ack' => ['notification_logs', [
            'channel' => 'PUSH', 'status' => 'ACKNOWLEDGED', 'acknowledged_at' => '2026-10-01 08:05:00',
        ]],
        'range: chk_p1r_retry' => ['part1_runs', ['retry_count' => 4]],
        'state consistency: chk_dm_feature' => ['drift_measurements', ['metric_type' => 'PSI', 'feature_name' => null]],
        'conditional: chk_ro_p90' => ['recommendation_options', ['service_time_method' => 'ML', 'service_time_p90_hours' => null]],
        'state consistency: chk_ys_blocked_empty' => ['yard_slots', ['status' => 'BLOCKED', 'container_id' => 5]],
        'range: chk_efp_direction' => ['environment_forecast_points', ['wind_direction_deg' => 360]],
    ];
}

test('a valid row of each sampled table is accepted', function (string $table) {
    expect(DB::table($table)->insert(validRow($table)))->toBeTrue();
})->with(fn () => array_values(array_unique(array_column(brokenRows(), 0))))->group('schema');

test('the database refuses a row that breaks the rule', function (string $table, array $broken, string $constraint) {
    expect(fn () => DB::table($table)->insert(array_merge(validRow($table), $broken)))
        ->toThrow(QueryException::class, "Check constraint '{$constraint}' is violated");
})->with(fn () => collect(brokenRows())
    ->map(fn (array $case, string $label) => [$case[0], $case[1], substr($label, strrpos($label, ' ') + 1)])
    ->all())->group('schema');

test('a generated column computes the value the design states', function (string $table, array $row, string $column, mixed $expected) {
    $id = DB::table($table)->insertGetId(array_merge(validRow($table), $row));

    expect(DB::table($table)->where('id', $id)->value($column))->toBe($expected);
})->with([
    'available_capacity = capacity - occupancy - reserved - blocked' => [
        'storage_facilities', ['current_occupancy' => 30, 'reserved_capacity' => 20, 'blocked_capacity' => 10], 'available_capacity', '40.00',
    ],
    'is_drifted when the metric is above the threshold' => ['drift_measurements', ['metric_value' => 1.5], 'is_drifted', 1],
    'is_drifted is 0 at or below the threshold' => ['drift_measurements', ['metric_value' => 1], 'is_drifted', 0],
    'yard_runs.active_key while QUEUED or RUNNING' => ['yard_runs', ['terminal_recommendation_id' => 9, 'status' => 'RUNNING'], 'active_key', 9],
    'yard_runs.active_key is NULL once REPLACED' => ['yard_runs', ['status' => 'REPLACED'], 'active_key', null],
    'baplie_parse_runs.active_key while QUEUED' => ['baplie_parse_runs', ['baplie_file_id' => 9], 'active_key', 9],
    'operational_plans.active_key while CONFIRMED' => ['operational_plans', ['berthing_request_id' => 9], 'active_key', 9],
    'operational_plans.active_key is NULL once CANCELLED' => ['operational_plans', ['status' => 'CANCELLED'], 'active_key', null],
    'recommendations.active_key for a live INITIAL recommendation' => ['recommendations', ['berthing_request_id' => 9], 'active_key', 9],
    'recommendations.active_key is NULL for a DELAY_REPLAN' => ['recommendations', ['purpose' => 'DELAY_REPLAN'], 'active_key', null],
])->group('schema');

/**
 * Generated unique keys: index => [table, the row that is "active", the change that makes it inactive, a second row's own columns].
 *
 * @return array<string, array{0: string, 1: array<string, mixed>, 2: array<string, mixed>, 3: array<string, mixed>}>
 */
function activeKeys(): array
{
    return [
        'one confirmed plan per request: active_key' => ['operational_plans', [], ['status' => 'CANCELLED'], []],
        'one live INITIAL recommendation per request: uq_rec_active' => ['recommendations', [], ['status' => 'REPLACED'], []],
        'one confirmed proposal per request: confirmed_key' => ['entry_window_proposals', [
            'status' => 'CONFIRMED', 'confirmed_window_id' => 1, 'confirmed_by' => 1, 'confirmed_at' => '2026-10-01 09:00:00',
        ], ['status' => 'REPLACED'], []],
        'one champion per model: champion_key' => ['ml_model_versions', [
            'status' => 'CHAMPION', 'deployed_at' => '2026-10-01 09:00:00',
        ], ['status' => 'ARCHIVED'], ['version_no' => 2]],
        'one open BAPLIE parse per file: uq_bpr_active' => ['baplie_parse_runs', [], ['status' => 'REPLACED'], []],
        'one open yard run per terminal recommendation: uq_yr_active' => ['yard_runs', [], ['status' => 'REPLACED'], []],
        'one active template per code: active_code' => ['rationale_templates', [], ['is_active' => 0], ['version' => 2]],
        'one ACTIVE BAPLIE file per request: active_request_key' => ['baplie_files', ['status' => 'ACTIVE'], ['status' => 'REPLACED'], [
            'file_hash_sha256' => str_repeat('b2', 32),
        ]],
        'the same file once per request unless withdrawn: uq_bf_request_live_hash' => ['baplie_files', [], ['status' => 'WITHDRAWN'], []],
        'one harbour authority unit: uq_org_unit_type_key' => ['organization_units', [], ['unit_type' => 'TERMINAL', 'terminal_id' => 7], []],
    ];
}

test('a generated unique key refuses a second active row and allows it once the first is inactive', function (
    string $table, array $active, array $inactive, array $second, string $index,
) {
    $firstId = DB::table($table)->insertGetId(array_merge(validRow($table), $active));
    $insertSecond = fn () => DB::table($table)->insert(array_merge(validRow($table), $active, $second));

    expect($insertSecond)->toThrow(QueryException::class, "for key '{$table}.{$index}'");

    DB::table($table)->where('id', $firstId)->update($inactive);

    expect($insertSecond())->toBeTrue();
})->with(fn () => collect(activeKeys())
    ->map(fn (array $case, string $label) => [...$case, substr($label, strrpos($label, ' ') + 1)])
    ->all())->group('schema');

test('the app connection runs in strict mode, so a bad ENUM value is refused, not stored as empty', function () {
    expect(DB::scalar('SELECT @@session.sql_mode'))->toContain('STRICT_TRANS_TABLES');
})->group('schema');

test('an ENUM column refuses a value outside its list', function (string $table, string $column) {
    expect(fn () => DB::table($table)->insert(array_merge(validRow($table), [$column => 'NOT_IN_THE_LIST'])))
        ->toThrow(QueryException::class, "Data truncated for column '{$column}'");
})->with([
    'notification_logs.channel' => ['notification_logs', 'channel'],
    'yard_runs.status' => ['yard_runs', 'status'],
    'user_role_assignments.role' => ['user_role_assignments', 'role'],
])->group('schema');

test('all 129 CHECK constraints of the design are in place by name', function () {
    $names = DB::table('information_schema.CHECK_CONSTRAINTS')
        ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))
        ->orderBy('CONSTRAINT_NAME')
        ->pluck('CONSTRAINT_NAME')
        ->all();

    expect($names)->toBe([
        'chk_al_states', 'chk_berth_dimensions', 'chk_berth_lat', 'chk_berth_loa_fits', 'chk_berth_lon',
        'chk_bf_correction', 'chk_bf_hash', 'chk_bp_period', 'chk_bpr_error', 'chk_bpr_finished',
        'chk_bpr_outcome', 'chk_br_cb', 'chk_br_departure_draft', 'chk_br_ports', 'chk_br_source',
        'chk_br_tonnage', 'chk_brc_quantity', 'chk_brsh_change', 'chk_channel_dimensions', 'chk_cme_oog',
        'chk_cme_pod', 'chk_cme_pol', 'chk_cme_reefer', 'chk_cme_stowage', 'chk_cme_weight',
        'chk_container_number', 'chk_cst_length', 'chk_cst_teu', 'chk_ct_temp', 'chk_dfs_method',
        'chk_dfs_values', 'chk_dm_feature', 'chk_dzac_type', 'chk_ecap_value', 'chk_efp_direction',
        'chk_efp_values', 'chk_em_period', 'chk_em_scope', 'chk_eq_quantity', 'chk_ewp_confirm_set',
        'chk_ewp_confirmed', 'chk_ewp_reject_set', 'chk_ewp_score', 'chk_fr_horizon', 'chk_fr_manual',
        'chk_fr_on_demand', 'chk_fw_values', 'chk_license_state_reason', 'chk_license_state_singleton',
        'chk_mlv_champion_deployed', 'chk_mlv_version', 'chk_mlv_window', 'chk_nl_ack', 'chk_nl_failed',
        'chk_nl_push_no_ack', 'chk_org_unit_terminal', 'chk_ozd_positive', 'chk_ozd_type', 'chk_p1r_retry',
        'chk_pc_assigned', 'chk_pc_resource', 'chk_pc_time', 'chk_pilot_rank_limit_loa', 'chk_port_profile_lat',
        'chk_port_profile_lon', 'chk_port_profile_singleton', 'chk_port_shift_times', 'chk_ro_p90',
        'chk_ro_values', 'chk_rov_berth', 'chk_rov_pilot', 'chk_rov_time', 'chk_rov_tug', 'chk_rr_review',
        'chk_rr_time', 'chk_rr_versions', 'chk_rrf_kind', 'chk_rt_version', 'chk_rzd_plugs', 'chk_rzd_type',
        'chk_sai_quantity', 'chk_sai_type', 'chk_sc_period', 'chk_sf_area', 'chk_sf_capacity',
        'chk_sf_capacity_sum', 'chk_sf_floor_load', 'chk_sf_lat', 'chk_sf_lon', 'chk_sf_yard_rules',
        'chk_sm_quantity', 'chk_sm_source_direction', 'chk_sr_finished', 'chk_sr_horizon', 'chk_sr_manual',
        'chk_sr_time', 'chk_tc_values', 'chk_td_kind', 'chk_td_pressure', 'chk_td_temp', 'chk_tpp_period',
        'chk_tpp_unit', 'chk_tpp_values', 'chk_tpr_horizon', 'chk_tr_confidence', 'chk_tr_decision',
        'chk_tr_lifecycle_yard', 'chk_tr_type_terminal', 'chk_tr_window', 'chk_tugboat_power',
        'chk_ud_push_revoked', 'chk_ura_period', 'chk_ura_role_matches_unit', 'chk_user_username_lower',
        'chk_vessel_dims', 'chk_vessel_imo', 'chk_vessel_tonnage', 'chk_waypoint_lat', 'chk_waypoint_lon',
        'chk_waypoint_order', 'chk_wd_kind', 'chk_wd_temp', 'chk_ypi_type', 'chk_yr_error', 'chk_yr_finished',
        'chk_yr_outcome', 'chk_ys_blocked_empty', 'chk_ys_position', 'chk_yz_kind',
    ]);
})->group('schema');
