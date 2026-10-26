-- ATLANTECH PortSync: base DDL fixes (generated file, NOT executed)
-- Target: MySQL 8.0.16 or later. Run on a COPY of an EMPTY database first.
--
-- Why this file exists: in PortSync.sql, (1) all 115 CHECK constraints are only text inside
-- table COMMENTs, so MySQL does not enforce them, and (2) 13 columns described as
-- 'GENERATED (stored)' are plain columns, so the "at most one ACTIVE" rules that depend on them
-- are not enforced. This file turns both into real constraints and real generated columns.
--
-- Before running: the three undefined types (request_status, request_source,
-- baplie_file_status) must be replaced in the CREATE TABLE statements (see Report A, section 0.1),
-- otherwise the tables cannot be created at all.
--
-- Left out on purpose (they are defined in Report A with their final form):
--   chk_license_state_reason, chk_fr_on_demand, recommendation.active_key.
-- If a database already holds rows, review each statement first: ADD CONSTRAINT fails when a row breaks it.

-- ============================================================
-- PART 1. CHECK constraints (113 of 115, from the table comments; the other 2 are in Report A)
-- ============================================================
ALTER TABLE `channel_waypoint` ADD CONSTRAINT `chk_waypoint_lat` CHECK ((latitude between -(90) and 90));
ALTER TABLE `channel_waypoint` ADD CONSTRAINT `chk_waypoint_lon` CHECK ((longitude between -(180) and 180));
ALTER TABLE `channel_waypoint` ADD CONSTRAINT `chk_waypoint_order` CHECK ((sequence_order > 0));
ALTER TABLE `license_state` ADD CONSTRAINT `chk_license_state_singleton` CHECK ((id = 1));
ALTER TABLE `navigation_channel` ADD CONSTRAINT `chk_channel_dimensions` CHECK (((depth > 0) and (width > 0) and (speed_limit_knots > 0)));
ALTER TABLE `port_profile` ADD CONSTRAINT `chk_port_profile_lat` CHECK ((latitude between -(90) and 90));
ALTER TABLE `port_profile` ADD CONSTRAINT `chk_port_profile_lon` CHECK ((longitude between -(180) and 180));
ALTER TABLE `port_profile` ADD CONSTRAINT `chk_port_profile_singleton` CHECK ((id = 1));
ALTER TABLE `berth` ADD CONSTRAINT `chk_berth_dimensions` CHECK (((length > 0) and (water_depth > 0) and (max_vessel_loa > 0)));
ALTER TABLE `berth` ADD CONSTRAINT `chk_berth_lat` CHECK (((latitude is null) or (latitude between -(90) and 90)));
ALTER TABLE `berth` ADD CONSTRAINT `chk_berth_loa_fits` CHECK ((max_vessel_loa <= length));
ALTER TABLE `berth` ADD CONSTRAINT `chk_berth_lon` CHECK (((longitude is null) or (longitude between -(180) and 180)));
ALTER TABLE `organization_unit` ADD CONSTRAINT `chk_org_unit_terminal` CHECK (((unit_type = 'TERMINAL') = (terminal_id is not null)));
ALTER TABLE `user` ADD CONSTRAINT `chk_user_username_lower` CHECK (((username = lower(username)) and (char_length(username) >= 3)));
ALTER TABLE `user_role_assignment` ADD CONSTRAINT `chk_ura_role_matches_unit` CHECK ((((unit_type = 'HARBOUR_AUTHORITY') and (role in ('PORT_GM','PORT_STAFF','MARINE_SAFETY_OFFICER'))) or ((unit_type = 'MARINE_SERVICES') and (role in ('PILOTAGE_MANAGER','PILOTAGE_OFFICER','MARINE_PILOT','TUG_MANAGER','TUG_OFFICER','TUG_MASTER'))) or ((unit_type = 'TERMINAL') and (role in ('TERMINAL_ADMIN','TERMINAL_OFFICER','TERMINAL_SUPERVISOR')))));
ALTER TABLE `pilot_rank_limit` ADD CONSTRAINT `chk_pilot_rank_limit_loa` CHECK (((max_vessel_loa is null) or (max_vessel_loa > 0)));
ALTER TABLE `port_shift` ADD CONSTRAINT `chk_port_shift_times` CHECK ((end_time <> start_time));
ALTER TABLE `tugboat` ADD CONSTRAINT `chk_tugboat_power` CHECK ((tug_power > 0));
ALTER TABLE `container` ADD CONSTRAINT `chk_container_number` CHECK (regexp_like(container_number,'^[A-Z]{3}[UJZ][0-9]{7}$','c'));
ALTER TABLE `container_size_type` ADD CONSTRAINT `chk_cst_length` CHECK ((length_ft in (10,20,40,45)));
ALTER TABLE `container_size_type` ADD CONSTRAINT `chk_cst_teu` CHECK ((teu_factor > 0));
ALTER TABLE `vessel` ADD CONSTRAINT `chk_vessel_dims` CHECK ((loa > 0) and (beam > 0) and (beam < loa) and (max_draft > 0));
ALTER TABLE `vessel` ADD CONSTRAINT `chk_vessel_imo` CHECK (regexp_like(imo_number, '^[0-9]{7}$'));
ALTER TABLE `vessel` ADD CONSTRAINT `chk_vessel_tonnage` CHECK ((net_tonnage IS NULL OR net_tonnage > 0) and (moulded_depth IS NULL OR moulded_depth > 0));
ALTER TABLE `cargo_type` ADD CONSTRAINT `chk_ct_temp` CHECK (((min_temperature is null) or (max_temperature is null) or (min_temperature <= max_temperature)));
ALTER TABLE `storage_facility` ADD CONSTRAINT `chk_sf_area` CHECK (((area is null) or (area > 0)));
ALTER TABLE `storage_facility` ADD CONSTRAINT `chk_sf_capacity` CHECK ((((capacity is null) or (capacity > 0)) and (current_occupancy >= 0) and (reserved_capacity >= 0) and (blocked_capacity >= 0)));
ALTER TABLE `storage_facility` ADD CONSTRAINT `chk_sf_capacity_sum` CHECK (((capacity is null) or (((current_occupancy + reserved_capacity) + blocked_capacity) <= capacity)));
ALTER TABLE `storage_facility` ADD CONSTRAINT `chk_sf_floor_load` CHECK (((max_floor_load is null) or (max_floor_load > 0)));
ALTER TABLE `storage_facility` ADD CONSTRAINT `chk_sf_lat` CHECK (((latitude is null) or (latitude between -(90) and 90)));
ALTER TABLE `storage_facility` ADD CONSTRAINT `chk_sf_lon` CHECK (((longitude is null) or (longitude between -(180) and 180)));
ALTER TABLE `storage_facility` ADD CONSTRAINT `chk_sf_yard_rules` CHECK ((((detail_kind = 'CONTAINER_YARD') and (terminal_type = 'CONTAINER') and (capacity is null) and (capacity_unit is null) and (current_occupancy = 0) and (reserved_capacity = 0) and (blocked_capacity = 0)) or ((detail_kind <> 'CONTAINER_YARD') and (capacity is not null) and (capacity_unit is not null))));
ALTER TABLE `storage_movement` ADD CONSTRAINT `chk_sm_quantity` CHECK ((quantity > 0));
ALTER TABLE `storage_movement` ADD CONSTRAINT `chk_sm_source_direction` CHECK ((((source = 'VESSEL_DISCHARGE') and (direction = 'IN')) or ((source = 'VESSEL_LOADING') and (direction = 'OUT')) or (source = 'MANUAL')));
ALTER TABLE `tank_detail` ADD CONSTRAINT `chk_td_kind` CHECK ((detail_kind = 'TANK'));
ALTER TABLE `tank_detail` ADD CONSTRAINT `chk_td_pressure` CHECK (((max_pressure_bar is null) or (max_pressure_bar > 0)));
ALTER TABLE `tank_detail` ADD CONSTRAINT `chk_td_temp` CHECK (((min_temperature is null) or (max_temperature is null) or (min_temperature <= max_temperature)));
ALTER TABLE `warehouse_detail` ADD CONSTRAINT `chk_wd_kind` CHECK ((detail_kind = 'WAREHOUSE'));
ALTER TABLE `warehouse_detail` ADD CONSTRAINT `chk_wd_temp` CHECK (((min_temperature is null) or (max_temperature is null) or (min_temperature <= max_temperature)));
ALTER TABLE `dg_zone_allowed_class` ADD CONSTRAINT `chk_dzac_type` CHECK ((zone_detail = 'DANGEROUS_GOODS'));
ALTER TABLE `oog_zone_detail` ADD CONSTRAINT `chk_ozd_positive` CHECK ((((max_length is null) or (max_length > 0)) and ((max_width is null) or (max_width > 0)) and ((max_height is null) or (max_height > 0)) and ((max_weight is null) or (max_weight > 0))));
ALTER TABLE `oog_zone_detail` ADD CONSTRAINT `chk_ozd_type` CHECK ((zone_detail = 'OOG'));
ALTER TABLE `reefer_zone_detail` ADD CONSTRAINT `chk_rzd_plugs` CHECK ((total_power_plugs > 0));
ALTER TABLE `reefer_zone_detail` ADD CONSTRAINT `chk_rzd_type` CHECK ((zone_detail = 'REEFER'));
ALTER TABLE `yard_slot` ADD CONSTRAINT `chk_ys_blocked_empty` CHECK (((status = 'ACTIVE') or (container_id is null)));
ALTER TABLE `yard_slot` ADD CONSTRAINT `chk_ys_position` CHECK (((bay > 0) and (row_no > 0) and (tier > 0)));
ALTER TABLE `yard_zone` ADD CONSTRAINT `chk_yz_kind` CHECK ((detail_kind = 'CONTAINER_YARD'));
ALTER TABLE `equipment` ADD CONSTRAINT `chk_eq_quantity` CHECK (((quantity > 0) and (maintenance_quantity >= 0) and (blocked_quantity >= 0) and ((maintenance_quantity + blocked_quantity) <= quantity)));
ALTER TABLE `equipment_capability` ADD CONSTRAINT `chk_ecap_value` CHECK ((capability_value >= 0));
ALTER TABLE `safety_constraint` ADD CONSTRAINT `chk_sc_period` CHECK (((active_to is null) or (active_to > active_from)));
ALTER TABLE `terminal_productivity_profile` ADD CONSTRAINT `chk_tpp_period` CHECK (((valid_to is null) or (valid_to >= valid_from)));
ALTER TABLE `terminal_productivity_profile` ADD CONSTRAINT `chk_tpp_unit` CHECK (((terminal_type = 'CONTAINER') = (rate_unit = 'MOVES_PER_HOUR')));
ALTER TABLE `terminal_productivity_profile` ADD CONSTRAINT `chk_tpp_values` CHECK (((productivity_rate > 0) and (fixed_overhead_minutes >= 0)));
ALTER TABLE `berthing_request` ADD CONSTRAINT `chk_br_cb` CHECK ((block_coefficient > 0.3) and (block_coefficient < 1.0));
ALTER TABLE `berthing_request` ADD CONSTRAINT `chk_br_tonnage` CHECK ((gross_tonnage > 0) and (deadweight_tonnes > 0));
ALTER TABLE `berthing_request` ADD CONSTRAINT `chk_br_source` CHECK ((source = 'EXTERNAL' AND external_system_id IS NOT NULL AND external_reference IS NOT NULL) OR (source = 'MANUAL' AND submitted_by IS NOT NULL AND (external_system_id IS NULL) = (external_reference IS NULL)));
ALTER TABLE `berthing_request` ADD CONSTRAINT `chk_br_departure_draft` CHECK (departure_draft IS NULL OR departure_draft > 0);
ALTER TABLE `berthing_request` ADD CONSTRAINT `chk_br_ports` CHECK ((previous_port IS NULL OR regexp_like(previous_port, '^[A-Z]{2}[A-Z2-9]{3}$')) AND (next_port IS NULL OR regexp_like(next_port, '^[A-Z]{2}[A-Z2-9]{3}$')));
ALTER TABLE `berthing_request_cargo` ADD CONSTRAINT `chk_brc_quantity` CHECK ((quantity > 0));
ALTER TABLE `berthing_request_status_history` ADD CONSTRAINT `chk_brsh_change` CHECK (((from_status is null) or (from_status <> to_status)));
ALTER TABLE `baplie_file` ADD CONSTRAINT `chk_bf_hash` CHECK (regexp_like(file_hash_sha256, '^[0-9a-f]{64}$', 'c'));
ALTER TABLE `baplie_file` ADD CONSTRAINT `chk_bf_correction` CHECK ((is_manually_corrected = false) OR (corrected_by IS NOT NULL AND corrected_at IS NOT NULL));
ALTER TABLE `container_manifest_entry` ADD CONSTRAINT `chk_cme_oog` CHECK ((((oog_over_front_cm is null) or (oog_over_front_cm > 0)) and ((oog_over_back_cm is null) or (oog_over_back_cm > 0)) and ((oog_over_right_cm is null) or (oog_over_right_cm > 0)) and ((oog_over_left_cm is null) or (oog_over_left_cm > 0)) and ((oog_over_height_cm is null) or (oog_over_height_cm > 0))));
ALTER TABLE `container_manifest_entry` ADD CONSTRAINT `chk_cme_pod` CHECK (((port_of_discharge is null) or regexp_like(port_of_discharge,'^[A-Z]{2}[A-Z2-9]{3}$','c')));
ALTER TABLE `container_manifest_entry` ADD CONSTRAINT `chk_cme_pol` CHECK (((port_of_load is null) or regexp_like(port_of_load,'^[A-Z]{2}[A-Z2-9]{3}$','c')));
ALTER TABLE `container_manifest_entry` ADD CONSTRAINT `chk_cme_reefer` CHECK (((reefer_set_temperature is null) or (reefer_set_temperature between -(40) and 40)));
ALTER TABLE `container_manifest_entry` ADD CONSTRAINT `chk_cme_stowage` CHECK ((((stowage_bay is null) or (stowage_bay > 0)) and ((stowage_row is null) or (stowage_row >= 0)) and ((stowage_tier is null) or (stowage_tier > 0))));
ALTER TABLE `container_manifest_entry` ADD CONSTRAINT `chk_cme_weight` CHECK (((weight_kg is null) or (weight_kg > 0)));
ALTER TABLE `environment_forecast_point` ADD CONSTRAINT `chk_efp_direction` CHECK ((((wind_direction_deg is null) or ((wind_direction_deg >= 0) and (wind_direction_deg < 360))) and ((wave_direction_deg is null) or ((wave_direction_deg >= 0) and (wave_direction_deg < 360)))));
ALTER TABLE `environment_forecast_point` ADD CONSTRAINT `chk_efp_values` CHECK ((((wind_speed is null) or (wind_speed >= 0)) and ((wind_gust is null) or (wind_gust >= 0)) and ((visibility is null) or (visibility >= 0)) and ((wave_height is null) or (wave_height >= 0)) and ((wave_period is null) or (wave_period >= 0)) and ((current_speed is null) or (current_speed >= 0))));
ALTER TABLE `forecast_run` ADD CONSTRAINT `chk_fr_horizon` CHECK (((horizon_start is null) or (horizon_end is null) or (horizon_end > horizon_start)));
ALTER TABLE `tide_constituent` ADD CONSTRAINT `chk_tc_values` CHECK (((amplitude >= 0) and (phase_lag >= 0) and (phase_lag < 360) and (speed_deg_per_hour > 0)));
ALTER TABLE `tide_prediction_run` ADD CONSTRAINT `chk_tpr_horizon` CHECK ((horizon_end > horizon_start));
ALTER TABLE `ml_model_version` ADD CONSTRAINT `chk_mlv_champion_deployed` CHECK (((status <> 'CHAMPION') or (deployed_at is not null)));
ALTER TABLE `ml_model_version` ADD CONSTRAINT `chk_mlv_version` CHECK (((version_no > 0) and ((training_row_count is null) or (training_row_count >= 0))));
ALTER TABLE `ml_model_version` ADD CONSTRAINT `chk_mlv_window` CHECK (((training_window_start is null) or (training_window_end is null) or (training_window_end > training_window_start)));
ALTER TABLE `blocked_period` ADD CONSTRAINT `chk_bp_period` CHECK ((period_end > period_start));
ALTER TABLE `demand_forecast_slot` ADD CONSTRAINT `chk_dfs_method` CHECK (((method = 'LIGHTGBM') = (model_version_id is not null)));
ALTER TABLE `demand_forecast_slot` ADD CONSTRAINT `chk_dfs_values` CHECK (((slot_end > slot_start) and (expected_arrivals >= 0) and (congestion_score between 0 and 1)));
ALTER TABLE `entry_window_proposal` ADD CONSTRAINT `chk_ewp_confirm_set` CHECK ((((confirmed_window_id is null) = (confirmed_by is null)) and ((confirmed_by is null) = (confirmed_at is null))));
ALTER TABLE `entry_window_proposal` ADD CONSTRAINT `chk_ewp_confirmed` CHECK (((status <> 'CONFIRMED') or (confirmed_window_id is not null)));
ALTER TABLE `entry_window_proposal` ADD CONSTRAINT `chk_ewp_score` CHECK (((peak_congestion_score is null) or (peak_congestion_score between 0 and 1)));
ALTER TABLE `feasible_window` ADD CONSTRAINT `chk_fw_values` CHECK (((window_end > window_start) and ((confidence is null) or (confidence between 0 and 1))));
ALTER TABLE `rationale_template` ADD CONSTRAINT `chk_rt_version` CHECK ((version > 0));
ALTER TABLE `recommendation_option` ADD CONSTRAINT `chk_ro_p90` CHECK ((((service_time_method = 'ML') = (service_time_p90_hours is not null)) and ((service_time_p90_hours is null) or (service_time_p90_hours >= service_time_p50_hours))));
ALTER TABLE `recommendation_option` ADD CONSTRAINT `chk_ro_values` CHECK (((option_rank > 0) and (planned_end > planned_start) and (service_time_p50_hours > 0) and ((delay_risk is null) or (delay_risk between 0 and 1))));
ALTER TABLE `solver_run` ADD CONSTRAINT `chk_sr_finished` CHECK (((status in ('QUEUED','RUNNING')) or (finished_at is not null)));
ALTER TABLE `solver_run` ADD CONSTRAINT `chk_sr_horizon` CHECK ((horizon_end > horizon_start));
ALTER TABLE `solver_run` ADD CONSTRAINT `chk_sr_manual` CHECK (((trigger_type <> 'MANUAL') or (triggered_by is not null)));
ALTER TABLE `solver_run` ADD CONSTRAINT `chk_sr_time` CHECK (((solve_time_ms is null) or (solve_time_ms >= 0)));
ALTER TABLE `recommendation_override` ADD CONSTRAINT `chk_rov_berth` CHECK ((((component_type = 'BERTH') = (override_berth_id is not null)) and ((component_type = 'BERTH') = (original_berth_id is not null))));
ALTER TABLE `recommendation_override` ADD CONSTRAINT `chk_rov_pilot` CHECK (((component_type = 'PILOT') = ((original_pilot_user_id is not null) or (override_pilot_user_id is not null))));
ALTER TABLE `recommendation_override` ADD CONSTRAINT `chk_rov_time` CHECK ((((component_type = 'START_TIME') = (override_start is not null)) and ((component_type = 'START_TIME') = (original_start is not null))));
ALTER TABLE `recommendation_override` ADD CONSTRAINT `chk_rov_tug` CHECK (((component_type = 'TUG') = ((original_tugboat_id is not null) or (override_tugboat_id is not null))));
ALTER TABLE `recommendation_rationale_factor` ADD CONSTRAINT `chk_rrf_kind` CHECK ((((factor_type = 'CONSTRAINT') and (constraint_type_id is not null) and (objective_term_id is null)) or ((factor_type = 'OBJECTIVE_TERM') and (objective_term_id is not null) and (constraint_type_id is null))));
ALTER TABLE `storage_allocation_item` ADD CONSTRAINT `chk_sai_quantity` CHECK ((quantity > 0));
ALTER TABLE `storage_allocation_item` ADD CONSTRAINT `chk_sai_type` CHECK ((rec_type = 'STORAGE_ALLOCATION'));
ALTER TABLE `terminal_recommendation` ADD CONSTRAINT `chk_tr_decision` CHECK ((((decision = 'PENDING') = (decided_by is null)) and ((decided_by is null) = (decided_at is null))));
ALTER TABLE `terminal_recommendation` ADD CONSTRAINT `chk_tr_type_terminal` CHECK ((((rec_type <> 'YARD_PLACEMENT') or (terminal_type = 'CONTAINER')) and ((rec_type <> 'STORAGE_ALLOCATION') or (terminal_type = 'NON_CONTAINER'))));
ALTER TABLE `terminal_recommendation` ADD CONSTRAINT `chk_tr_window` CHECK ((((rec_type = 'TIMING') = ((window_start is not null) and (window_end is not null))) and ((window_start is null) or (window_end > window_start))));
ALTER TABLE `yard_placement_item` ADD CONSTRAINT `chk_ypi_type` CHECK ((rec_type = 'YARD_PLACEMENT'));
ALTER TABLE `plan_component` ADD CONSTRAINT `chk_pc_assigned` CHECK (((assigned_user_id is null) or (assigned_at is not null)));
ALTER TABLE `plan_component` ADD CONSTRAINT `chk_pc_resource` CHECK ((((component_type = 'BERTH') and (berth_id is not null) and (tugboat_id is null) and (terminal_id is null)) or ((component_type = 'TUG') and (berth_id is null) and (tugboat_id is not null) and (terminal_id is null)) or ((component_type = 'TERMINAL_OPS') and (berth_id is null) and (tugboat_id is null) and (terminal_id is not null)) or ((component_type = 'PILOT') and (berth_id is null) and (tugboat_id is null) and (terminal_id is null))));
ALTER TABLE `plan_component` ADD CONSTRAINT `chk_pc_time` CHECK ((planned_end > planned_start));
ALTER TABLE `drift_measurement` ADD CONSTRAINT `chk_dm_feature` CHECK (((metric_type = 'PSI') = (feature_name is not null)));
ALTER TABLE `evaluation_metric` ADD CONSTRAINT `chk_em_period` CHECK ((period_end > period_start));
ALTER TABLE `evaluation_metric` ADD CONSTRAINT `chk_em_scope` CHECK ((((metric_name <> 'MAE') or (model_version_id is not null)) and ((metric_name not in ('OVERRIDE_RATE','WAIT_TIME')) or (model_version_id is null))));
ALTER TABLE `retraining_run` ADD CONSTRAINT `chk_rr_review` CHECK (((reviewed_by is null) = (reviewed_at is null)));
ALTER TABLE `retraining_run` ADD CONSTRAINT `chk_rr_time` CHECK (((finished_at is null) or (finished_at >= started_at)));
ALTER TABLE `retraining_run` ADD CONSTRAINT `chk_rr_versions` CHECK (((champion_version_id is null) or (champion_version_id <> challenger_version_id)));
ALTER TABLE `audit_log` ADD CONSTRAINT `chk_al_states` CHECK ((((action <> 'CREATE') or (before_state is null)) and ((action <> 'DELETE') or (after_state is null))));
ALTER TABLE `notification_log` ADD CONSTRAINT `chk_nl_ack` CHECK (((status = 'ACKNOWLEDGED') = (acknowledged_at is not null)));
ALTER TABLE `notification_log` ADD CONSTRAINT `chk_nl_failed` CHECK (((status = 'FAILED') = (failure_reason is not null)));

-- 113 CHECK statements above

-- ============================================================
-- PART 2. Generated columns (the DDL has them as plain columns)
-- Pattern: drop the plain column (and its unique index, named like the column), add the generated one.
-- ============================================================

-- organization_unit.single_key
ALTER TABLE `organization_unit` DROP INDEX `uq_org_unit_type_key`, DROP COLUMN `single_key`;
ALTER TABLE `organization_unit`
  ADD COLUMN `single_key` bigint GENERATED ALWAYS AS (COALESCE(`terminal_id`, 0)) STORED,
  ADD UNIQUE KEY `uq_org_unit_type_key` (`unit_type`, `single_key`);

-- container_size_type.is_reefer
ALTER TABLE `container_size_type` DROP COLUMN `is_reefer`;
ALTER TABLE `container_size_type`
  ADD COLUMN `is_reefer` tinyint(1) GENERATED ALWAYS AS (`type_group` = 'REEFER') STORED;

-- storage_facility.available_capacity
ALTER TABLE `storage_facility` DROP COLUMN `available_capacity`;
ALTER TABLE `storage_facility`
  ADD COLUMN `available_capacity` decimal(14,2)
    GENERATED ALWAYS AS (`capacity` - `current_occupancy` - `reserved_capacity` - `blocked_capacity`) STORED;

-- equipment.available_quantity
ALTER TABLE `equipment` DROP COLUMN `available_quantity`;
ALTER TABLE `equipment`
  ADD COLUMN `available_quantity` int
    GENERATED ALWAYS AS (`quantity` - `maintenance_quantity` - `blocked_quantity`) STORED;

-- baplie_file.active_request_key (one ACTIVE file per request)
ALTER TABLE `baplie_file` DROP INDEX `active_request_key`, DROP COLUMN `active_request_key`;
ALTER TABLE `baplie_file`
  ADD COLUMN `active_request_key` bigint
    GENERATED ALWAYS AS (IF(`status` = 'ACTIVE', `berthing_request_id`, NULL)) STORED,
  ADD UNIQUE KEY `active_request_key` (`active_request_key`);

-- tide_constituent_set.active_key (one active set)
ALTER TABLE `tide_constituent_set` DROP INDEX `active_key`, DROP COLUMN `active_key`;
ALTER TABLE `tide_constituent_set`
  ADD COLUMN `active_key` tinyint GENERATED ALWAYS AS (IF(`is_active`, 1, NULL)) STORED,
  ADD UNIQUE KEY `active_key` (`active_key`);

-- ml_model_version.champion_key (one CHAMPION per model)
ALTER TABLE `ml_model_version` DROP INDEX `champion_key`, DROP COLUMN `champion_key`;
ALTER TABLE `ml_model_version`
  ADD COLUMN `champion_key` bigint GENERATED ALWAYS AS (IF(`status` = 'CHAMPION', `ml_model_id`, NULL)) STORED,
  ADD UNIQUE KEY `champion_key` (`champion_key`);

-- entry_window_proposal.confirmed_key (one CONFIRMED proposal per request)
ALTER TABLE `entry_window_proposal` DROP INDEX `confirmed_key`, DROP COLUMN `confirmed_key`;
ALTER TABLE `entry_window_proposal`
  ADD COLUMN `confirmed_key` bigint
    GENERATED ALWAYS AS (IF(`status` = 'CONFIRMED', `berthing_request_id`, NULL)) STORED,
  ADD UNIQUE KEY `confirmed_key` (`confirmed_key`);

-- rationale_template.active_code (one active version per code)
ALTER TABLE `rationale_template` DROP INDEX `active_code`, DROP COLUMN `active_code`;
ALTER TABLE `rationale_template`
  ADD COLUMN `active_code` varchar(60) GENERATED ALWAYS AS (IF(`is_active`, `code`, NULL)) STORED,
  ADD UNIQUE KEY `active_code` (`active_code`);

-- operational_plan.active_key (one CONFIRMED plan per request).
-- Requires Report B item B-6 (status ENUM('CONFIRMED','CANCELLED','COMPLETED')) in the CREATE TABLE first.
ALTER TABLE `operational_plan` DROP INDEX `active_key`, DROP COLUMN `active_key`;
ALTER TABLE `operational_plan`
  ADD COLUMN `active_key` bigint GENERATED ALWAYS AS (IF(`status` = 'CONFIRMED', `berthing_request_id`, NULL)) STORED,
  ADD UNIQUE KEY `active_key` (`active_key`);

-- drift_measurement.is_drifted
ALTER TABLE `drift_measurement` DROP COLUMN `is_drifted`;
ALTER TABLE `drift_measurement`
  ADD COLUMN `is_drifted` tinyint(1) GENERATED ALWAYS AS (`metric_value` > `threshold`) STORED;

-- retraining_run.review_pending
ALTER TABLE `retraining_run` DROP COLUMN `review_pending`;
ALTER TABLE `retraining_run`
  ADD COLUMN `review_pending` tinyint(1)
    GENERATED ALWAYS AS (`decision` IN ('KEPT_CHAMPION', 'FAILED') AND `reviewed_at` IS NULL) STORED;

-- recommendation.active_key: see Report A, section 1 (Tier 1 item 4); it also adds MODIFIED_BY_STAFF.
