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
