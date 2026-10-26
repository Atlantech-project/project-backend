# ATLANTECH PortSync — Port Deployment Database Schema

Per-port deployment database (MySQL) for PortSync: port profile and licence state, infrastructure, users and roles, vessels and berthing requests, recommendations and solver runs, storage and yard, forecasting, ML models and audit.

## Overview

| Metric | Count |
|---|---|
| Tables | 90 |
| Columns | 659 |
| Indexes | 185 |
| Foreign keys | 152 |

**Legend:** PK = primary key · AI = auto-increment · UQ = unique · FK = foreign key

## Tables

| # | Table | Description |
|---|---|---|
| 1 | [`channel_waypoint`](#channel_waypoint) |  |
| 2 | [`license_state`](#license_state) | Local copy of the SaaS licence (hybrid signed token). Public key and API credential live in config, not here. |
| 3 | [`navigation_channel`](#navigation_channel) |  |
| 4 | [`port_profile`](#port_profile) | Singleton per deployment. GM is a user_role_assignment, not a column here. |
| 5 | [`port_setting`](#port_setting) |  |
| 6 | [`berth`](#berth) |  |
| 7 | [`berth_terminal`](#berth_terminal) | One row per terminal allowed to use the berth. Normal berth = 1 row, shared berth = more. Backend: every berth has at least one row. |
| 8 | [`terminal`](#terminal) |  |
| 9 | [`organization_unit`](#organization_unit) | Where a user works: the Harbour Authority, Marine Services, or one terminal. A TERMINAL row is created with each terminal. |
| 10 | [`user`](#user) |  |
| 11 | [`user_role_assignment`](#user_role_assignment) | Who holds which role, in which organization unit. Roles are a fixed ENUM (P3-04). |
| 12 | [`pilot_profile`](#pilot_profile) | 1:1 with user. App rule: user must hold MARINE_PILOT in MARINE_SERVICES (D-29). |
| 13 | [`pilot_rank_limit`](#pilot_rank_limit) | Settings screen: one row per rank. |
| 14 | [`pilot_schedule`](#pilot_schedule) | Which pilot works which fixed shift on which day. |
| 15 | [`port_shift`](#port_shift) | Fixed port shifts, defined on the settings screen. App rule: active shifts must not overlap. |
| 16 | [`tug_master_profile`](#tug_master_profile) | 1:1 with user. App rule: user must hold TUG_MASTER in MARINE_SERVICES (D-29). |
| 17 | [`tug_master_schedule`](#tug_master_schedule) | Which tug master works which fixed shift on which day. |
| 18 | [`tugboat`](#tugboat) | Port tug fleet (shared Marine Services resource). |
| 19 | [`container`](#container) | Identity only. Per-call attributes (weight, stowage, ports) live in container_manifest_entry. |
| 20 | [`container_size_type`](#container_size_type) |  |
| 21 | [`vessel`](#vessel) | Block coefficient (Cb), GT and DWT are per call: they live on berthing_request. |
| 22 | [`vessel_type`](#vessel_type) |  |
| 23 | [`cargo_type`](#cargo_type) |  |
| 24 | [`imdg_class`](#imdg_class) | Fixed IMDG list; one source for every imdg_class column. |
| 25 | [`storage_facility`](#storage_facility) |  |
| 26 | [`storage_facility_cargo_compatibility`](#storage_facility_cargo_compatibility) |  |
| 27 | [`storage_movement`](#storage_movement) |  |
| 28 | [`storage_type`](#storage_type) |  |
| 29 | [`tank_detail`](#tank_detail) |  |
| 30 | [`terminal_cargo_type`](#terminal_cargo_type) |  |
| 31 | [`warehouse_detail`](#warehouse_detail) |  |
| 32 | [`dg_zone_allowed_class`](#dg_zone_allowed_class) |  |
| 33 | [`oog_zone_detail`](#oog_zone_detail) |  |
| 34 | [`reefer_zone_detail`](#reefer_zone_detail) |  |
| 35 | [`yard_block`](#yard_block) |  |
| 36 | [`yard_slot`](#yard_slot) |  |
| 37 | [`yard_zone`](#yard_zone) | Capacity/occupancy are derived from yard_slot. |
| 38 | [`yard_zone_type`](#yard_zone_type) |  |
| 39 | [`capability_type`](#capability_type) |  |
| 40 | [`equipment`](#equipment) |  |
| 41 | [`equipment_capability`](#equipment_capability) |  |
| 42 | [`equipment_cargo_compatibility`](#equipment_cargo_compatibility) |  |
| 43 | [`equipment_category`](#equipment_category) |  |
| 44 | [`constraint_type`](#constraint_type) |  |
| 45 | [`safety_constraint`](#safety_constraint) | Overlap of the same type + scope is rejected by the backend. Several scopes apply to one case: the STRICTEST value wins (Moustafa, OQ-P9-2). |
| 46 | [`terminal_productivity_profile`](#terminal_productivity_profile) | Inputs for the AI service-time calculation (Module 15). Overlapping periods rejected by the backend. |
| 47 | [`berthing_request`](#berthing_request) | status = current state; history in berthing_request_status_history (same transaction). Return for revision only from SUBMITTED; ENTRY_WINDOW_CONFIRMED may be reopened to CUSTOMS_APPROVED while no plan is confirmed. Cancel only before Preliminary Approval. |
| 48 | [`berthing_request_cargo`](#berthing_request_cargo) | Cargo of non-container calls (container calls use the BAPLIE manifest). |
| 49 | [`berthing_request_status_history`](#berthing_request_status_history) | CUSTOMS_APPROVED row = who/when Customs Approval was recorded (D1, D4). |
| 50 | [`external_system`](#external_system) | External adapters that send berthing requests (P10-03). |
| 51 | [`baplie_file`](#baplie_file) | Exactly one ACTIVE file per request (active_request_key). Replacing a revision: old ACTIVE -> REPLACED, new -> ACTIVE, in one transaction. Mistaken upload -> WITHDRAWN. |
| 52 | [`baplie_parse_issue`](#baplie_parse_issue) | FR-1.2.4 diagnostics. |
| 53 | [`container_manifest_entry`](#container_manifest_entry) | Per-call container data from BAPLIE. Container identity in container (Module 5). |
| 54 | [`environment_forecast_point`](#environment_forecast_point) | Raw provider values; unit conversion and derived values (e.g. surge) are AI-side (P12-02). |
| 55 | [`forecast_run`](#forecast_run) | Location = port_profile lat/lon (single port). |
| 56 | [`tide_constituent`](#tide_constituent) |  |
| 57 | [`tide_constituent_set`](#tide_constituent_set) |  |
| 58 | [`tide_prediction_point`](#tide_prediction_point) |  |
| 59 | [`tide_prediction_run`](#tide_prediction_run) | Daily in-house harmonic computation (D7, D10). |
| 60 | [`ml_model`](#ml_model) |  |
| 61 | [`ml_model_version`](#ml_model_version) |  |
| 62 | [`blocked_period`](#blocked_period) |  |
| 63 | [`demand_forecast_slot`](#demand_forecast_slot) |  |
| 64 | [`entry_window_proposal`](#entry_window_proposal) | Part 1 output (D2). Confirmed window feeds Part 2 (D3). data_versions = the three run FKs. |
| 65 | [`feasible_window`](#feasible_window) |  |
| 66 | [`rationale_template`](#rationale_template) | Fixed, versioned templates; never LLM (D11). |
| 67 | [`recommendation`](#recommendation) |  |
| 68 | [`recommendation_option`](#recommendation_option) | No is_selected column: the selected option is the one operational_plan references (backend reads it, P15-03). |
| 69 | [`recommendation_option_tug`](#recommendation_option_tug) | Number of tugs decided by the AI formula. |
| 70 | [`solver_run`](#solver_run) | Port-wide joint solve (FR-1.4.1). INFEASIBLE is final; FIFO fallback only on technical failure (recommendation.source = FIFO). |
| 71 | [`solver_run_model_version`](#solver_run_model_version) |  |
| 72 | [`objective_term`](#objective_term) | CP-SAT objective terms (P16-02). Codes agreed with the AI team. |
| 73 | [`recommendation_override`](#recommendation_override) | Typed override of a recommendation option (FR-1.4.7). Signal for Part 3, not an automatic retrain trigger (D12). |
| 74 | [`recommendation_rationale_factor`](#recommendation_rationale_factor) | Constraints/objective terms that actually drove the solve (FR-1.4.10). |
| 75 | [`storage_allocation_item`](#storage_allocation_item) | On ACCEPTED, backend adds quantity to storage_facility.reserved_capacity (D-46). |
| 76 | [`terminal_recommendation`](#terminal_recommendation) | Common terminal output contract (FR-3.2.2). Visible to that terminal only (§42). |
| 77 | [`yard_placement_item`](#yard_placement_item) |  |
| 78 | [`operational_plan`](#operational_plan) |  |
| 79 | [`plan_component`](#plan_component) | One row per dispatched unit (one per tug). Berth/tug occupancy = RESERVED rows overlapping a window (D-20). |
| 80 | [`actual_status_update`](#actual_status_update) |  |
| 81 | [`deviation_log`](#deviation_log) | P19-03 deferred (2026-10-08): kept as in source for now. |
| 82 | [`status_type`](#status_type) | Allowed field statuses per component type (P19-01). New status = INSERT, not ALTER. |
| 83 | [`drift_measurement`](#drift_measurement) |  |
| 84 | [`evaluation_metric`](#evaluation_metric) |  |
| 85 | [`retraining_run`](#retraining_run) | Champion vs Challenger history. Hard safety limits are never changed by retraining (D12). |
| 86 | [`audit_log`](#audit_log) | Append-only: the application DB user gets INSERT + SELECT only on this table (P21-03). |
| 87 | [`notification_log`](#notification_log) |  |
| 88 | [`user_device`](#user_device) |  |
| 89 | [`part1_run`](#part1_run) |  |
| 90 | [`recommendation_review`](#recommendation_review) |  |

---

## `channel_waypoint`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `channel_id` | `bigint` | NO |  | FK → `navigation_channel.id` |  |
| `sequence_order` | `int` | NO |  |  |  |
| `label` | `varchar(60)` | NO |  |  | Waypoint name entered by admin, e.g. Fairway Buoy |
| `latitude` | `decimal(9,6)` | NO |  |  |  |
| `longitude` | `decimal(9,6)` | NO |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_waypoint_lat` | `(latitude between -(90) and 90)` |
| `chk_waypoint_lon` | `(longitude between -(180) and 180)` |
| `chk_waypoint_order` | `(sequence_order > 0)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_channel_waypoint_label` | Yes | channel_id, label |
| `uq_channel_waypoint_order` | Yes | channel_id, sequence_order |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_channel_waypoint_channel` | channel_id | [`navigation_channel`](#navigation_channel) (id) |  |

---

## `license_state`

> Local copy of the SaaS licence (hybrid signed token). Public key and API credential live in config, not here.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `tinyint` | NO | `1` | PK |  |
| `token` | `text` | YES |  |  | Current signed licence token |
| `license_ref` | `bigint` | YES |  |  | SaaS license.id (no FK, other DB) |
| `plan_name` | `varchar(80)` | YES |  |  |  |
| `max_terminals` | `int` | YES |  |  |  |
| `max_berths` | `int` | YES |  |  |  |
| `max_users` | `int` | YES |  |  |  |
| `license_expires_at` | `datetime` | YES |  |  |  |
| `token_expires_at` | `datetime` | YES |  |  |  |
| `port_status` | `ENUM(ONBOARDING, ACTIVE, SUSPENDED, OFFBOARDED)` | YES |  |  |  |
| `license_status` | `ENUM(PENDING, ACTIVE, SUSPENDED, EXPIRED, REVOKED)` | YES |  |  |  |
| `status_received_at` | `datetime` | YES |  |  |  |
| `last_check_in_attempt_at` | `datetime` | YES |  |  |  |
| `last_successful_check_in_at` | `datetime` | YES |  |  |  |
| `last_check_in_error` | `varchar(255)` | YES |  |  |  |
| `max_seen_time` | `datetime` | YES |  |  | Clock-rollback guard |
| `mode` | `ENUM(SETUP, FULL, READ_ONLY)` | NO | `'READ_ONLY'` |  |  |
| `read_only_reason` | `ENUM(NONE, NO_LICENSE, TOKEN_EXPIRED, SUSPENDED, REVOKED, INVALID_SIGNATURE, CLOCK_ROLLBACK, PORT_SUSPENDED, PORT_OFFBOARDED, LICENSE_NOT_YET_IN_EFFECT)` | NO | `'NO_LICENSE'` |  |  |
| `updated_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_license_state_reason` | `((mode IN ('FULL','SETUP')) = (read_only_reason = 'NONE'))` |
| `chk_license_state_singleton` | `(id = 1)` |

---

## `navigation_channel`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `name` | `varchar(100)` | NO |  | UQ |  |
| `depth` | `decimal(6,2)` | NO |  |  | Controlling (minimum) charted depth, metres â€” input to UKC |
| `depth_datum` | `varchar(40)` | NO |  |  | e.g. LAT; must equal the active tide_constituent_set.datum_reference |
| `width` | `decimal(7,2)` | NO |  |  | metres â€” input to squat |
| `speed_limit_knots` | `decimal(4,1)` | NO |  |  | Max permitted transit speed, entered by admin; worst-case input to squat |
| `is_active` | `tinyint(1)` | NO | `1` |  |  |
| `created_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `updated_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_channel_dimensions` | `((depth > 0) and (width > 0) and (speed_limit_knots > 0))` |

**Referenced by:** [`channel_waypoint`](#channel_waypoint).channel_id

---

## `port_profile`

> Singleton per deployment. GM is a user_role_assignment, not a column here.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `tinyint` | NO | `1` | PK |  |
| `public_id` | `char(36)` | NO |  | UQ | Same value as SaaS platform_port_registry.public_id (read-only) |
| `port_code` | `varchar(10)` | YES |  |  | UN/LOCODE, from SaaS |
| `governorate_code` | `varchar(6)` | NO |  |  | ISO 3166-2:EG, same codes as SaaS governorate |
| `name` | `varchar(150)` | NO |  |  |  |
| `latitude` | `decimal(9,6)` | NO |  |  | Reference point for weather/tide pulls |
| `longitude` | `decimal(9,6)` | NO |  |  |  |
| `timezone` | `varchar(64)` | NO |  |  | IANA, e.g. Africa/Cairo |
| `boundary_coordinates` | `json` | YES |  |  | GeoJSON Polygon, [lon, lat] |
| `created_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `updated_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_port_profile_lat` | `(latitude between -(90) and 90)` |
| `chk_port_profile_lon` | `(longitude between -(180) and 180)` |
| `chk_port_profile_singleton` | `(id = 1)` |

---

## `port_setting`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `setting_key` | `varchar(80)` | NO |  | UQ | reminder_lead_minutes, weather_pull_hours, retrain_interval_days ... |
| `setting_value` | `varchar(255)` | NO |  |  |  |
| `value_type` | `ENUM(INT, DECIMAL, BOOL, STRING, JSON)` | NO |  |  |  |
| `description` | `varchar(255)` | YES |  |  |  |
| `updated_by` | `bigint` | YES |  | FK → `user.id` |  |
| `updated_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_port_setting_updated_by` | No | updated_by |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_port_setting_updated_by` | updated_by | [`user`](#user) (id) |  |

---

## `berth`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(30)` | NO |  | UQ |  |
| `name` | `varchar(80)` | YES |  |  |  |
| `length` | `decimal(7,2)` | NO |  |  | metres |
| `water_depth` | `decimal(6,2)` | NO |  |  | Water depth alongside the berth (m) below depth_datum |
| `depth_datum` | `varchar(40)` | NO |  |  | e.g. LAT; must equal navigation_channel.depth_datum and the active tide datum |
| `max_vessel_loa` | `decimal(7,2)` | NO |  |  | metres |
| `status` | `ENUM(ACTIVE, MAINTENANCE, INACTIVE)` | NO | `'ACTIVE'` |  | Administrative only. Reserved/occupied is derived from plan_component and actual status |
| `latitude` | `decimal(9,6)` | YES |  |  |  |
| `longitude` | `decimal(9,6)` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_berth_dimensions` | `((length > 0) and (water_depth > 0) and (max_vessel_loa > 0))` |
| `chk_berth_lat` | `((latitude is null) or (latitude between -(90) and 90))` |
| `chk_berth_loa_fits` | `(max_vessel_loa <= length)` |
| `chk_berth_lon` | `((longitude is null) or (longitude between -(180) and 180))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `idx_berth_status` | No | status |

**Referenced by:** [`berth_terminal`](#berth_terminal).berth_id, [`safety_constraint`](#safety_constraint).berth_id, [`recommendation_override`](#recommendation_override).override_berth_id, [`recommendation_override`](#recommendation_override).original_berth_id, [`plan_component`](#plan_component).berth_id

---

## `berth_terminal`

> One row per terminal allowed to use the berth. Normal berth = 1 row, shared berth = more. Backend: every berth has at least one row.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `berth_id` | `bigint` | NO |  | PK · FK → `berth.id` |  |
| `terminal_id` | `bigint` | NO |  | PK · FK → `terminal.id` |  |

**Composite primary key:** `berth_id`, `terminal_id`

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `idx_bt_terminal` | No | terminal_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_bt_berth` | berth_id | [`berth`](#berth) (id) |  |
| `fk_bt_terminal` | terminal_id | [`terminal`](#terminal) (id) |  |

**Referenced by:** [`safety_constraint`](#safety_constraint).berth_id, terminal_id, [`recommendation_option`](#recommendation_option).berth_id, terminal_id

---

## `terminal`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(20)` | NO |  | UQ |  |
| `name` | `varchar(120)` | NO |  | UQ |  |
| `terminal_type` | `ENUM(CONTAINER, NON_CONTAINER)` | NO |  |  |  |
| `boundary_coordinates` | `json` | YES |  |  | GeoJSON Polygon [lon, lat]; inside port boundary (app rule) |
| `is_active` | `tinyint(1)` | NO | `1` |  | All registered terminals (active or not) count against license max_terminals |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_terminal_id_type` | Yes | id, terminal_type |

**Referenced by:** [`berth_terminal`](#berth_terminal).terminal_id, [`organization_unit`](#organization_unit).terminal_id, [`storage_facility`](#storage_facility).terminal_id, terminal_type, [`terminal_cargo_type`](#terminal_cargo_type).terminal_id, [`equipment`](#equipment).terminal_id, [`safety_constraint`](#safety_constraint).terminal_id, [`terminal_productivity_profile`](#terminal_productivity_profile).terminal_id, terminal_type, [`berthing_request`](#berthing_request).requested_terminal_id, [`terminal_recommendation`](#terminal_recommendation).terminal_id, terminal_type, [`plan_component`](#plan_component).terminal_id

---

## `organization_unit`

> Where a user works: the Harbour Authority, Marine Services, or one terminal. A TERMINAL row is created with each terminal.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `unit_type` | `ENUM(HARBOUR_AUTHORITY, MARINE_SERVICES, TERMINAL)` | NO |  |  |  |
| `terminal_id` | `bigint` | YES |  | FK → `terminal.id` | Set only for TERMINAL units; display name comes from terminal.name |
| `single_key` | `bigint` | YES |  |  | GENERATED (stored): coalesce(terminal_id,0) |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_org_unit_terminal` | `((unit_type = 'TERMINAL') = (terminal_id is not null))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_org_unit_terminal` | No | terminal_id |
| `uq_org_unit_id_type` | Yes | id, unit_type |
| `uq_org_unit_type_key` | Yes | unit_type, single_key |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_org_unit_terminal` | terminal_id | [`terminal`](#terminal) (id) |  |

**Referenced by:** [`user_role_assignment`](#user_role_assignment).organization_unit_id, unit_type

---

## `user`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `name` | `varchar(120)` | NO |  |  |  |
| `username` | `varchar(50)` | NO |  | UQ | Login name, stored lowercase |
| `password_hash` | `varchar(255)` | NO |  |  |  |
| `phone` | `varchar(30)` | YES |  |  | Contact only, not used for login |
| `is_active` | `tinyint(1)` | NO | `1` |  | Active users count against license max_users |
| `must_change_password` | `boolean` | NO | `false` |  | 1 = one-time password in use; must change before any other action |
| `created_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_user_username_lower` | `((username = lower(username)) and (char_length(username) >= 3))` |

**Referenced by:** [`user_device`](#user_device).user_id, [`recommendation_review`](#recommendation_review).user_id, [`port_setting`](#port_setting).updated_by, [`user_role_assignment`](#user_role_assignment).user_id, [`pilot_profile`](#pilot_profile).user_id, [`pilot_schedule`](#pilot_schedule).created_by, [`tug_master_profile`](#tug_master_profile).user_id, [`tug_master_schedule`](#tug_master_schedule).created_by, [`storage_movement`](#storage_movement).recorded_by, [`safety_constraint`](#safety_constraint).created_by, [`berthing_request`](#berthing_request).submitted_by, [`berthing_request_status_history`](#berthing_request_status_history).changed_by, [`baplie_file`](#baplie_file).uploaded_by, [`entry_window_proposal`](#entry_window_proposal).confirmed_by, [`solver_run`](#solver_run).triggered_by, [`recommendation_override`](#recommendation_override).overridden_by, [`terminal_recommendation`](#terminal_recommendation).decided_by, [`operational_plan`](#operational_plan).confirmed_by, [`plan_component`](#plan_component).assigned_user_id, [`plan_component`](#plan_component).assigned_by, [`actual_status_update`](#actual_status_update).reported_by, [`retraining_run`](#retraining_run).reviewed_by, [`audit_log`](#audit_log).actor_id, [`notification_log`](#notification_log).user_id, [`baplie_file`](#baplie_file).corrected_by, [`entry_window_proposal`](#entry_window_proposal).rejected_by, [`forecast_run`](#forecast_run).submitted_by, [`terminal_recommendation`](#terminal_recommendation).exported_by

---

## `user_role_assignment`

> Who holds which role, in which organization unit. Roles are a fixed ENUM (P3-04).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `user_id` | `bigint` | NO |  | FK → `user.id` |  |
| `role` | `ENUM(PORT_GM, PORT_STAFF, MARINE_SAFETY_OFFICER, PILOTAGE_MANAGER, PILOTAGE_OFFICER, MARINE_PILOT, TUG_MANAGER, TUG_OFFICER, TUG_MASTER, TERMINAL_ADMIN, TERMINAL_OFFICER, TERMINAL_SUPERVISOR)` | NO |  |  |  |
| `organization_unit_id` | `bigint` | NO |  | FK → `organization_unit.id, unit_type` | Terminal X, Marine Services, or Harbour Authority. Never empty. |
| `unit_type` | `ENUM(HARBOUR_AUTHORITY, MARINE_SERVICES, TERMINAL)` | NO |  | FK → `organization_unit.id, unit_type` | Kept equal to organization_unit.unit_type by fk_ura_unit |
| `active_from` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `active_to` | `datetime` | YES |  |  | NULL = role active; set when the role is ended (never deleted) |
| `active_assignment_key` | `tinyint` | YES |  |  | GENERATED (stored): IF(active_to IS NULL, 1, NULL) |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ura_period` | `(active_to IS NULL OR active_to > active_from)` |
| `chk_ura_role_matches_unit` | `(((unit_type = 'HARBOUR_AUTHORITY') and (role in ('PORT_GM','PORT_STAFF','MARINE_SAFETY_OFFICER'))) or ((unit_type = 'MARINE_SERVICES') and (role in ('PILOTAGE_MANAGER','PILOTAGE_OFFICER','MARINE_PILOT','TUG_MANAGER','TUG_OFFICER','TUG_MASTER'))) or ((unit_type = 'TERMINAL') and (role in ('TERMINAL_ADMIN','TERMINAL_OFFICER','TERMINAL_SUPERVISOR'))))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_ura_unit` | No | organization_unit_id, unit_type |
| `idx_ura_unit` | No | organization_unit_id |
| `uq_ura_active` | Yes | user_id, role, organization_unit_id, active_assignment_key |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ura_unit` | organization_unit_id, unit_type | [`organization_unit`](#organization_unit) (id, unit_type) |  |
| `fk_ura_user` | user_id | [`user`](#user) (id) |  |

---

## `pilot_profile`

> 1:1 with user. App rule: user must hold MARINE_PILOT in MARINE_SERVICES (D-29).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `user_id` | `bigint` | NO |  | PK · FK → `user.id` |  |
| `pilot_rank` | `ENUM(FIRST, SECOND, THIRD)` | NO |  |  | FIRST = most senior |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_pilot_profile_user` | user_id | [`user`](#user) (id) |  |

**Referenced by:** [`pilot_schedule`](#pilot_schedule).pilot_user_id, [`recommendation_option`](#recommendation_option).pilot_user_id, [`recommendation_override`](#recommendation_override).override_pilot_user_id, [`recommendation_override`](#recommendation_override).original_pilot_user_id

---

## `pilot_rank_limit`

> Settings screen: one row per rank.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `pilot_rank` | `ENUM(FIRST, SECOND, THIRD)` | NO |  | PK |  |
| `max_vessel_loa` | `decimal(7,2)` | YES |  |  | Largest vessel LOA (m) this rank may pilot; NULL = no limit |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_pilot_rank_limit_loa` | `((max_vessel_loa is null) or (max_vessel_loa > 0))` |

---

## `pilot_schedule`

> Which pilot works which fixed shift on which day.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `pilot_user_id` | `bigint` | NO |  | FK → `pilot_profile.user_id` |  |
| `shift_date` | `date` | NO |  |  | Port local date the shift starts on |
| `port_shift_id` | `bigint` | NO |  | FK → `port_shift.id` |  |
| `created_by` | `bigint` | NO |  | FK → `user.id` |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_pilot_schedule_created_by` | No | created_by |
| `fk_pilot_schedule_shift` | No | port_shift_id |
| `idx_pilot_schedule_date` | No | shift_date, port_shift_id |
| `uq_pilot_schedule` | Yes | pilot_user_id, shift_date, port_shift_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_pilot_schedule_created_by` | created_by | [`user`](#user) (id) |  |
| `fk_pilot_schedule_pilot` | pilot_user_id | [`pilot_profile`](#pilot_profile) (user_id) |  |
| `fk_pilot_schedule_shift` | port_shift_id | [`port_shift`](#port_shift) (id) |  |

---

## `port_shift`

> Fixed port shifts, defined on the settings screen. App rule: active shifts must not overlap.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(20)` | NO |  | UQ |  |
| `name` | `varchar(60)` | NO |  |  |  |
| `start_time` | `time` | NO |  |  | Port local time (port_profile.timezone) |
| `end_time` | `time` | NO |  |  | If end_time < start_time the shift ends the next day |
| `is_active` | `tinyint(1)` | NO | `1` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_port_shift_times` | `(end_time <> start_time)` |

**Referenced by:** [`pilot_schedule`](#pilot_schedule).port_shift_id, [`tug_master_schedule`](#tug_master_schedule).port_shift_id

---

## `tug_master_profile`

> 1:1 with user. App rule: user must hold TUG_MASTER in MARINE_SERVICES (D-29).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `user_id` | `bigint` | NO |  | PK · FK → `user.id` |  |
| `assigned_tugboat_id` | `bigint` | YES |  | UQ · FK → `tugboat.id` | NULL = explicitly unassigned; UNIQUE = one master per tug (FR-1.1.2) |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_tug_master_tugboat` | assigned_tugboat_id | [`tugboat`](#tugboat) (id) |  |
| `fk_tug_master_user` | user_id | [`user`](#user) (id) |  |

**Referenced by:** [`tug_master_schedule`](#tug_master_schedule).tug_master_user_id

---

## `tug_master_schedule`

> Which tug master works which fixed shift on which day.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `tug_master_user_id` | `bigint` | NO |  | FK → `tug_master_profile.user_id` |  |
| `shift_date` | `date` | NO |  |  | Port local date the shift starts on |
| `port_shift_id` | `bigint` | NO |  | FK → `port_shift.id` |  |
| `created_by` | `bigint` | NO |  | FK → `user.id` |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_tms_created_by` | No | created_by |
| `fk_tms_shift` | No | port_shift_id |
| `idx_tug_master_schedule_date` | No | shift_date, port_shift_id |
| `uq_tug_master_schedule` | Yes | tug_master_user_id, shift_date, port_shift_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_tms_created_by` | created_by | [`user`](#user) (id) |  |
| `fk_tms_master` | tug_master_user_id | [`tug_master_profile`](#tug_master_profile) (user_id) |  |
| `fk_tms_shift` | port_shift_id | [`port_shift`](#port_shift) (id) |  |

---

## `tugboat`

> Port tug fleet (shared Marine Services resource).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `name` | `varchar(100)` | NO |  | UQ |  |
| `tug_power` | `decimal(8,2)` | NO |  |  |  |
| `tug_power_unit` | `ENUM(BOLLARD_PULL_TONNES, HP, KW)` | NO |  |  | Chosen by the Tug Manager |
| `capacity` | `decimal(8,2)` | YES |  |  | Kept as in source (P4-03 rejected) |
| `status` | `ENUM(AVAILABLE, BUSY, MAINTENANCE, INACTIVE)` | NO | `'AVAILABLE'` |  | BUSY for the plan duration; Tug Master sets it back to AVAILABLE from the tablet; Tug Officer notified if still BUSY after planned_end |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_tugboat_power` | `(tug_power > 0)` |

**Referenced by:** [`tug_master_profile`](#tug_master_profile).assigned_tugboat_id, [`recommendation_option_tug`](#recommendation_option_tug).tugboat_id, [`recommendation_override`](#recommendation_override).override_tugboat_id, [`recommendation_override`](#recommendation_override).original_tugboat_id, [`plan_component`](#plan_component).tugboat_id

---

## `container`

> Identity only. Per-call attributes (weight, stowage, ports) live in container_manifest_entry.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `container_number` | `char(11)` | NO |  | UQ | ISO 6346, uppercase, no spaces; check digit validated by parser (warning) |
| `size_type_id` | `bigint` | NO |  | FK → `container_size_type.id` |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_container_number` | `regexp_like(container_number,'^[A-Z]{3}[UJZ][0-9]{7}$','c')` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `idx_container_size_type` | No | size_type_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_container_size_type` | size_type_id | [`container_size_type`](#container_size_type) (id) |  |

**Referenced by:** [`yard_slot`](#yard_slot).container_id, [`container_manifest_entry`](#container_manifest_entry).container_id

---

## `container_size_type`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `char(4)` | NO |  | UQ | ISO 6346 size-type code, e.g. 22G1, 45R1 |
| `length_ft` | `int` | NO |  |  |  |
| `is_high_cube` | `tinyint(1)` | NO | `0` |  | 9'6" high |
| `type_group` | `ENUM(GENERAL, REEFER, TANK, OPEN_TOP, FLAT_RACK, VENTILATED, BULK)` | NO |  |  |  |
| `teu_factor` | `decimal(3,2)` | NO |  |  |  |
| `is_reefer` | `tinyint(1)` | YES |  |  | GENERATED (stored): (type_group = 'REEFER') |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_cst_length` | `(length_ft in (10,20,40,45))` |
| `chk_cst_teu` | `(teu_factor > 0)` |

**Referenced by:** [`container`](#container).size_type_id

---

## `vessel`

> Block coefficient (Cb), GT and DWT are per call: they live on berthing_request.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `imo_number` | `char(7)` | NO |  | UQ | 7 digits, no "IMO" prefix; backend validates the check digit |
| `name` | `varchar(120)` | NO |  |  |  |
| `vessel_type_id` | `bigint` | NO |  | FK → `vessel_type.id` |  |
| `loa` | `decimal(7,2)` | NO |  |  | Length overall, m |
| `beam` | `decimal(6,2)` | NO |  |  | Breadth, m. Needed for squat (blockage) |
| `max_draft` | `decimal(5,2)` | NO |  |  | Summer (max) draft, m; arrival_draft must not exceed it |
| `net_tonnage` | `int` | YES |  |  | NT. Nullable: not in BAPLIE; backend requires it at Preliminary Approval |
| `moulded_depth` | `decimal(5,2)` | YES |  |  | m. Nullable, same rule |
| `call_sign` | `varchar(10)` | YES |  |  | Nullable |
| `flag_state` | `char(2)` | YES |  |  | ISO 3166-1 alpha-2. Nullable |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_vessel_dims` | `(loa > 0) and (beam > 0) and (beam < loa) and (max_draft > 0)` |
| `chk_vessel_imo` | `regexp_like(imo_number, '^[0-9]{7}$')` |
| `chk_vessel_tonnage` | `(net_tonnage IS NULL OR net_tonnage > 0) and (moulded_depth IS NULL OR moulded_depth > 0)` |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_vessel_type` | vessel_type_id | [`vessel_type`](#vessel_type) (id) |  |

**Referenced by:** [`berthing_request`](#berthing_request).vessel_id, [`baplie_file`](#baplie_file).vessel_id

---

## `vessel_type`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(30)` | NO |  | UQ |  |
| `name` | `varchar(80)` | NO |  |  |  |

**Referenced by:** [`vessel`](#vessel).vessel_type_id, [`safety_constraint`](#safety_constraint).vessel_type_id, [`terminal_productivity_profile`](#terminal_productivity_profile).vessel_type_id

---

## `cargo_type`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(30)` | NO |  | UQ |  |
| `name` | `varchar(100)` | NO |  |  |  |
| `category` | `ENUM(GENERAL_CARGO, BREAK_BULK, DRY_BULK, LIQUID_BULK, GAS, RO_RO, CONTAINERIZED)` | NO |  |  |  |
| `imdg_class` | `varchar(3)` | YES |  | FK → `imdg_class.code` | NULL = not dangerous |
| `min_temperature` | `decimal(5,2)` | YES |  |  | Â°C; NULL = no temperature requirement |
| `max_temperature` | `decimal(5,2)` | YES |  |  | Â°C; NULL = no temperature requirement |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ct_temp` | `((min_temperature is null) or (max_temperature is null) or (min_temperature <= max_temperature))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_ct_imdg` | No | imdg_class |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ct_imdg` | imdg_class | [`imdg_class`](#imdg_class) (code) |  |

**Referenced by:** [`terminal_cargo_type`](#terminal_cargo_type).cargo_type_id

---

## `imdg_class`

> Fixed IMDG list; one source for every imdg_class column.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `code` | `varchar(3)` | NO |  | PK | IMDG class / division, e.g. 3, 2.1 |
| `name` | `varchar(80)` | NO |  |  |  |

**Referenced by:** [`cargo_type`](#cargo_type).imdg_class, [`dg_zone_allowed_class`](#dg_zone_allowed_class).imdg_class, [`safety_constraint`](#safety_constraint).imdg_class, [`container_manifest_entry`](#container_manifest_entry).imdg_class

---

## `storage_facility`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `terminal_id` | `bigint` | NO |  | FK → `terminal.id, terminal_type` |  |
| `terminal_type` | `ENUM(CONTAINER, NON_CONTAINER)` | NO |  | FK → `terminal.id, terminal_type` | Copy of terminal.terminal_type, enforced by composite FK |
| `storage_type_id` | `bigint` | NO |  | FK → `storage_type.id, detail_kind` |  |
| `detail_kind` | `ENUM(NONE, WAREHOUSE, CONTAINER_YARD, TANK)` | NO |  | FK → `storage_type.id, detail_kind` | Copy of storage_type.detail_kind, enforced by composite FK |
| `name` | `varchar(100)` | NO |  |  |  |
| `code` | `varchar(30)` | NO |  |  |  |
| `area` | `decimal(12,2)` | YES |  |  | m2 |
| `max_floor_load` | `decimal(6,2)` | YES |  |  | Ground/floor weight limit, tonnes per m2 (open yard, warehouse); NULL = not applicable |
| `capacity` | `decimal(14,2)` | YES |  |  | NULL for CONTAINER_YARD (derived from yard_slot) |
| `capacity_unit` | `ENUM(TONNES, CUBIC_METRES, SQUARE_METRES, TEU, UNITS)` | YES |  |  | NULL for CONTAINER_YARD |
| `current_occupancy` | `decimal(14,2)` | NO | `0` |  |  |
| `reserved_capacity` | `decimal(14,2)` | NO | `0` |  |  |
| `blocked_capacity` | `decimal(14,2)` | NO | `0` |  |  |
| `available_capacity` | `decimal(14,2)` | YES |  |  | GENERATED (stored): (((capacity - current_occupancy) - reserved_capacity) - blocked_capacity) |
| `status` | `ENUM(ACTIVE, MAINTENANCE, INACTIVE)` | NO | `'ACTIVE'` |  | Administrative only; fullness comes from the capacity numbers |
| `latitude` | `decimal(9,6)` | YES |  |  |  |
| `longitude` | `decimal(9,6)` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_sf_area` | `((area is null) or (area > 0))` |
| `chk_sf_capacity` | `(((capacity is null) or (capacity > 0)) and (current_occupancy >= 0) and (reserved_capacity >= 0) and (blocked_capacity >= 0))` |
| `chk_sf_capacity_sum` | `((capacity is null) or (((current_occupancy + reserved_capacity) + blocked_capacity) <= capacity))` |
| `chk_sf_floor_load` | `((max_floor_load is null) or (max_floor_load > 0))` |
| `chk_sf_lat` | `((latitude is null) or (latitude between -(90) and 90))` |
| `chk_sf_lon` | `((longitude is null) or (longitude between -(180) and 180))` |
| `chk_sf_yard_rules` | `(((detail_kind = 'CONTAINER_YARD') and (terminal_type = 'CONTAINER') and (capacity is null) and (capacity_unit is null) and (current_occupancy = 0) and (reserved_capacity = 0) and (blocked_capacity = 0)) or ((detail_kind <> 'CONTAINER_YARD') and (capacity is not null) and (capacity_unit is not null)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_sf_storage_type` | No | storage_type_id, detail_kind |
| `fk_sf_terminal` | No | terminal_id, terminal_type |
| `idx_sf_terminal_status` | No | terminal_id, status |
| `uq_sf_id_kind` | Yes | id, detail_kind |
| `uq_sf_id_terminal` | Yes | id, terminal_id |
| `uq_sf_id_unit` | Yes | id, capacity_unit |
| `uq_sf_terminal_code` | Yes | terminal_id, code |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_sf_storage_type` | storage_type_id, detail_kind | [`storage_type`](#storage_type) (id, detail_kind) |  |
| `fk_sf_terminal` | terminal_id, terminal_type | [`terminal`](#terminal) (id, terminal_type) |  |

**Referenced by:** [`storage_facility_cargo_compatibility`](#storage_facility_cargo_compatibility).storage_facility_id, terminal_id, [`tank_detail`](#tank_detail).storage_facility_id, detail_kind, [`warehouse_detail`](#warehouse_detail).storage_facility_id, detail_kind, [`yard_zone`](#yard_zone).container_yard_id, detail_kind, [`storage_allocation_item`](#storage_allocation_item).storage_facility_id, quantity_unit

---

## `storage_facility_cargo_compatibility`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `storage_facility_id` | `bigint` | NO |  | PK · FK → `storage_facility.id, terminal_id` |  |
| `terminal_id` | `bigint` | NO |  | FK → `storage_facility.id, terminal_id` |  |
| `cargo_type_id` | `bigint` | NO |  | PK · FK → `terminal_cargo_type.terminal_id, cargo_type_id` |  |

**Composite primary key:** `storage_facility_id`, `cargo_type_id`

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_sfcc_facility` | No | storage_facility_id, terminal_id |
| `fk_sfcc_terminal_cargo` | No | terminal_id, cargo_type_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_sfcc_facility` | storage_facility_id, terminal_id | [`storage_facility`](#storage_facility) (id, terminal_id) |  |
| `fk_sfcc_terminal_cargo` | terminal_id, cargo_type_id | [`terminal_cargo_type`](#terminal_cargo_type) (terminal_id, cargo_type_id) |  |

**Referenced by:** [`storage_movement`](#storage_movement).storage_facility_id, cargo_type_id, [`storage_allocation_item`](#storage_allocation_item).storage_facility_id, cargo_type_id

---

## `storage_movement`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `storage_facility_id` | `bigint` | NO |  | FK → `storage_facility_cargo_compatibility.storage_facility_id, cargo_type_id` |  |
| `cargo_type_id` | `bigint` | NO |  | FK → `storage_facility_cargo_compatibility.storage_facility_id, cargo_type_id` |  |
| `direction` | `ENUM(IN, OUT)` | NO |  |  |  |
| `quantity` | `decimal(14,2)` | NO |  |  | In the facility capacity_unit |
| `source` | `ENUM(VESSEL_DISCHARGE, VESSEL_LOADING, MANUAL)` | NO |  |  |  |
| `plan_component_id` | `bigint` | YES |  | FK → `plan_component.id` | FK added in Module 18 (D-49); NULL for MANUAL |
| `occurred_at` | `datetime` | NO |  |  | UTC |
| `recorded_by` | `bigint` | YES |  | FK → `user.id` | user.id (Module 3); NULL = recorded by the system |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_sm_quantity` | `(quantity > 0)` |
| `chk_sm_source_direction` | `(((source = 'VESSEL_DISCHARGE') and (direction = 'IN')) or ((source = 'VESSEL_LOADING') and (direction = 'OUT')) or (source = 'MANUAL'))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_sm_compatible` | No | storage_facility_id, cargo_type_id |
| `fk_sm_plan_component` | No | plan_component_id |
| `fk_sm_user` | No | recorded_by |
| `idx_sm_facility_time` | No | storage_facility_id, occurred_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_sm_compatible` | storage_facility_id, cargo_type_id | [`storage_facility_cargo_compatibility`](#storage_facility_cargo_compatibility) (storage_facility_id, cargo_type_id) |  |
| `fk_sm_plan_component` | plan_component_id | [`plan_component`](#plan_component) (id) |  |
| `fk_sm_user` | recorded_by | [`user`](#user) (id) |  |

---

## `storage_type`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(30)` | NO |  | UQ |  |
| `name` | `varchar(80)` | NO |  |  |  |
| `detail_kind` | `ENUM(NONE, WAREHOUSE, CONTAINER_YARD, TANK)` | NO | `'NONE'` |  | Which detail table facilities of this type may use |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_storage_type_id_kind` | Yes | id, detail_kind |

**Referenced by:** [`storage_facility`](#storage_facility).storage_type_id, detail_kind

---

## `tank_detail`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `storage_facility_id` | `bigint` | NO |  | PK · FK → `storage_facility.id, detail_kind` |  |
| `detail_kind` | `ENUM(NONE, WAREHOUSE, CONTAINER_YARD, TANK)` | NO | `'TANK'` | FK → `storage_facility.id, detail_kind` | Same ENUM as parent (FK compares ENUM index) |
| `min_temperature` | `decimal(5,2)` | YES |  |  | Â°C |
| `max_temperature` | `decimal(5,2)` | YES |  |  | Â°C |
| `max_pressure_bar` | `decimal(6,2)` | YES |  |  | Pressure limit (bar); NULL = atmospheric tank |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_td_kind` | `(detail_kind = 'TANK')` |
| `chk_td_pressure` | `((max_pressure_bar is null) or (max_pressure_bar > 0))` |
| `chk_td_temp` | `((min_temperature is null) or (max_temperature is null) or (min_temperature <= max_temperature))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_td_facility` | No | storage_facility_id, detail_kind |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_td_facility` | storage_facility_id, detail_kind | [`storage_facility`](#storage_facility) (id, detail_kind) |  |

---

## `terminal_cargo_type`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `terminal_id` | `bigint` | NO |  | PK · FK → `terminal.id` |  |
| `cargo_type_id` | `bigint` | NO |  | PK · FK → `cargo_type.id` |  |

**Composite primary key:** `terminal_id`, `cargo_type_id`

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_tct_cargo_type` | No | cargo_type_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_tct_cargo_type` | cargo_type_id | [`cargo_type`](#cargo_type) (id) |  |
| `fk_tct_terminal` | terminal_id | [`terminal`](#terminal) (id) |  |

**Referenced by:** [`storage_facility_cargo_compatibility`](#storage_facility_cargo_compatibility).terminal_id, cargo_type_id, [`equipment_cargo_compatibility`](#equipment_cargo_compatibility).terminal_id, cargo_type_id, [`berthing_request_cargo`](#berthing_request_cargo).terminal_id, cargo_type_id

---

## `warehouse_detail`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `storage_facility_id` | `bigint` | NO |  | PK · FK → `storage_facility.id, detail_kind` |  |
| `detail_kind` | `ENUM(NONE, WAREHOUSE, CONTAINER_YARD, TANK)` | NO | `'WAREHOUSE'` | FK → `storage_facility.id, detail_kind` | Same ENUM as parent (FK compares ENUM index) |
| `min_temperature` | `decimal(5,2)` | YES |  |  | Â°C |
| `max_temperature` | `decimal(5,2)` | YES |  |  | Â°C |
| `temperature_monitoring` | `tinyint(1)` | NO | `0` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_wd_kind` | `(detail_kind = 'WAREHOUSE')` |
| `chk_wd_temp` | `((min_temperature is null) or (max_temperature is null) or (min_temperature <= max_temperature))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_wd_facility` | No | storage_facility_id, detail_kind |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_wd_facility` | storage_facility_id, detail_kind | [`storage_facility`](#storage_facility) (id, detail_kind) |  |

---

## `dg_zone_allowed_class`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `yard_zone_id` | `bigint` | NO |  | PK · FK → `yard_zone.id, zone_detail` |  |
| `zone_detail` | `ENUM(NONE, REEFER, DANGEROUS_GOODS, OOG)` | NO | `'DANGEROUS_GOODS'` | FK → `yard_zone.id, zone_detail` |  |
| `imdg_class` | `varchar(3)` | NO |  | PK · FK → `imdg_class.code` |  |

**Composite primary key:** `yard_zone_id`, `imdg_class`

**Check constraints**

| Name | Rule |
|---|---|
| `chk_dzac_type` | `(zone_detail = 'DANGEROUS_GOODS')` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_dzac_imdg` | No | imdg_class |
| `fk_dzac_zone` | No | yard_zone_id, zone_detail |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_dzac_imdg` | imdg_class | [`imdg_class`](#imdg_class) (code) |  |
| `fk_dzac_zone` | yard_zone_id, zone_detail | [`yard_zone`](#yard_zone) (id, zone_detail) |  |

---

## `oog_zone_detail`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `yard_zone_id` | `bigint` | NO |  | PK · FK → `yard_zone.id, zone_detail` |  |
| `zone_detail` | `ENUM(NONE, REEFER, DANGEROUS_GOODS, OOG)` | NO | `'OOG'` | FK → `yard_zone.id, zone_detail` |  |
| `max_length` | `decimal(6,2)` | YES |  |  | m |
| `max_width` | `decimal(6,2)` | YES |  |  | m |
| `max_height` | `decimal(6,2)` | YES |  |  | m |
| `max_weight` | `decimal(8,2)` | YES |  |  | tonnes |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ozd_positive` | `(((max_length is null) or (max_length > 0)) and ((max_width is null) or (max_width > 0)) and ((max_height is null) or (max_height > 0)) and ((max_weight is null) or (max_weight > 0)))` |
| `chk_ozd_type` | `(zone_detail = 'OOG')` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_ozd_zone` | No | yard_zone_id, zone_detail |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ozd_zone` | yard_zone_id, zone_detail | [`yard_zone`](#yard_zone) (id, zone_detail) |  |

---

## `reefer_zone_detail`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `yard_zone_id` | `bigint` | NO |  | PK · FK → `yard_zone.id, zone_detail` |  |
| `zone_detail` | `ENUM(NONE, REEFER, DANGEROUS_GOODS, OOG)` | NO | `'REEFER'` | FK → `yard_zone.id, zone_detail` |  |
| `total_power_plugs` | `int` | NO |  |  | Free plugs = total - reefer containers in the zone (derived) |
| `power_type` | `varchar(30)` | YES |  |  | Display only, e.g. 380-460V 3-phase |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_rzd_plugs` | `(total_power_plugs > 0)` |
| `chk_rzd_type` | `(zone_detail = 'REEFER')` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_rzd_zone` | No | yard_zone_id, zone_detail |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_rzd_zone` | yard_zone_id, zone_detail | [`yard_zone`](#yard_zone) (id, zone_detail) |  |

---

## `yard_block`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `yard_zone_id` | `bigint` | NO |  | FK → `yard_zone.id` |  |
| `code` | `varchar(30)` | NO |  |  |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_yb_zone_code` | Yes | yard_zone_id, code |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_yb_zone` | yard_zone_id | [`yard_zone`](#yard_zone) (id) |  |

**Referenced by:** [`yard_slot`](#yard_slot).yard_block_id, [`yard_placement_item`](#yard_placement_item).yard_block_id

---

## `yard_slot`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `yard_block_id` | `bigint` | NO |  | FK → `yard_block.id` |  |
| `bay` | `int` | NO |  |  |  |
| `row_no` | `int` | NO |  |  |  |
| `tier` | `int` | NO |  |  |  |
| `status` | `ENUM(ACTIVE, BLOCKED)` | NO | `'ACTIVE'` |  | Administrative only; occupied = container_id IS NOT NULL |
| `container_id` | `bigint` | YES |  | UQ · FK → `container.id` |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ys_blocked_empty` | `((status = 'ACTIVE') or (container_id is null))` |
| `chk_ys_position` | `((bay > 0) and (row_no > 0) and (tier > 0))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_ys_id_block` | Yes | id, yard_block_id |
| `uq_ys_position` | Yes | yard_block_id, bay, row_no, tier |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ys_block` | yard_block_id | [`yard_block`](#yard_block) (id) |  |
| `fk_ys_container` | container_id | [`container`](#container) (id) |  |

**Referenced by:** [`yard_placement_item`](#yard_placement_item).yard_slot_id, yard_block_id

---

## `yard_zone`

> Capacity/occupancy are derived from yard_slot.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `container_yard_id` | `bigint` | NO |  | FK → `storage_facility.id, detail_kind` | storage_facility.id of a CONTAINER_YARD |
| `detail_kind` | `ENUM(NONE, WAREHOUSE, CONTAINER_YARD, TANK)` | NO | `'CONTAINER_YARD'` | FK → `storage_facility.id, detail_kind` | Same ENUM as storage_facility (FK compares ENUM index) |
| `yard_zone_type_id` | `bigint` | NO |  | FK → `yard_zone_type.id, zone_detail` |  |
| `zone_detail` | `ENUM(NONE, REEFER, DANGEROUS_GOODS, OOG)` | NO |  | FK → `yard_zone_type.id, zone_detail` | Copy of yard_zone_type.zone_detail, enforced by composite FK |
| `code` | `varchar(30)` | NO |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_yz_kind` | `(detail_kind = 'CONTAINER_YARD')` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_yz_type` | No | yard_zone_type_id, zone_detail |
| `fk_yz_yard` | No | container_yard_id, detail_kind |
| `uq_yz_id_detail` | Yes | id, zone_detail |
| `uq_yz_yard_code` | Yes | container_yard_id, code |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_yz_type` | yard_zone_type_id, zone_detail | [`yard_zone_type`](#yard_zone_type) (id, zone_detail) |  |
| `fk_yz_yard` | container_yard_id, detail_kind | [`storage_facility`](#storage_facility) (id, detail_kind) |  |

**Referenced by:** [`dg_zone_allowed_class`](#dg_zone_allowed_class).yard_zone_id, zone_detail, [`oog_zone_detail`](#oog_zone_detail).yard_zone_id, zone_detail, [`reefer_zone_detail`](#reefer_zone_detail).yard_zone_id, zone_detail, [`yard_block`](#yard_block).yard_zone_id

---

## `yard_zone_type`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(30)` | NO |  | UQ |  |
| `name` | `varchar(80)` | NO |  |  |  |
| `zone_detail` | `ENUM(NONE, REEFER, DANGEROUS_GOODS, OOG)` | NO | `'NONE'` |  | Which detail table zones of this type use; chosen by the admin when adding a type |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_yzt_id_detail` | Yes | id, zone_detail |

**Referenced by:** [`yard_zone`](#yard_zone).yard_zone_type_id, zone_detail

---

## `capability_type`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(60)` | NO |  | UQ |  |
| `name` | `varchar(100)` | NO |  |  |  |
| `unit` | `ENUM(TONNES, METRES, TONNES_PER_HOUR, CUBIC_METRES_PER_HOUR, MOVES_PER_HOUR, COUNT, BAR)` | NO |  |  |  |

**Referenced by:** [`equipment_capability`](#equipment_capability).capability_type_id

---

## `equipment`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `terminal_id` | `bigint` | NO |  | FK → `terminal.id` |  |
| `equipment_category_id` | `bigint` | NO |  | FK → `equipment_category.id` |  |
| `quantity` | `int` | NO |  |  | How many units of this type the terminal has |
| `maintenance_quantity` | `int` | NO | `0` |  |  |
| `blocked_quantity` | `int` | NO | `0` |  |  |
| `available_quantity` | `int` | YES |  |  | GENERATED (stored): ((quantity - maintenance_quantity) - blocked_quantity) \| Usable units; units busy in a plan are derived from plans (Module 18) |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_eq_quantity` | `((quantity > 0) and (maintenance_quantity >= 0) and (blocked_quantity >= 0) and ((maintenance_quantity + blocked_quantity) <= quantity))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `idx_eq_category` | No | equipment_category_id |
| `uq_eq_id_terminal` | Yes | id, terminal_id |
| `uq_eq_terminal_category` | Yes | terminal_id, equipment_category_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_eq_category` | equipment_category_id | [`equipment_category`](#equipment_category) (id) |  |
| `fk_eq_terminal` | terminal_id | [`terminal`](#terminal) (id) |  |

**Referenced by:** [`equipment_capability`](#equipment_capability).equipment_id, [`equipment_cargo_compatibility`](#equipment_cargo_compatibility).equipment_id, terminal_id

---

## `equipment_capability`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `equipment_id` | `bigint` | NO |  | FK → `equipment.id` |  |
| `capability_type_id` | `bigint` | NO |  | FK → `capability_type.id` |  |
| `capability_value` | `decimal(12,3)` | NO |  |  | In capability_type.unit |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ecap_value` | `(capability_value >= 0)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_ecap_type` | No | capability_type_id |
| `uq_ecap_equipment_type` | Yes | equipment_id, capability_type_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ecap_equipment` | equipment_id | [`equipment`](#equipment) (id) |  |
| `fk_ecap_type` | capability_type_id | [`capability_type`](#capability_type) (id) |  |

---

## `equipment_cargo_compatibility`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `equipment_id` | `bigint` | NO |  | PK · FK → `equipment.id, terminal_id` |  |
| `terminal_id` | `bigint` | NO |  | FK → `equipment.id, terminal_id` |  |
| `cargo_type_id` | `bigint` | NO |  | PK · FK → `terminal_cargo_type.terminal_id, cargo_type_id` |  |

**Composite primary key:** `equipment_id`, `cargo_type_id`

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_ecc_equipment` | No | equipment_id, terminal_id |
| `fk_ecc_terminal_cargo` | No | terminal_id, cargo_type_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ecc_equipment` | equipment_id, terminal_id | [`equipment`](#equipment) (id, terminal_id) |  |
| `fk_ecc_terminal_cargo` | terminal_id, cargo_type_id | [`terminal_cargo_type`](#terminal_cargo_type) (terminal_id, cargo_type_id) |  |

---

## `equipment_category`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(40)` | NO |  | UQ |  |
| `name` | `varchar(100)` | NO |  |  |  |
| `equipment_role` | `ENUM(VESSEL_HANDLING, CARGO_TRANSPORT, TRANSFER_SYSTEM)` | NO |  |  |  |

**Referenced by:** [`equipment`](#equipment).equipment_category_id, [`safety_constraint`](#safety_constraint).equipment_category_id

---

## `constraint_type`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(60)` | NO |  | UQ |  |
| `category` | `ENUM(ENVIRONMENTAL, UKC, VESSEL_SAFETY)` | NO |  |  |  |
| `unit` | `ENUM(KNOTS, METRES_PER_SECOND, METRES, NAUTICAL_MILES, PERCENT, DEGREES, MINUTES, TONNES)` | NO |  |  |  |
| `comparator` | `ENUM(MAX_ALLOWED, MIN_REQUIRED)` | NO |  |  |  |
| `is_hard_safety_limit` | `tinyint(1)` | NO | `1` |  | TRUE = never modified by Part 3 / retraining (D12). Not the solver hard/soft choice (D13, deferred) |
| `description` | `varchar(255)` | YES |  |  |  |

**Referenced by:** [`safety_constraint`](#safety_constraint).constraint_type_id, [`blocked_period`](#blocked_period).limiting_constraint_type_id, [`recommendation_rationale_factor`](#recommendation_rationale_factor).constraint_type_id

---

## `safety_constraint`

> Overlap of the same type + scope is rejected by the backend. Several scopes apply to one case: the STRICTEST value wins (Moustafa, OQ-P9-2).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `constraint_type_id` | `bigint` | NO |  | FK → `constraint_type.id` |  |
| `terminal_id` | `bigint` | YES |  | FK → `berth_terminal.berth_id, terminal_id` | NULL = port-wide |
| `berth_id` | `bigint` | YES |  | FK → `berth.id` | NULL = whole terminal / port; if set, terminal_id is the berth's terminal |
| `vessel_type_id` | `bigint` | YES |  | FK → `vessel_type.id` | NULL = all vessel types |
| `imdg_class` | `varchar(3)` | YES |  | FK → `imdg_class.code` | NULL = any cargo |
| `equipment_category_id` | `bigint` | YES |  | FK → `equipment_category.id` | NULL = not equipment-specific (e.g. cranes stop above a wind limit) |
| `value` | `decimal(12,3)` | NO |  |  | In constraint_type.unit |
| `created_by` | `bigint` | NO |  | FK → `user.id` |  |
| `active_from` | `datetime` | NO |  |  | UTC |
| `active_to` | `datetime` | YES |  |  | UTC; NULL = open-ended |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_sc_period` | `((active_to is null) or (active_to > active_from))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_sc_berth_terminal` | No | berth_id, terminal_id |
| `fk_sc_eqcat` | No | equipment_category_id |
| `fk_sc_imdg` | No | imdg_class |
| `fk_sc_terminal` | No | terminal_id |
| `fk_sc_user` | No | created_by |
| `fk_sc_vtype` | No | vessel_type_id |
| `idx_sc_lookup` | No | constraint_type_id, terminal_id, berth_id, vessel_type_id, active_from |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_sc_berth` | berth_id | [`berth`](#berth) (id) |  |
| `fk_sc_berth_terminal` | berth_id, terminal_id | [`berth_terminal`](#berth_terminal) (berth_id, terminal_id) |  |
| `fk_sc_eqcat` | equipment_category_id | [`equipment_category`](#equipment_category) (id) |  |
| `fk_sc_imdg` | imdg_class | [`imdg_class`](#imdg_class) (code) |  |
| `fk_sc_terminal` | terminal_id | [`terminal`](#terminal) (id) |  |
| `fk_sc_type` | constraint_type_id | [`constraint_type`](#constraint_type) (id) |  |
| `fk_sc_user` | created_by | [`user`](#user) (id) |  |
| `fk_sc_vtype` | vessel_type_id | [`vessel_type`](#vessel_type) (id) |  |

---

## `terminal_productivity_profile`

> Inputs for the AI service-time calculation (Module 15). Overlapping periods rejected by the backend.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `terminal_id` | `bigint` | NO |  | FK → `terminal.id, terminal_type` |  |
| `terminal_type` | `ENUM(CONTAINER, NON_CONTAINER)` | NO |  | FK → `terminal.id, terminal_type` | Copy of terminal.terminal_type, enforced by composite FK |
| `vessel_type_id` | `bigint` | YES |  | FK → `vessel_type.id` | NULL = all vessel types (terminal default) |
| `productivity_rate` | `decimal(10,2)` | NO |  |  |  |
| `rate_unit` | `ENUM(MOVES_PER_HOUR, TONNES_PER_HOUR, CUBIC_METRES_PER_HOUR, UNITS_PER_HOUR)` | NO |  |  |  |
| `fixed_overhead_minutes` | `int` | NO |  |  |  |
| `valid_from` | `date` | NO |  |  |  |
| `valid_to` | `date` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_tpp_period` | `((valid_to is null) or (valid_to >= valid_from))` |
| `chk_tpp_unit` | `((terminal_type = 'CONTAINER') = (rate_unit = 'MOVES_PER_HOUR'))` |
| `chk_tpp_values` | `((productivity_rate > 0) and (fixed_overhead_minutes >= 0))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_tpp_terminal` | No | terminal_id, terminal_type |
| `fk_tpp_vtype` | No | vessel_type_id |
| `idx_tpp_lookup` | No | terminal_id, vessel_type_id, valid_from |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_tpp_terminal` | terminal_id, terminal_type | [`terminal`](#terminal) (id, terminal_type) |  |
| `fk_tpp_vtype` | vessel_type_id | [`vessel_type`](#vessel_type) (id) |  |

---

## `berthing_request`

> status = current state; history in berthing_request_status_history (same transaction). Return for revision only from SUBMITTED; ENTRY_WINDOW_CONFIRMED may be reopened to CUSTOMS_APPROVED while no plan is confirmed. Cancel only before Preliminary Approval.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `vessel_id` | `bigint` | NO |  | FK → `vessel.id` |  |
| `requested_terminal_id` | `bigint` | NO |  | FK → `terminal.id` |  |
| `source` | `ENUM(MANUAL, EXTERNAL)` | NO |  |  |  |
| `external_system_id` | `bigint` | YES |  | FK → `external_system.id` | Required when EXTERNAL. Optional for MANUAL: staff enter the EGMSW trip number (seed external_system row EGMSW) |
| `external_reference` | `varchar(100)` | YES |  |  | Pairs with external_system_id (both set or both empty for MANUAL); unique per external system |
| `submitted_by` | `bigint` | YES |  | FK → `user.id` | Required when MANUAL; NULL when ingested by the external adapter |
| `requested_eta` | `datetime` | NO |  |  | UTC |
| `arrival_draft` | `decimal(5,2)` | NO |  |  | m, this call; backend checks > 0 and <= vessel.max_draft |
| `departure_draft` | `decimal(5,2)` | YES |  |  | m, expected draft on departure. Nullable (not known at arrival) |
| `previous_port` | `char(5)` | YES |  |  | UN/LOCODE, nullable |
| `next_port` | `char(5)` | YES |  |  | UN/LOCODE, nullable |
| `draft_source` | `ENUM(MANUAL, EGMSW_FEED, REGISTRY)` | NO | `'MANUAL'` |  |  |
| `eta_source` | `ENUM(MANUAL, EGMSW_FEED, REGISTRY)` | NO | `'MANUAL'` |  |  |
| `ports_source` | `ENUM(MANUAL, EGMSW_FEED, REGISTRY)` | NO | `'MANUAL'` |  |  |
| `block_coefficient` | `decimal(4,3)` | NO |  |  | Cb entered on the request form (D-44) |
| `gross_tonnage` | `int` | NO |  |  | GT, entered on the request form; input to the AI tug formula (OQ-15-1) |
| `deadweight_tonnes` | `int` | NO |  |  | DWT (tonnes), entered on the request form; input to the AI tug formula (OQ-15-1) |
| `status` | `ENUM(SUBMITTED, NEEDS_REVISION, PRELIMINARY_APPROVED, CUSTOMS_APPROVED, ENTRY_WINDOW_CONFIRMED, RECOMMENDATION_GENERATED, PLAN_CONFIRMED, PLAN_CANCELLED, COMPLETED, REJECTED, CANCELLED)` | NO | `'SUBMITTED'` |  |  |
| `created_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_br_cb` | `(block_coefficient > 0.3) and (block_coefficient < 1.0)` |
| `chk_br_tonnage` | `(gross_tonnage > 0) and (deadweight_tonnes > 0)` |
| `chk_br_source` | `(source = 'EXTERNAL' AND external_system_id IS NOT NULL AND external_reference IS NOT NULL) OR (source = 'MANUAL' AND submitted_by IS NOT NULL AND (external_system_id IS NULL) = (external_reference IS NULL))` |
| `chk_br_departure_draft` | `departure_draft IS NULL OR departure_draft > 0` |
| `chk_br_ports` | `(previous_port IS NULL OR regexp_like(previous_port, '^[A-Z]{2}[A-Z2-9]{3}$')) AND (next_port IS NULL OR regexp_like(next_port, '^[A-Z]{2}[A-Z2-9]{3}$'))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_br_user` | No | submitted_by |
| `fk_br_vessel` | No | vessel_id |
| `idx_br_status` | No | status |
| `idx_br_terminal_status` | No | requested_terminal_id, status |
| `uq_br_external_ref` | Yes | external_system_id, external_reference |
| `uq_br_id_terminal` | Yes | id, requested_terminal_id |
| `uq_br_id_vessel` | Yes | id, vessel_id |
| `idx_br_vessel_eta` | No | vessel_id, requested_eta |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_br_external` | external_system_id | [`external_system`](#external_system) (id) |  |
| `fk_br_terminal` | requested_terminal_id | [`terminal`](#terminal) (id) |  |
| `fk_br_user` | submitted_by | [`user`](#user) (id) |  |
| `fk_br_vessel` | vessel_id | [`vessel`](#vessel) (id) |  |

**Referenced by:** [`part1_run`](#part1_run).berthing_request_id, [`berthing_request_cargo`](#berthing_request_cargo).berthing_request_id, terminal_id, [`berthing_request_status_history`](#berthing_request_status_history).berthing_request_id, [`baplie_file`](#baplie_file).berthing_request_id, vessel_id, [`forecast_run`](#forecast_run).berthing_request_id, [`entry_window_proposal`](#entry_window_proposal).berthing_request_id, [`recommendation`](#recommendation).berthing_request_id, terminal_id

---

## `berthing_request_cargo`

> Cargo of non-container calls (container calls use the BAPLIE manifest).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `berthing_request_id` | `bigint` | NO |  | PK · FK → `berthing_request.id, requested_terminal_id` |  |
| `terminal_id` | `bigint` | NO |  | FK → `berthing_request.id, requested_terminal_id` | Copy of the request terminal, enforced by composite FK |
| `cargo_type_id` | `bigint` | NO |  | PK · FK → `terminal_cargo_type.terminal_id, cargo_type_id` |  |
| `operation` | `ENUM(DISCHARGE, LOAD)` | NO |  | PK |  |
| `quantity` | `decimal(14,2)` | NO |  |  |  |
| `quantity_unit` | `ENUM(TONNES, CUBIC_METRES, UNITS)` | NO |  |  |  |

**Composite primary key:** `berthing_request_id`, `cargo_type_id`, `operation`

**Check constraints**

| Name | Rule |
|---|---|
| `chk_brc_quantity` | `(quantity > 0)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_brc_request` | No | berthing_request_id, terminal_id |
| `fk_brc_terminal_cargo` | No | terminal_id, cargo_type_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_brc_request` | berthing_request_id, terminal_id | [`berthing_request`](#berthing_request) (id, requested_terminal_id) |  |
| `fk_brc_terminal_cargo` | terminal_id, cargo_type_id | [`terminal_cargo_type`](#terminal_cargo_type) (terminal_id, cargo_type_id) |  |

---

## `berthing_request_status_history`

> CUSTOMS_APPROVED row = who/when Customs Approval was recorded (D1, D4).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `berthing_request_id` | `bigint` | NO |  | FK → `berthing_request.id` |  |
| `from_status` | `ENUM(SUBMITTED, NEEDS_REVISION, PRELIMINARY_APPROVED, CUSTOMS_APPROVED, ENTRY_WINDOW_CONFIRMED, RECOMMENDATION_GENERATED, PLAN_CONFIRMED, PLAN_CANCELLED, COMPLETED, REJECTED, CANCELLED)` | YES |  |  | NULL on creation |
| `to_status` | `ENUM(SUBMITTED, NEEDS_REVISION, PRELIMINARY_APPROVED, CUSTOMS_APPROVED, ENTRY_WINDOW_CONFIRMED, RECOMMENDATION_GENERATED, PLAN_CONFIRMED, PLAN_CANCELLED, COMPLETED, REJECTED, CANCELLED)` | NO |  |  |  |
| `changed_by` | `bigint` | YES |  | FK → `user.id` | NULL = system / external adapter |
| `changed_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `note` | `varchar(500)` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_brsh_change` | `((from_status is null) or (from_status <> to_status))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_brsh_user` | No | changed_by |
| `idx_brsh_request` | No | berthing_request_id, changed_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_brsh_request` | berthing_request_id | [`berthing_request`](#berthing_request) (id) |  |
| `fk_brsh_user` | changed_by | [`user`](#user) (id) |  |

---

## `external_system`

> External adapters that send berthing requests (P10-03).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(30)` | NO |  | UQ |  |
| `name` | `varchar(100)` | NO |  |  |  |

**Referenced by:** [`berthing_request`](#berthing_request).external_system_id

---

## `baplie_file`

> Exactly one ACTIVE file per request (active_request_key). Replacing a revision: old ACTIVE -> REPLACED, new -> ACTIVE, in one transaction. Mistaken upload -> WITHDRAWN.
>
> Diagnostics rows live in baplie_parse_issue (no JSON copy here).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `vessel_id` | `bigint` | NO |  | FK → `berthing_request.id, vessel_id` |  |
| `berthing_request_id` | `bigint` | NO |  | FK → `berthing_request.id, vessel_id` | Must be a request for the same vessel (composite FK below) |
| `uploaded_by` | `bigint` | NO |  | FK → `user.id` |  |
| `file_hash_sha256` | `char(64)` | NO |  |  | Not globally unique: the same file may be uploaded for a different request |
| `live_hash_key` | `char(64)` | YES |  |  | GENERATED (stored): IF(status = WITHDRAWN, NULL, file_hash_sha256) |
| `storage_path` | `varchar(255)` | NO |  |  |  |
| `parse_status` | `ENUM(PENDING, PARSED, FAILED, QUARANTINED)` | NO | `'PENDING'` |  |  |
| `status` | `ENUM(STAGED, ACTIVE, REPLACED, WITHDRAWN)` | NO | `'STAGED'` |  | STAGED = uploaded, not yet in use; ACTIVE = in use; REPLACED = replaced by a revision; WITHDRAWN = uploaded by mistake |
| `active_request_key` | `bigint` | YES |  | UQ | GENERATED (stored): IF(status = ACTIVE, berthing_request_id, NULL). One ACTIVE file per request. |
| `staged_payload` | `json` | YES |  |  | Quarantine: extracted data of a QUARANTINED file, kept so staff can correct it. Commercially sensitive (terminal isolation rules) |
| `is_manually_corrected` | `boolean` | NO | `false` |  |  |
| `corrected_by` | `bigint` | YES |  | FK → `user.id` |  |
| `corrected_at` | `datetime` | YES |  |  |  |
| `uploaded_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_bf_hash` | `regexp_like(file_hash_sha256, '^[0-9a-f]{64}$', 'c')` |
| `chk_bf_correction` | `(is_manually_corrected = false) OR (corrected_by IS NOT NULL AND corrected_at IS NOT NULL)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_bf_request` | No | berthing_request_id, vessel_id |
| `fk_bf_user` | No | uploaded_by |
| `fk_bf_vessel` | No | vessel_id |
| `fk_bf_corrected_by` | No | corrected_by |
| `idx_bf_request` | No | berthing_request_id, uploaded_at |
| `uq_bf_request_live_hash` | Yes | berthing_request_id, live_hash_key |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_bf_request` | berthing_request_id, vessel_id | [`berthing_request`](#berthing_request) (id, vessel_id) |  |
| `fk_bf_user` | uploaded_by | [`user`](#user) (id) |  |
| `fk_bf_vessel` | vessel_id | [`vessel`](#vessel) (id) |  |
| `fk_cb_user` | corrected_by | [`user`](#user) (id) |  |

**Referenced by:** [`baplie_parse_issue`](#baplie_parse_issue).baplie_file_id, [`container_manifest_entry`](#container_manifest_entry).baplie_file_id

---

## `baplie_parse_issue`

> FR-1.2.4 diagnostics.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `baplie_file_id` | `bigint` | NO |  | FK → `baplie_file.id` |  |
| `severity` | `ENUM(ERROR, WARNING)` | NO |  |  |  |
| `segment_ref` | `varchar(60)` | YES |  |  |  |
| `message` | `varchar(500)` | NO |  |  |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_bpi_file` | No | baplie_file_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_bpi_file` | baplie_file_id | [`baplie_file`](#baplie_file) (id) |  |

---

## `container_manifest_entry`

> Per-call container data from BAPLIE. Container identity in container (Module 5).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `baplie_file_id` | `bigint` | NO |  | FK → `baplie_file.id` |  |
| `container_id` | `bigint` | NO |  | FK → `container.id` |  |
| `full_empty` | `ENUM(FULL, EMPTY)` | YES |  |  | NULL = missing in BAPLIE; staff fixes it (FR-1.2.5). Backend blocks CUSTOMS_APPROVED and yard recommendations while any line is incomplete |
| `weight_kg` | `int` | YES |  |  | Gross weight (VGM), kg |
| `imdg_class` | `varchar(3)` | YES |  | FK → `imdg_class.code` | NULL = not dangerous |
| `oog_over_front_cm` | `int` | YES |  |  | BAPLIE DIM overhang, cm; NULL = none. Overall size = standard size (container_size_type) + overhangs, compared with oog_zone_detail |
| `oog_over_back_cm` | `int` | YES |  |  |  |
| `oog_over_right_cm` | `int` | YES |  |  |  |
| `oog_over_left_cm` | `int` | YES |  |  |  |
| `oog_over_height_cm` | `int` | YES |  |  |  |
| `reefer_set_temperature` | `decimal(5,2)` | YES |  |  | Â°C. Reefer (container_size_type.is_reefer) with NULL here = data error flagged by the parser |
| `stowage_bay` | `int` | YES |  |  |  |
| `stowage_row` | `int` | YES |  |  | 00 = centre line |
| `stowage_tier` | `int` | YES |  |  |  |
| `port_of_load` | `char(5)` | YES |  |  | UN/LOCODE |
| `port_of_discharge` | `char(5)` | YES |  |  | UN/LOCODE; = port_profile.port_code means discharged here |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_cme_oog` | `(((oog_over_front_cm is null) or (oog_over_front_cm > 0)) and ((oog_over_back_cm is null) or (oog_over_back_cm > 0)) and ((oog_over_right_cm is null) or (oog_over_right_cm > 0)) and ((oog_over_left_cm is null) or (oog_over_left_cm > 0)) and ((oog_over_height_cm is null) or (oog_over_height_cm > 0)))` |
| `chk_cme_pod` | `((port_of_discharge is null) or regexp_like(port_of_discharge,'^[A-Z]{2}[A-Z2-9]{3}$','c'))` |
| `chk_cme_pol` | `((port_of_load is null) or regexp_like(port_of_load,'^[A-Z]{2}[A-Z2-9]{3}$','c'))` |
| `chk_cme_reefer` | `((reefer_set_temperature is null) or (reefer_set_temperature between -(40) and 40))` |
| `chk_cme_stowage` | `(((stowage_bay is null) or (stowage_bay > 0)) and ((stowage_row is null) or (stowage_row >= 0)) and ((stowage_tier is null) or (stowage_tier > 0)))` |
| `chk_cme_weight` | `((weight_kg is null) or (weight_kg > 0))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_cme_container` | No | container_id |
| `fk_cme_imdg` | No | imdg_class |
| `uq_cme_file_container` | Yes | baplie_file_id, container_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_cme_container` | container_id | [`container`](#container) (id) |  |
| `fk_cme_file` | baplie_file_id | [`baplie_file`](#baplie_file) (id) |  |
| `fk_cme_imdg` | imdg_class | [`imdg_class`](#imdg_class) (code) |  |

**Referenced by:** [`yard_placement_item`](#yard_placement_item).manifest_entry_id

---

## `environment_forecast_point`

> Raw provider values; unit conversion and derived values (e.g. surge) are AI-side (P12-02).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `forecast_run_id` | `bigint` | NO |  | FK → `forecast_run.id` |  |
| `valid_at` | `datetime` | NO |  |  |  |
| `wind_speed` | `decimal(5,2)` | YES |  |  | Unit: forecast_run.wind_speed_unit |
| `wind_gust` | `decimal(5,2)` | YES |  |  | Unit: forecast_run.wind_speed_unit |
| `wind_direction_deg` | `decimal(4,1)` | YES |  |  | Direction the wind comes from, degrees true |
| `visibility` | `decimal(8,2)` | YES |  |  | Unit: forecast_run.visibility_unit |
| `wave_height` | `decimal(4,2)` | YES |  |  | m (significant) |
| `wave_period` | `decimal(4,1)` | YES |  |  | s |
| `wave_direction_deg` | `decimal(4,1)` | YES |  |  | Direction the waves come from, degrees true |
| `current_speed` | `decimal(4,2)` | YES |  |  | Unit: forecast_run.current_speed_unit |
| `sea_level` | `decimal(5,3)` | YES |  |  | m above the active tide datum |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_efp_direction` | `(((wind_direction_deg is null) or ((wind_direction_deg >= 0) and (wind_direction_deg < 360))) and ((wave_direction_deg is null) or ((wave_direction_deg >= 0) and (wave_direction_deg < 360))))` |
| `chk_efp_values` | `(((wind_speed is null) or (wind_speed >= 0)) and ((wind_gust is null) or (wind_gust >= 0)) and ((visibility is null) or (visibility >= 0)) and ((wave_height is null) or (wave_height >= 0)) and ((wave_period is null) or (wave_period >= 0)) and ((current_speed is null) or (current_speed >= 0)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_efp_run_time` | Yes | forecast_run_id, valid_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_efp_run` | forecast_run_id | [`forecast_run`](#forecast_run) (id) |  |

---

## `forecast_run`

> Location = port_profile lat/lon (single port).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `source` | `varchar(80)` | NO |  |  | Weather provider |
| `pull_type` | `ENUM(SCHEDULED, ON_DEMAND, MANUAL)` | NO |  |  |  |
| `provider_rank` | `ENUM(PRIMARY, SECONDARY, MANUAL)` | YES |  |  | Which provider supplied the data |
| `data_stale` | `tinyint(1)` | NO | `0` |  | 1 = latest data older than the allowed age |
| `berthing_request_id` | `bigint` | YES |  | FK → `berthing_request.id` | Set only for ON_DEMAND (Customs Approval) |
| `submitted_by` | `bigint` | YES |  | FK → `user.id` | Required when pull_type = MANUAL |
| `status` | `ENUM(SUCCESS, FAILED)` | NO |  |  |  |
| `pulled_at` | `datetime` | NO |  |  |  |
| `horizon_start` | `datetime` | YES |  |  |  |
| `horizon_end` | `datetime` | YES |  |  |  |
| `wind_speed_unit` | `ENUM(KNOTS, METRES_PER_SECOND, KM_PER_HOUR)` | NO |  |  | Unit of wind_speed / wind_gust as sent by the provider |
| `current_speed_unit` | `ENUM(KNOTS, METRES_PER_SECOND, KM_PER_HOUR)` | NO |  |  |  |
| `visibility_unit` | `ENUM(NAUTICAL_MILES, KILOMETRES, METRES)` | NO |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_fr_horizon` | `((horizon_start is null) or (horizon_end is null) or (horizon_end > horizon_start))` |
| `chk_fr_on_demand` | `((pull_type = 'SCHEDULED') = (berthing_request_id is null))` |
| `chk_fr_manual` | `((pull_type = 'MANUAL') = (submitted_by is not null))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_fr_request` | No | berthing_request_id |
| `fk_fr_submitted_by` | No | submitted_by |
| `idx_fr_pulled` | No | pulled_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_fr_request` | berthing_request_id | [`berthing_request`](#berthing_request) (id) |  |
| `fk_fr_submitted_by` | submitted_by | [`user`](#user) (id) |  |

**Referenced by:** [`environment_forecast_point`](#environment_forecast_point).forecast_run_id, [`entry_window_proposal`](#entry_window_proposal).forecast_run_id

---

## `tide_constituent`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `constituent_set_id` | `bigint` | NO |  | FK → `tide_constituent_set.id` |  |
| `constituent_code` | `varchar(10)` | NO |  |  | M2, S2, K1, O1 ... |
| `amplitude` | `decimal(8,4)` | NO |  |  | m |
| `phase_lag` | `decimal(7,3)` | NO |  |  | degrees |
| `speed_deg_per_hour` | `decimal(10,6)` | NO |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_tc_values` | `((amplitude >= 0) and (phase_lag >= 0) and (phase_lag < 360) and (speed_deg_per_hour > 0))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_tc_set_code` | Yes | constituent_set_id, constituent_code |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_tc_set` | constituent_set_id | [`tide_constituent_set`](#tide_constituent_set) (id) |  |

---

## `tide_constituent_set`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `version_label` | `varchar(40)` | NO |  | UQ |  |
| `datum_reference` | `varchar(40)` | NO |  |  | Must equal navigation_channel / berth depth_datum (backend check on activation) |
| `valid_from` | `date` | NO |  |  |  |
| `is_active` | `tinyint(1)` | NO | `0` |  |  |
| `active_key` | `tinyint` | YES |  | UQ | GENERATED (stored): if(is_active,1,NULL) |

**Referenced by:** [`tide_constituent`](#tide_constituent).constituent_set_id, [`tide_prediction_run`](#tide_prediction_run).constituent_set_id

---

## `tide_prediction_point`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `tide_prediction_run_id` | `bigint` | NO |  | FK → `tide_prediction_run.id` |  |
| `valid_at` | `datetime` | NO |  |  |  |
| `height` | `decimal(5,3)` | NO |  |  | m above datum_reference |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_tpp_run_time` | Yes | tide_prediction_run_id, valid_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_tpp_run` | tide_prediction_run_id | [`tide_prediction_run`](#tide_prediction_run) (id) |  |

---

## `tide_prediction_run`

> Daily in-house harmonic computation (D7, D10).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `constituent_set_id` | `bigint` | NO |  | FK → `tide_constituent_set.id` |  |
| `computed_at` | `datetime` | NO |  |  |  |
| `horizon_start` | `datetime` | NO |  |  |  |
| `horizon_end` | `datetime` | NO |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_tpr_horizon` | `(horizon_end > horizon_start)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_tpr_set` | No | constituent_set_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_tpr_set` | constituent_set_id | [`tide_constituent_set`](#tide_constituent_set) (id) |  |

**Referenced by:** [`tide_prediction_point`](#tide_prediction_point).tide_prediction_run_id, [`entry_window_proposal`](#entry_window_proposal).tide_prediction_run_id

---

## `ml_model`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `model_key` | `ENUM(M2_SERVICE_TIME, M3_DEMAND_FORECAST, M4_DELAY_RISK, FORECAST_BIAS_CORRECTION)` | NO |  | UQ |  |
| `algorithm_family` | `varchar(40)` | NO |  |  |  |
| `description` | `varchar(255)` | YES |  |  |  |

**Referenced by:** [`ml_model_version`](#ml_model_version).ml_model_id, [`retraining_run`](#retraining_run).ml_model_id

---

## `ml_model_version`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `ml_model_id` | `bigint` | NO |  | FK → `ml_model.id` |  |
| `version_no` | `int` | NO |  |  |  |
| `status` | `ENUM(CHALLENGER, CHAMPION, ARCHIVED, REJECTED)` | NO | `'CHALLENGER'` |  |  |
| `champion_key` | `bigint` | YES |  | UQ | GENERATED (stored): if((status = 'CHAMPION'),ml_model_id,NULL) |
| `hyperparameters` | `json` | YES |  |  |  |
| `training_window_start` | `datetime` | YES |  |  |  |
| `training_window_end` | `datetime` | YES |  |  |  |
| `training_row_count` | `int` | YES |  |  |  |
| `artifact_path` | `varchar(255)` | NO |  |  | Local file volume only (D8) |
| `trained_at` | `datetime` | NO |  |  |  |
| `deployed_at` | `datetime` | YES |  |  |  |
| `retired_at` | `datetime` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_mlv_champion_deployed` | `((status <> 'CHAMPION') or (deployed_at is not null))` |
| `chk_mlv_version` | `((version_no > 0) and ((training_row_count is null) or (training_row_count >= 0)))` |
| `chk_mlv_window` | `((training_window_start is null) or (training_window_end is null) or (training_window_end > training_window_start))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_mlv_id_model` | Yes | id, ml_model_id |
| `uq_mlv_model_version` | Yes | ml_model_id, version_no |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_mlv_model` | ml_model_id | [`ml_model`](#ml_model) (id) |  |

**Referenced by:** [`demand_forecast_slot`](#demand_forecast_slot).model_version_id, [`entry_window_proposal`](#entry_window_proposal).bias_correction_version_id, [`solver_run_model_version`](#solver_run_model_version).model_version_id, [`drift_measurement`](#drift_measurement).model_version_id, [`evaluation_metric`](#evaluation_metric).model_version_id, [`retraining_run`](#retraining_run).challenger_version_id, ml_model_id, [`retraining_run`](#retraining_run).champion_version_id, ml_model_id

---

## `blocked_period`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `proposal_id` | `bigint` | NO |  | FK → `entry_window_proposal.id` |  |
| `period_start` | `datetime` | NO |  |  |  |
| `period_end` | `datetime` | NO |  |  |  |
| `limiting_constraint_type_id` | `bigint` | NO |  | FK → `constraint_type.id` |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_bp_period` | `(period_end > period_start)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_bp_constraint` | No | limiting_constraint_type_id |
| `fk_bp_proposal` | No | proposal_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_bp_constraint` | limiting_constraint_type_id | [`constraint_type`](#constraint_type) (id) |  |
| `fk_bp_proposal` | proposal_id | [`entry_window_proposal`](#entry_window_proposal) (id) |  |

---

## `demand_forecast_slot`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `generated_at` | `datetime` | NO |  |  |  |
| `slot_start` | `datetime` | NO |  |  |  |
| `slot_end` | `datetime` | NO |  |  |  |
| `expected_arrivals` | `int` | NO |  |  |  |
| `congestion_score` | `decimal(4,3)` | NO |  |  |  |
| `method` | `ENUM(AGGREGATION, LIGHTGBM)` | NO |  |  |  |
| `model_version_id` | `bigint` | YES |  | FK → `ml_model_version.id` | NULL in Phase 1 (aggregation) |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_dfs_method` | `((method = 'LIGHTGBM') = (model_version_id is not null))` |
| `chk_dfs_values` | `((slot_end > slot_start) and (expected_arrivals >= 0) and (congestion_score between 0 and 1))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_dfs_model` | No | model_version_id |
| `idx_dfs_slot` | No | slot_start |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_dfs_model` | model_version_id | [`ml_model_version`](#ml_model_version) (id) |  |

---

## `entry_window_proposal`

> Part 1 output (D2). Confirmed window feeds Part 2 (D3). data_versions = the three run FKs.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI · FK → `feasible_window.id, proposal_id` |  |
| `berthing_request_id` | `bigint` | NO |  | FK → `berthing_request.id` |  |
| `forecast_run_id` | `bigint` | NO |  | FK → `forecast_run.id` |  |
| `tide_prediction_run_id` | `bigint` | NO |  | FK → `tide_prediction_run.id` |  |
| `bias_correction_version_id` | `bigint` | YES |  | FK → `ml_model_version.id` |  |
| `peak_congestion_score` | `decimal(4,3)` | YES |  |  |  |
| `status` | `ENUM(PROPOSED, CONFIRMED, REPLACED, REJECTED)` | NO | `'PROPOSED'` |  |  |
| `confirmed_key` | `bigint` | YES |  | UQ | GENERATED (stored): if((status = 'CONFIRMED'),berthing_request_id,NULL) |
| `generated_at` | `datetime` | NO |  |  |  |
| `confirmed_window_id` | `bigint` | YES |  | FK → `feasible_window.id, proposal_id` |  |
| `confirmed_by` | `bigint` | YES |  | FK → `user.id` |  |
| `confirmed_at` | `datetime` | YES |  |  |  |
| `rejection_reason` | `varchar(500)` | YES |  |  | Required when REJECTED (all windows rejected) |
| `rejected_by` | `bigint` | YES |  | FK → `user.id` |  |
| `rejected_at` | `datetime` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ewp_confirm_set` | `(((confirmed_window_id is null) = (confirmed_by is null)) and ((confirmed_by is null) = (confirmed_at is null)))` |
| `chk_ewp_confirmed` | `((status <> 'CONFIRMED') or (confirmed_window_id is not null))` |
| `chk_ewp_score` | `((peak_congestion_score is null) or (peak_congestion_score between 0 and 1))` |
| `chk_ewp_reject_set` | `(((rejected_by IS NULL) = (rejected_at IS NULL)) AND ((status = 'REJECTED') = (rejection_reason IS NOT NULL)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_ewp_bias` | No | bias_correction_version_id |
| `fk_ewp_forecast` | No | forecast_run_id |
| `fk_ewp_request` | No | berthing_request_id |
| `fk_ewp_tide` | No | tide_prediction_run_id |
| `fk_ewp_user` | No | confirmed_by |
| `fk_ewp_window` | No | confirmed_window_id, id |
| `uq_ewp_id_request` | Yes | id, berthing_request_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ewp_bias` | bias_correction_version_id | [`ml_model_version`](#ml_model_version) (id) |  |
| `fk_ewp_forecast` | forecast_run_id | [`forecast_run`](#forecast_run) (id) |  |
| `fk_ewp_request` | berthing_request_id | [`berthing_request`](#berthing_request) (id) |  |
| `fk_ewp_tide` | tide_prediction_run_id | [`tide_prediction_run`](#tide_prediction_run) (id) |  |
| `fk_ewp_user` | confirmed_by | [`user`](#user) (id) |  |
| `fk_ewp_window` | confirmed_window_id, id | [`feasible_window`](#feasible_window) (id, proposal_id) |  |
| `fk_ewp_rejected_by` | rejected_by | [`user`](#user) (id) |  |

**Referenced by:** [`part1_run`](#part1_run).entry_window_proposal_id, [`blocked_period`](#blocked_period).proposal_id, [`feasible_window`](#feasible_window).proposal_id, [`recommendation`](#recommendation).entry_window_proposal_id, berthing_request_id

---

## `feasible_window`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `proposal_id` | `bigint` | NO |  | FK → `entry_window_proposal.id` |  |
| `window_start` | `datetime` | NO |  |  |  |
| `window_end` | `datetime` | NO |  |  |  |
| `confidence` | `decimal(4,3)` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_fw_values` | `((window_end > window_start) and ((confidence is null) or (confidence between 0 and 1)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_fw_proposal` | No | proposal_id |
| `uq_fw_id_proposal` | Yes | id, proposal_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_fw_proposal` | proposal_id | [`entry_window_proposal`](#entry_window_proposal) (id) |  |

**Referenced by:** [`entry_window_proposal`](#entry_window_proposal).confirmed_window_id, id

---

## `rationale_template`

> Fixed, versioned templates; never LLM (D11).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(60)` | NO |  |  |  |
| `version` | `int` | NO |  |  |  |
| `template_text` | `text` | NO |  |  |  |
| `is_active` | `tinyint(1)` | NO | `1` |  |  |
| `active_code` | `varchar(60)` | YES |  | UQ | GENERATED (stored): if(is_active,code,NULL) |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_rt_version` | `(version > 0)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_rt_code_version` | Yes | code, version |

**Referenced by:** [`recommendation_option`](#recommendation_option).rationale_template_id, [`terminal_recommendation`](#terminal_recommendation).rationale_template_id

---

## `recommendation`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `solver_run_id` | `bigint` | NO |  | FK → `solver_run.id` |  |
| `berthing_request_id` | `bigint` | NO |  | FK → `entry_window_proposal.id, berthing_request_id` |  |
| `terminal_id` | `bigint` | NO |  | FK → `berthing_request.id, requested_terminal_id` | Copy of the requested terminal, enforced by composite FK |
| `entry_window_proposal_id` | `bigint` | NO |  | FK → `entry_window_proposal.id, berthing_request_id` | Confirmed Part 1 input of the same request |
| `status` | `ENUM(PENDING_REVIEW, CONFIRMED, REJECTED, INVALIDATED, REPLACED, MODIFIED_BY_STAFF)` | NO | `'PENDING_REVIEW'` |  |  |
| `source` | `ENUM(AI, FIFO)` | NO | `'AI'` |  | FIFO = fallback after a solver technical failure |
| `purpose` | `ENUM(INITIAL, DELAY_REPLAN)` | NO | `'INITIAL'` |  |  |
| `active_key` | `bigint` | YES |  | UQ | GENERATED (stored): IF(status IN (PENDING_REVIEW, MODIFIED_BY_STAFF, CONFIRMED) AND purpose = INITIAL, berthing_request_id, NULL) |
| `generated_at` | `datetime` | NO |  |  |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_rec_proposal` | No | entry_window_proposal_id, berthing_request_id |
| `fk_rec_request` | No | berthing_request_id, terminal_id |
| `fk_rec_run` | No | solver_run_id |
| `uq_rec_id_request` | Yes | id, berthing_request_id |
| `uq_rec_id_terminal` | Yes | id, terminal_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_rec_proposal` | entry_window_proposal_id, berthing_request_id | [`entry_window_proposal`](#entry_window_proposal) (id, berthing_request_id) |  |
| `fk_rec_request` | berthing_request_id, terminal_id | [`berthing_request`](#berthing_request) (id, requested_terminal_id) |  |
| `fk_rec_run` | solver_run_id | [`solver_run`](#solver_run) (id) |  |

**Referenced by:** [`recommendation_review`](#recommendation_review).recommendation_id, [`recommendation_option`](#recommendation_option).recommendation_id, terminal_id, [`terminal_recommendation`](#terminal_recommendation).recommendation_id, terminal_id, [`operational_plan`](#operational_plan).recommendation_id, berthing_request_id

---

## `recommendation_option`

> No is_selected column: the selected option is the one operational_plan references (backend reads it, P15-03).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `recommendation_id` | `bigint` | NO |  | FK → `recommendation.id, terminal_id` |  |
| `terminal_id` | `bigint` | NO |  | FK → `berth_terminal.berth_id, terminal_id` | Copy, enforced by composite FKs: berth must be allowed for the requested terminal (berth_terminal) |
| `option_rank` | `int` | NO |  |  |  |
| `berth_id` | `bigint` | NO |  | FK → `berth_terminal.berth_id, terminal_id` |  |
| `planned_start` | `datetime` | NO |  |  |  |
| `planned_end` | `datetime` | NO |  |  |  |
| `pilot_user_id` | `bigint` | YES |  | FK → `pilot_profile.user_id` |  |
| `service_time_method` | `ENUM(FORMULA, ML)` | NO |  |  |  |
| `service_time_p50_hours` | `decimal(6,2)` | NO |  |  |  |
| `service_time_p90_hours` | `decimal(6,2)` | YES |  |  | ML only |
| `delay_risk` | `decimal(4,3)` | YES |  |  |  |
| `objective_score` | `decimal(14,4)` | YES |  |  |  |
| `rationale_template_id` | `bigint` | YES |  | FK → `rationale_template.id` |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ro_p90` | `(((service_time_method = 'ML') = (service_time_p90_hours is not null)) and ((service_time_p90_hours is null) or (service_time_p90_hours >= service_time_p50_hours)))` |
| `chk_ro_values` | `((option_rank > 0) and (planned_end > planned_start) and (service_time_p50_hours > 0) and ((delay_risk is null) or (delay_risk between 0 and 1)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_ro_berth` | No | berth_id, terminal_id |
| `fk_ro_pilot` | No | pilot_user_id |
| `fk_ro_rec` | No | recommendation_id, terminal_id |
| `fk_ro_template` | No | rationale_template_id |
| `idx_ro_berth_time` | No | berth_id, planned_start |
| `uq_ro_id_rec` | Yes | id, recommendation_id |
| `uq_ro_rank` | Yes | recommendation_id, option_rank |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ro_berth` | berth_id, terminal_id | [`berth_terminal`](#berth_terminal) (berth_id, terminal_id) |  |
| `fk_ro_pilot` | pilot_user_id | [`pilot_profile`](#pilot_profile) (user_id) |  |
| `fk_ro_rec` | recommendation_id, terminal_id | [`recommendation`](#recommendation) (id, terminal_id) |  |
| `fk_ro_template` | rationale_template_id | [`rationale_template`](#rationale_template) (id) |  |

**Referenced by:** [`recommendation_option_tug`](#recommendation_option_tug).option_id, [`recommendation_override`](#recommendation_override).recommendation_option_id, [`recommendation_rationale_factor`](#recommendation_rationale_factor).option_id, [`operational_plan`](#operational_plan).recommendation_option_id, recommendation_id

---

## `recommendation_option_tug`

> Number of tugs decided by the AI formula.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `option_id` | `bigint` | NO |  | PK · FK → `recommendation_option.id` |  |
| `tugboat_id` | `bigint` | NO |  | PK · FK → `tugboat.id` |  |

**Composite primary key:** `option_id`, `tugboat_id`

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_rot_tug` | No | tugboat_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_rot_option` | option_id | [`recommendation_option`](#recommendation_option) (id) |  |
| `fk_rot_tug` | tugboat_id | [`tugboat`](#tugboat) (id) |  |

---

## `solver_run`

> Port-wide joint solve (FR-1.4.1). INFEASIBLE is final; FIFO fallback only on technical failure (recommendation.source = FIFO).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `trigger_type` | `ENUM(NEW_REQUEST, REPLAN, RESOURCE_CHANGE, MANUAL)` | NO |  |  |  |
| `triggered_by` | `bigint` | YES |  | FK → `user.id` |  |
| `horizon_start` | `datetime` | NO |  |  |  |
| `horizon_end` | `datetime` | NO |  |  |  |
| `status` | `ENUM(QUEUED, RUNNING, OPTIMAL, FEASIBLE, INFEASIBLE, ERROR)` | NO | `'QUEUED'` |  |  |
| `objective_value` | `decimal(14,4)` | YES |  |  |  |
| `solve_time_ms` | `int` | YES |  |  |  |
| `solver_version` | `varchar(40)` | NO |  |  |  |
| `result_detail` | `json` | YES |  |  | INFEASIBLE: binding constraints; ERROR: error message |
| `queued_at` | `datetime` | NO |  |  |  |
| `started_at` | `datetime` | YES |  |  |  |
| `finished_at` | `datetime` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_sr_finished` | `((status in ('QUEUED','RUNNING')) or (finished_at is not null))` |
| `chk_sr_horizon` | `(horizon_end > horizon_start)` |
| `chk_sr_manual` | `((trigger_type <> 'MANUAL') or (triggered_by is not null))` |
| `chk_sr_time` | `((solve_time_ms is null) or (solve_time_ms >= 0))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_sr_user` | No | triggered_by |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_sr_user` | triggered_by | [`user`](#user) (id) |  |

**Referenced by:** [`recommendation`](#recommendation).solver_run_id, [`solver_run_model_version`](#solver_run_model_version).solver_run_id

---

## `solver_run_model_version`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `solver_run_id` | `bigint` | NO |  | PK · FK → `solver_run.id` |  |
| `model_version_id` | `bigint` | NO |  | PK · FK → `ml_model_version.id` |  |

**Composite primary key:** `solver_run_id`, `model_version_id`

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_srmv_model` | No | model_version_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_srmv_model` | model_version_id | [`ml_model_version`](#ml_model_version) (id) |  |
| `fk_srmv_run` | solver_run_id | [`solver_run`](#solver_run) (id) |  |

---

## `objective_term`

> CP-SAT objective terms (P16-02). Codes agreed with the AI team.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(40)` | NO |  | UQ |  |
| `name` | `varchar(100)` | NO |  |  |  |

**Referenced by:** [`recommendation_rationale_factor`](#recommendation_rationale_factor).objective_term_id

---

## `recommendation_override`

> Typed override of a recommendation option (FR-1.4.7). Signal for Part 3, not an automatic retrain trigger (D12).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `recommendation_option_id` | `bigint` | NO |  | FK → `recommendation_option.id` |  |
| `component_type` | `ENUM(BERTH, PILOT, TUG, START_TIME)` | NO |  |  |  |
| `original_berth_id` | `bigint` | YES |  | FK → `berth.id` |  |
| `override_berth_id` | `bigint` | YES |  | FK → `berth.id` |  |
| `original_pilot_user_id` | `bigint` | YES |  | FK → `pilot_profile.user_id` |  |
| `override_pilot_user_id` | `bigint` | YES |  | FK → `pilot_profile.user_id` |  |
| `original_tugboat_id` | `bigint` | YES |  | FK → `tugboat.id` | NULL = tug added |
| `override_tugboat_id` | `bigint` | YES |  | FK → `tugboat.id` | NULL = tug removed |
| `original_start` | `datetime` | YES |  |  |  |
| `override_start` | `datetime` | YES |  |  | New end is computed: override_start + option service_time_p50_hours (P16-03) |
| `override_reason` | `varchar(500)` | NO |  |  |  |
| `overridden_by` | `bigint` | NO |  | FK → `user.id` |  |
| `overridden_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_rov_berth` | `(((component_type = 'BERTH') = (override_berth_id is not null)) and ((component_type = 'BERTH') = (original_berth_id is not null)))` |
| `chk_rov_pilot` | `((component_type = 'PILOT') = ((original_pilot_user_id is not null) or (override_pilot_user_id is not null)))` |
| `chk_rov_time` | `(((component_type = 'START_TIME') = (override_start is not null)) and ((component_type = 'START_TIME') = (original_start is not null)))` |
| `chk_rov_tug` | `((component_type = 'TUG') = ((original_tugboat_id is not null) or (override_tugboat_id is not null)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_rov_new_berth` | No | override_berth_id |
| `fk_rov_new_pilot` | No | override_pilot_user_id |
| `fk_rov_new_tug` | No | override_tugboat_id |
| `fk_rov_option` | No | recommendation_option_id |
| `fk_rov_orig_berth` | No | original_berth_id |
| `fk_rov_orig_pilot` | No | original_pilot_user_id |
| `fk_rov_orig_tug` | No | original_tugboat_id |
| `fk_rov_user` | No | overridden_by |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_rov_new_berth` | override_berth_id | [`berth`](#berth) (id) |  |
| `fk_rov_new_pilot` | override_pilot_user_id | [`pilot_profile`](#pilot_profile) (user_id) |  |
| `fk_rov_new_tug` | override_tugboat_id | [`tugboat`](#tugboat) (id) |  |
| `fk_rov_option` | recommendation_option_id | [`recommendation_option`](#recommendation_option) (id) |  |
| `fk_rov_orig_berth` | original_berth_id | [`berth`](#berth) (id) |  |
| `fk_rov_orig_pilot` | original_pilot_user_id | [`pilot_profile`](#pilot_profile) (user_id) |  |
| `fk_rov_orig_tug` | original_tugboat_id | [`tugboat`](#tugboat) (id) |  |
| `fk_rov_user` | overridden_by | [`user`](#user) (id) |  |

---

## `recommendation_rationale_factor`

> Constraints/objective terms that actually drove the solve (FR-1.4.10).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `option_id` | `bigint` | NO |  | FK → `recommendation_option.id` |  |
| `factor_type` | `ENUM(CONSTRAINT, OBJECTIVE_TERM)` | NO |  |  |  |
| `constraint_type_id` | `bigint` | YES |  | FK → `constraint_type.id` |  |
| `objective_term_id` | `bigint` | YES |  | FK → `objective_term.id` |  |
| `factor_value` | `decimal(14,4)` | YES |  |  |  |
| `display_order` | `int` | NO | `0` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_rrf_kind` | `(((factor_type = 'CONSTRAINT') and (constraint_type_id is not null) and (objective_term_id is null)) or ((factor_type = 'OBJECTIVE_TERM') and (objective_term_id is not null) and (constraint_type_id is null)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_rrf_constraint` | No | constraint_type_id |
| `fk_rrf_objective` | No | objective_term_id |
| `fk_rrf_option` | No | option_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_rrf_constraint` | constraint_type_id | [`constraint_type`](#constraint_type) (id) |  |
| `fk_rrf_objective` | objective_term_id | [`objective_term`](#objective_term) (id) |  |
| `fk_rrf_option` | option_id | [`recommendation_option`](#recommendation_option) (id) |  |

---

## `storage_allocation_item`

> On ACCEPTED, backend adds quantity to storage_facility.reserved_capacity (D-46).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `terminal_recommendation_id` | `bigint` | NO |  | FK → `terminal_recommendation.id, rec_type` |  |
| `rec_type` | `ENUM(TIMING, YARD_PLACEMENT, STORAGE_ALLOCATION)` | NO | `'STORAGE_ALLOCATION'` | FK → `terminal_recommendation.id, rec_type` |  |
| `storage_facility_id` | `bigint` | NO |  | FK → `storage_facility_cargo_compatibility.storage_facility_id, cargo_type_id` |  |
| `cargo_type_id` | `bigint` | NO |  | FK → `storage_facility_cargo_compatibility.storage_facility_id, cargo_type_id` |  |
| `quantity` | `decimal(14,2)` | NO |  |  |  |
| `quantity_unit` | `ENUM(TONNES, CUBIC_METRES, SQUARE_METRES, TEU, UNITS)` | NO |  | FK → `storage_facility.id, capacity_unit` | Must equal the facility capacity_unit (composite FK) |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_sai_quantity` | `(quantity > 0)` |
| `chk_sai_type` | `(rec_type = 'STORAGE_ALLOCATION')` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_sai_compatible` | No | storage_facility_id, cargo_type_id |
| `fk_sai_tr` | No | terminal_recommendation_id, rec_type |
| `fk_sai_unit` | No | storage_facility_id, quantity_unit |
| `uq_sai_rec_facility_cargo` | Yes | terminal_recommendation_id, storage_facility_id, cargo_type_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_sai_compatible` | storage_facility_id, cargo_type_id | [`storage_facility_cargo_compatibility`](#storage_facility_cargo_compatibility) (storage_facility_id, cargo_type_id) |  |
| `fk_sai_tr` | terminal_recommendation_id, rec_type | [`terminal_recommendation`](#terminal_recommendation) (id, rec_type) |  |
| `fk_sai_unit` | storage_facility_id, quantity_unit | [`storage_facility`](#storage_facility) (id, capacity_unit) |  |

---

## `terminal_recommendation`

> Common terminal output contract (FR-3.2.2). Visible to that terminal only (§42).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `recommendation_id` | `bigint` | NO |  | FK → `recommendation.id, terminal_id` |  |
| `terminal_id` | `bigint` | NO |  | FK → `recommendation.id, terminal_id` |  |
| `terminal_type` | `ENUM(CONTAINER, NON_CONTAINER)` | NO |  | FK → `terminal.id, terminal_type` | Copy, enforced by composite FK |
| `rec_type` | `ENUM(TIMING, YARD_PLACEMENT, STORAGE_ALLOCATION)` | NO |  |  |  |
| `window_start` | `datetime` | YES |  |  |  |
| `window_end` | `datetime` | YES |  |  |  |
| `decision` | `ENUM(PENDING, ACCEPTED, REJECTED, ADJUSTED)` | NO | `'PENDING'` |  |  |
| `decided_by` | `bigint` | YES |  | FK → `user.id` |  |
| `decided_at` | `datetime` | YES |  |  |  |
| `decision_note` | `varchar(500)` | YES |  |  |  |
| `lifecycle_status` | `ENUM(YARD_REC_REQUESTED, YARD_REC_GENERATED, GENERATION_FAILED, YARD_REC_ACCEPTED, YARD_REC_REJECTED, EXPORTED_TO_TOS)` | YES |  |  | YARD_PLACEMENT only |
| `failure_reason` | `varchar(500)` | YES |  |  |  |
| `exported_at` | `datetime` | YES |  |  |  |
| `exported_by` | `bigint` | YES |  | FK → `user.id` |  |
| `rationale_template_id` | `bigint` | YES |  | FK → `rationale_template.id` |  |
| `confidence` | `decimal(4,3)` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_tr_decision` | `(((decision = 'PENDING') = (decided_by is null)) and ((decided_by is null) = (decided_at is null)))` |
| `chk_tr_type_terminal` | `(((rec_type <> 'YARD_PLACEMENT') or (terminal_type = 'CONTAINER')) and ((rec_type <> 'STORAGE_ALLOCATION') or (terminal_type = 'NON_CONTAINER')))` |
| `chk_tr_window` | `(((rec_type = 'TIMING') = ((window_start is not null) and (window_end is not null))) and ((window_start is null) or (window_end > window_start)))` |
| `chk_tr_lifecycle_yard` | `(lifecycle_status IS NULL OR rec_type = 'YARD_PLACEMENT')` |
| `chk_tr_confidence` | `(confidence IS NULL OR confidence BETWEEN 0 AND 1)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_tr_rec` | No | recommendation_id, terminal_id |
| `fk_tr_terminal` | No | terminal_id, terminal_type |
| `fk_tr_user` | No | decided_by |
| `fk_tr_exported_by` | No | exported_by |
| `fk_tr_template` | No | rationale_template_id |
| `idx_tr_terminal_decision` | No | terminal_id, decision |
| `uq_tr_id_type` | Yes | id, rec_type |
| `uq_tr_rec_type` | Yes | recommendation_id, rec_type |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_tr_rec` | recommendation_id, terminal_id | [`recommendation`](#recommendation) (id, terminal_id) |  |
| `fk_tr_terminal` | terminal_id, terminal_type | [`terminal`](#terminal) (id, terminal_type) |  |
| `fk_tr_user` | decided_by | [`user`](#user) (id) |  |
| `fk_tr_exported_by` | exported_by | [`user`](#user) (id) |  |
| `fk_tr_template` | rationale_template_id | [`rationale_template`](#rationale_template) (id) |  |

**Referenced by:** [`storage_allocation_item`](#storage_allocation_item).terminal_recommendation_id, rec_type, [`yard_placement_item`](#yard_placement_item).terminal_recommendation_id, rec_type

---

## `yard_placement_item`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `terminal_recommendation_id` | `bigint` | NO |  | FK → `terminal_recommendation.id, rec_type` |  |
| `rec_type` | `ENUM(TIMING, YARD_PLACEMENT, STORAGE_ALLOCATION)` | NO | `'YARD_PLACEMENT'` | FK → `terminal_recommendation.id, rec_type` |  |
| `manifest_entry_id` | `bigint` | NO |  | FK → `container_manifest_entry.id` |  |
| `yard_block_id` | `bigint` | NO |  | FK → `yard_block.id` |  |
| `yard_slot_id` | `bigint` | YES |  | FK → `yard_slot.id, yard_block_id` | Optional; coordination level is block (FR-2.2.3) |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ypi_type` | `(rec_type = 'YARD_PLACEMENT')` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_ypi_block` | No | yard_block_id |
| `fk_ypi_manifest` | No | manifest_entry_id |
| `fk_ypi_slot` | No | yard_slot_id, yard_block_id |
| `fk_ypi_tr` | No | terminal_recommendation_id, rec_type |
| `uq_ypi_rec_container` | Yes | terminal_recommendation_id, manifest_entry_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ypi_block` | yard_block_id | [`yard_block`](#yard_block) (id) |  |
| `fk_ypi_manifest` | manifest_entry_id | [`container_manifest_entry`](#container_manifest_entry) (id) |  |
| `fk_ypi_slot` | yard_slot_id, yard_block_id | [`yard_slot`](#yard_slot) (id, yard_block_id) |  |
| `fk_ypi_tr` | terminal_recommendation_id, rec_type | [`terminal_recommendation`](#terminal_recommendation) (id, rec_type) |  |

---

## `operational_plan`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `berthing_request_id` | `bigint` | NO |  | FK → `recommendation.id, berthing_request_id` |  |
| `recommendation_id` | `bigint` | NO |  | FK → `recommendation_option.id, recommendation_id` | Enforces option <-> recommendation <-> request consistency |
| `recommendation_option_id` | `bigint` | NO |  | FK → `recommendation_option.id, recommendation_id` | The confirmed (selected) option |
| `status` | `ENUM(CONFIRMED, CANCELLED, COMPLETED)` | NO | `'CONFIRMED'` |  |  |
| `active_key` | `bigint` | YES |  | UQ | GENERATED (stored): IF(status = CONFIRMED, berthing_request_id, NULL) |
| `confirmed_by` | `bigint` | NO |  | FK → `user.id` |  |
| `confirmed_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_op_option` | No | recommendation_option_id, recommendation_id |
| `fk_op_rec` | No | recommendation_id, berthing_request_id |
| `fk_op_user` | No | confirmed_by |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_op_option` | recommendation_option_id, recommendation_id | [`recommendation_option`](#recommendation_option) (id, recommendation_id) |  |
| `fk_op_rec` | recommendation_id, berthing_request_id | [`recommendation`](#recommendation) (id, berthing_request_id) |  |
| `fk_op_user` | confirmed_by | [`user`](#user) (id) |  |

**Referenced by:** [`plan_component`](#plan_component).operational_plan_id

---

## `plan_component`

> One row per dispatched unit (one per tug). Berth/tug occupancy = RESERVED rows overlapping a window (D-20).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `operational_plan_id` | `bigint` | NO |  | FK → `operational_plan.id` |  |
| `component_type` | `ENUM(BERTH, PILOT, TUG, TERMINAL_OPS)` | NO |  |  |  |
| `berth_id` | `bigint` | YES |  | FK → `berth.id` |  |
| `tugboat_id` | `bigint` | YES |  | FK → `tugboat.id` |  |
| `terminal_id` | `bigint` | YES |  | FK → `terminal.id` |  |
| `assigned_user_id` | `bigint` | YES |  | FK → `user.id` | Pilot / Tug Master / terminal supervisor (role checked by backend, D-33) |
| `assigned_by` | `bigint` | YES |  | FK → `user.id` | Dept. Officer (FR-1.5.1/2) |
| `assigned_at` | `datetime` | YES |  |  |  |
| `planned_start` | `datetime` | NO |  |  |  |
| `planned_end` | `datetime` | NO |  |  |  |
| `reservation_state` | `ENUM(RESERVED, RELEASED, CONSUMED)` | NO | `'RESERVED'` |  | RELEASED on plan cancellation |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_pc_assigned` | `((assigned_user_id is null) or (assigned_at is not null))` |
| `chk_pc_resource` | `(((component_type = 'BERTH') and (berth_id is not null) and (tugboat_id is null) and (terminal_id is null)) or ((component_type = 'TUG') and (berth_id is null) and (tugboat_id is not null) and (terminal_id is null)) or ((component_type = 'TERMINAL_OPS') and (berth_id is null) and (tugboat_id is null) and (terminal_id is not null)) or ((component_type = 'PILOT') and (berth_id is null) and (tugboat_id is null) and (terminal_id is null)))` |
| `chk_pc_time` | `(planned_end > planned_start)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_pc_assigner` | No | assigned_by |
| `fk_pc_plan` | No | operational_plan_id |
| `fk_pc_terminal` | No | terminal_id |
| `idx_pc_berth_time` | No | berth_id, planned_start |
| `idx_pc_tug_time` | No | tugboat_id, planned_start |
| `idx_pc_user_time` | No | assigned_user_id, planned_start |
| `uq_pc_id_type` | Yes | id, component_type |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_pc_assignee` | assigned_user_id | [`user`](#user) (id) |  |
| `fk_pc_assigner` | assigned_by | [`user`](#user) (id) |  |
| `fk_pc_berth` | berth_id | [`berth`](#berth) (id) |  |
| `fk_pc_plan` | operational_plan_id | [`operational_plan`](#operational_plan) (id) |  |
| `fk_pc_terminal` | terminal_id | [`terminal`](#terminal) (id) |  |
| `fk_pc_tug` | tugboat_id | [`tugboat`](#tugboat) (id) |  |

**Referenced by:** [`storage_movement`](#storage_movement).plan_component_id, [`actual_status_update`](#actual_status_update).plan_component_id, component_type, [`deviation_log`](#deviation_log).plan_component_id

---

## `actual_status_update`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `client_event_id` | `char(36)` | NO |  | UQ | UUID generated on the device; duplicates from offline re-sends are ignored |
| `plan_component_id` | `bigint` | NO |  | FK → `plan_component.id, component_type` |  |
| `component_type` | `ENUM(BERTH, PILOT, TUG, TERMINAL_OPS)` | NO |  | FK → `plan_component.id, component_type` | Copy of the component type, enforced by composite FK |
| `reported_by` | `bigint` | NO |  | FK → `user.id` |  |
| `status` | `varchar(30)` | NO |  | FK → `status_type.component_type, code` | Must be a status of this component type (status_type) |
| `reported_at` | `datetime` | NO |  |  | Time on the ground (device) |
| `client_sent_at` | `datetime` | YES |  |  | Device clock when the batch was sent |
| `received_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  | Server receipt (offline sync) |
| `clock_skew_suspect` | `tinyint(1)` | NO | `0` |  | 1 = received_at - client_sent_at > 5 min; excluded from evaluation |
| `sync_source` | `ENUM(MOBILE, TABLET, WEB, EXTERNAL)` | NO |  |  |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_asu_component` | No | plan_component_id, component_type |
| `fk_asu_status` | No | component_type, status |
| `fk_asu_user` | No | reported_by |
| `idx_asu_component_time` | No | plan_component_id, reported_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_asu_component` | plan_component_id, component_type | [`plan_component`](#plan_component) (id, component_type) |  |
| `fk_asu_status` | component_type, status | [`status_type`](#status_type) (component_type, code) |  |
| `fk_asu_user` | reported_by | [`user`](#user) (id) |  |

**Referenced by:** [`deviation_log`](#deviation_log).status_update_id

---

## `deviation_log`

> P19-03 deferred (2026-10-08): kept as in source for now.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `plan_component_id` | `bigint` | NO |  | FK → `plan_component.id` |  |
| `status_update_id` | `bigint` | YES |  | FK → `actual_status_update.id` |  |
| `deviation_type` | `ENUM(DELAY, RESOURCE_CHANGED, STATE_MISMATCH)` | NO |  |  |  |
| `planned_value` | `varchar(255)` | NO |  |  |  |
| `actual_value` | `varchar(255)` | NO |  |  |  |
| `delay_minutes` | `int` | YES |  |  |  |
| `deviation_reason` | `varchar(500)` | YES |  |  |  |
| `detected_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_dl_component` | No | plan_component_id |
| `fk_dl_update` | No | status_update_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_dl_component` | plan_component_id | [`plan_component`](#plan_component) (id) |  |
| `fk_dl_update` | status_update_id | [`actual_status_update`](#actual_status_update) (id) |  |

---

## `status_type`

> Allowed field statuses per component type (P19-01). New status = INSERT, not ALTER.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `component_type` | `ENUM(BERTH, PILOT, TUG, TERMINAL_OPS)` | NO |  | PK | Same ENUM as plan_component.component_type |
| `code` | `varchar(30)` | NO |  | PK |  |
| `name` | `varchar(80)` | NO |  |  |  |

**Composite primary key:** `component_type`, `code`

**Referenced by:** [`actual_status_update`](#actual_status_update).component_type, status

---

## `drift_measurement`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `model_version_id` | `bigint` | NO |  | FK → `ml_model_version.id` |  |
| `metric_type` | `ENUM(PSI, ROLLING_MAE)` | NO |  |  |  |
| `feature_name` | `varchar(60)` | YES |  |  | Required for PSI |
| `metric_value` | `decimal(14,5)` | NO |  |  |  |
| `threshold` | `decimal(14,5)` | NO |  |  |  |
| `is_drifted` | `tinyint(1)` | YES |  |  | GENERATED (stored): (metric_value > threshold) |
| `measured_at` | `datetime` | NO |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_dm_feature` | `((metric_type = 'PSI') = (feature_name is not null))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_dm_version` | No | model_version_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_dm_version` | model_version_id | [`ml_model_version`](#ml_model_version) (id) |  |

---

## `evaluation_metric`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `model_version_id` | `bigint` | YES |  | FK → `ml_model_version.id` | NULL for system-level metrics |
| `retraining_run_id` | `bigint` | YES |  | FK → `retraining_run.id` |  |
| `metric_name` | `ENUM(MAE, OVERRIDE_RATE, WAIT_TIME, WINDOW_ACCURACY)` | NO |  |  |  |
| `dataset_type` | `ENUM(HOLDOUT, ROLLING, PRODUCTION)` | NO |  |  |  |
| `metric_value` | `decimal(14,5)` | NO |  |  |  |
| `period_start` | `datetime` | NO |  |  |  |
| `period_end` | `datetime` | NO |  |  |  |
| `computed_at` | `datetime` | NO |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_em_period` | `(period_end > period_start)` |
| `chk_em_scope` | `(((metric_name <> 'MAE') or (model_version_id is not null)) and ((metric_name not in ('OVERRIDE_RATE','WAIT_TIME')) or (model_version_id is null)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_em_run` | No | retraining_run_id |
| `fk_em_version` | No | model_version_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_em_run` | retraining_run_id | [`retraining_run`](#retraining_run) (id) |  |
| `fk_em_version` | model_version_id | [`ml_model_version`](#ml_model_version) (id) |  |

---

## `retraining_run`

> Champion vs Challenger history. Hard safety limits are never changed by retraining (D12).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `ml_model_id` | `bigint` | NO |  | FK → `ml_model_version.id, ml_model_id` |  |
| `champion_version_id` | `bigint` | YES |  | FK → `ml_model_version.id, ml_model_id` | NULL at cold start |
| `challenger_version_id` | `bigint` | NO |  | FK → `ml_model_version.id, ml_model_id` |  |
| `started_at` | `datetime` | NO |  |  |  |
| `finished_at` | `datetime` | YES |  |  |  |
| `decision` | `ENUM(PROMOTED, KEPT_CHAMPION, FAILED)` | NO |  |  |  |
| `decision_reason` | `varchar(500)` | YES |  |  |  |
| `review_pending` | `tinyint(1)` | YES |  |  | GENERATED (stored): ((decision in ('KEPT_CHAMPION','FAILED')) and (reviewed_at is null)) \| TRUE until someone reviews a kept-champion or failed run (D12, P20-02) |
| `reviewed_by` | `bigint` | YES |  | FK → `user.id` |  |
| `reviewed_at` | `datetime` | YES |  |  |  |
| `review_note` | `varchar(500)` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_rr_review` | `((reviewed_by is null) = (reviewed_at is null))` |
| `chk_rr_time` | `((finished_at is null) or (finished_at >= started_at))` |
| `chk_rr_versions` | `((champion_version_id is null) or (champion_version_id <> challenger_version_id))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_rr_challenger` | No | challenger_version_id, ml_model_id |
| `fk_rr_champion` | No | champion_version_id, ml_model_id |
| `fk_rr_model` | No | ml_model_id |
| `fk_rr_user` | No | reviewed_by |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_rr_challenger` | challenger_version_id, ml_model_id | [`ml_model_version`](#ml_model_version) (id, ml_model_id) |  |
| `fk_rr_champion` | champion_version_id, ml_model_id | [`ml_model_version`](#ml_model_version) (id, ml_model_id) |  |
| `fk_rr_model` | ml_model_id | [`ml_model`](#ml_model) (id) |  |
| `fk_rr_user` | reviewed_by | [`user`](#user) (id) |  |

**Referenced by:** [`evaluation_metric`](#evaluation_metric).retraining_run_id

---

## `audit_log`

> Append-only: the application DB user gets INSERT + SELECT only on this table (P21-03).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `actor_id` | `bigint` | YES |  | FK → `user.id` | NULL = system / AI worker |
| `entity_type` | `varchar(60)` | NO |  |  | Table name, e.g. safety_constraint, user_role_assignment |
| `entity_id` | `bigint` | YES |  |  | NULL when a refused attempt has no entity (e.g. failed login) |
| `action` | `ENUM(CREATE, UPDATE, DELETE, STATUS_CHANGE, SAFETY_EXCEPTION, REJECTED_ATTEMPT)` | NO |  |  |  |
| `before_state` | `json` | YES |  |  |  |
| `after_state` | `json` | YES |  |  |  |
| `occurred_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_al_states` | `(((action <> 'CREATE') or (before_state is null)) and ((action <> 'DELETE') or (after_state is null)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_al_user` | No | actor_id |
| `idx_al_entity` | No | entity_type, entity_id |
| `idx_al_time` | No | occurred_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_al_user` | actor_id | [`user`](#user) (id) |  |

---

## `notification_log`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `user_id` | `bigint` | NO |  | FK → `user.id` |  |
| `related_entity_type` | `ENUM(PLAN_COMPONENT, BERTHING_REQUEST, RECOMMENDATION, ENTRY_WINDOW_PROPOSAL, TUGBOAT, RETRAINING_RUN, LICENSE)` | NO |  |  |  |
| `related_entity_id` | `bigint` | NO |  |  |  |
| `type` | `ENUM(PLAN_ASSIGNMENT, PRE_EXECUTION_REMINDER, TUG_STILL_BUSY, ENTRY_WINDOW_READY, RECOMMENDATION_READY, PLAN_CANCELLED, RETRAINING_REVIEW_NEEDED, LICENSE_EXPIRY_WARNING, ACK_ESCALATION, YARD_RECOMMENDATION_READY, YARD_RECOMMENDATION_FAILED, DRIFT_ALERT)` | NO |  |  |  |
| `channel` | `ENUM(WEB, MOBILE)` | NO |  |  |  |
| `status` | `ENUM(SENT, FAILED, ACKNOWLEDGED)` | NO |  |  |  |
| `failure_reason` | `varchar(255)` | YES |  |  |  |
| `sent_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `acknowledged_at` | `datetime` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_nl_ack` | `((status = 'ACKNOWLEDGED') = (acknowledged_at is not null))` |
| `chk_nl_failed` | `((status = 'FAILED') = (failure_reason is not null))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `idx_nl_entity` | No | related_entity_type, related_entity_id |
| `idx_nl_user_status` | No | user_id, status |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_nl_user` | user_id | [`user`](#user) (id) |  |

---

## `user_device`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `user_id` | `bigint` | NO |  | FK → `user.id` |  |
| `device_id` | `varchar(100)` | NO |  |  |  |
| `platform` | `ENUM(ANDROID, IOS)` | NO |  |  |  |
| `app_version` | `varchar(20)` | NO |  |  |  |
| `last_seen_at` | `datetime` | YES |  |  |  |
| `revoked_at` | `datetime` | YES |  |  | Never deleted: set to revoke |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_user_device` | Yes | user_id, device_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| — | user_id | [`user`](#user) (id) |  |

---

## `part1_run`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `berthing_request_id` | `bigint` | NO |  | FK → `berthing_request.id` |  |
| `status` | `ENUM(QUEUED, RUNNING, DONE, FAILED, REPLACED)` | NO | `'QUEUED'` |  |  |
| `outcome` | `ENUM(WINDOWS_FOUND, NO_FEASIBLE_WINDOW, ENV_DATA_UNAVAILABLE)` | YES |  |  |  |
| `retry_count` | `tinyint` | NO | `0` |  | 0..3 |
| `entry_window_proposal_id` | `bigint` | YES |  | FK → `entry_window_proposal.id` | Set only when windows are found |
| `queued_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `started_at` | `datetime` | YES |  |  |  |
| `finished_at` | `datetime` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_p1r_retry` | `(retry_count BETWEEN 0 AND 3)` |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| — | berthing_request_id | [`berthing_request`](#berthing_request) (id) |  |
| — | entry_window_proposal_id | [`entry_window_proposal`](#entry_window_proposal) (id) |  |

---

## `recommendation_review`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `recommendation_id` | `bigint` | NO |  | PK · FK → `recommendation.id` |  |
| `user_id` | `bigint` | NO |  | PK · FK → `user.id` |  |
| `viewed_at` | `datetime` | NO | `CURRENT_TIMESTAMP` | PK |  |

**Composite primary key:** `recommendation_id`, `user_id`, `viewed_at`

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| — | recommendation_id | [`recommendation`](#recommendation) (id) |  |
| — | user_id | [`user`](#user) (id) |  |

---

## Original SQL

<details>
<summary>Show full DDL</summary>

```sql
CREATE TABLE `channel_waypoint` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `channel_id` bigint NOT NULL,
  `sequence_order` int NOT NULL,
  `label` varchar(60) NOT NULL COMMENT 'Waypoint name entered by admin, e.g. Fairway Buoy',
  `latitude` decimal(9,6) NOT NULL,
  `longitude` decimal(9,6) NOT NULL
);

CREATE TABLE `license_state` (
  `id` tinyint PRIMARY KEY DEFAULT 1,
  `token` text COMMENT 'Current signed licence token',
  `license_ref` bigint COMMENT 'SaaS license.id (no FK, other DB)',
  `plan_name` varchar(80),
  `max_terminals` int,
  `max_berths` int,
  `max_users` int,
  `license_expires_at` datetime,
  `token_expires_at` datetime,
  `port_status` ENUM ('ONBOARDING', 'ACTIVE', 'SUSPENDED', 'OFFBOARDED'),
  `license_status` ENUM ('PENDING', 'ACTIVE', 'SUSPENDED', 'EXPIRED', 'REVOKED'),
  `status_received_at` datetime,
  `last_check_in_attempt_at` datetime,
  `last_successful_check_in_at` datetime,
  `last_check_in_error` varchar(255),
  `max_seen_time` datetime COMMENT 'Clock-rollback guard',
  `mode` ENUM ('SETUP', 'FULL', 'READ_ONLY') NOT NULL DEFAULT 'READ_ONLY',
  `read_only_reason` ENUM ('NONE', 'NO_LICENSE', 'TOKEN_EXPIRED', 'SUSPENDED', 'REVOKED', 'INVALID_SIGNATURE', 'CLOCK_ROLLBACK', 'PORT_SUSPENDED', 'PORT_OFFBOARDED', 'LICENSE_NOT_YET_IN_EFFECT') NOT NULL DEFAULT 'NO_LICENSE',
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `navigation_channel` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(100) UNIQUE NOT NULL,
  `depth` decimal(6,2) NOT NULL COMMENT 'Controlling (minimum) charted depth, metres â€” input to UKC',
  `depth_datum` varchar(40) NOT NULL COMMENT 'e.g. LAT; must equal the active tide_constituent_set.datum_reference',
  `width` decimal(7,2) NOT NULL COMMENT 'metres â€” input to squat',
  `speed_limit_knots` decimal(4,1) NOT NULL COMMENT 'Max permitted transit speed, entered by admin; worst-case input to squat',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `port_profile` (
  `id` tinyint PRIMARY KEY DEFAULT 1,
  `public_id` char(36) UNIQUE NOT NULL COMMENT 'Same value as SaaS platform_port_registry.public_id (read-only)',
  `port_code` varchar(10) COMMENT 'UN/LOCODE, from SaaS',
  `governorate_code` varchar(6) NOT NULL COMMENT 'ISO 3166-2:EG, same codes as SaaS governorate',
  `name` varchar(150) NOT NULL,
  `latitude` decimal(9,6) NOT NULL COMMENT 'Reference point for weather/tide pulls',
  `longitude` decimal(9,6) NOT NULL,
  `timezone` varchar(64) NOT NULL COMMENT 'IANA, e.g. Africa/Cairo',
  `boundary_coordinates` json COMMENT 'GeoJSON Polygon, [lon, lat]',
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `port_setting` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `setting_key` varchar(80) UNIQUE NOT NULL COMMENT 'reminder_lead_minutes, weather_pull_hours, retrain_interval_days ...',
  `setting_value` varchar(255) NOT NULL,
  `value_type` ENUM ('INT', 'DECIMAL', 'BOOL', 'STRING', 'JSON') NOT NULL,
  `description` varchar(255),
  `updated_by` bigint,
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `berth` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(30) UNIQUE NOT NULL,
  `name` varchar(80),
  `length` decimal(7,2) NOT NULL COMMENT 'metres',
  `water_depth` decimal(6,2) NOT NULL COMMENT 'Water depth alongside the berth (m) below depth_datum',
  `depth_datum` varchar(40) NOT NULL COMMENT 'e.g. LAT; must equal navigation_channel.depth_datum and the active tide datum',
  `max_vessel_loa` decimal(7,2) NOT NULL COMMENT 'metres',
  `status` ENUM ('ACTIVE', 'MAINTENANCE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE' COMMENT 'Administrative only. Reserved/occupied is derived from plan_component and actual status',
  `latitude` decimal(9,6),
  `longitude` decimal(9,6)
);

CREATE TABLE `berth_terminal` (
  `berth_id` bigint NOT NULL,
  `terminal_id` bigint NOT NULL,
  PRIMARY KEY (`berth_id`, `terminal_id`)
);

CREATE TABLE `terminal` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(20) UNIQUE NOT NULL,
  `name` varchar(120) UNIQUE NOT NULL,
  `terminal_type` ENUM ('CONTAINER', 'NON_CONTAINER') NOT NULL,
  `boundary_coordinates` json COMMENT 'GeoJSON Polygon [lon, lat]; inside port boundary (app rule)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'All registered terminals (active or not) count against license max_terminals'
);

CREATE TABLE `organization_unit` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `unit_type` ENUM ('HARBOUR_AUTHORITY', 'MARINE_SERVICES', 'TERMINAL') NOT NULL,
  `terminal_id` bigint COMMENT 'Set only for TERMINAL units; display name comes from terminal.name',
  `single_key` bigint COMMENT 'GENERATED (stored): coalesce(terminal_id,0)'
);

CREATE TABLE `user` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `username` varchar(50) UNIQUE NOT NULL COMMENT 'Login name, stored lowercase',
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(30) COMMENT 'Contact only, not used for login',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Active users count against license max_users',
  `must_change_password` boolean NOT NULL DEFAULT false COMMENT '1 = one-time password in use; must change before any other action',
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `user_role_assignment` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `role` ENUM ('PORT_GM', 'PORT_STAFF', 'MARINE_SAFETY_OFFICER', 'PILOTAGE_MANAGER', 'PILOTAGE_OFFICER', 'MARINE_PILOT', 'TUG_MANAGER', 'TUG_OFFICER', 'TUG_MASTER', 'TERMINAL_ADMIN', 'TERMINAL_OFFICER', 'TERMINAL_SUPERVISOR') NOT NULL,
  `organization_unit_id` bigint NOT NULL COMMENT 'Terminal X, Marine Services, or Harbour Authority. Never empty.',
  `unit_type` ENUM ('HARBOUR_AUTHORITY', 'MARINE_SERVICES', 'TERMINAL') NOT NULL COMMENT 'Kept equal to organization_unit.unit_type by fk_ura_unit',
  `active_from` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `active_to` datetime COMMENT 'NULL = role active; set when the role is ended (never deleted)',
  `active_assignment_key` tinyint COMMENT 'GENERATED (stored): IF(active_to IS NULL, 1, NULL)'
);

CREATE TABLE `pilot_profile` (
  `user_id` bigint PRIMARY KEY,
  `pilot_rank` ENUM ('FIRST', 'SECOND', 'THIRD') NOT NULL COMMENT 'FIRST = most senior'
);

CREATE TABLE `pilot_rank_limit` (
  `pilot_rank` ENUM ('FIRST', 'SECOND', 'THIRD') PRIMARY KEY,
  `max_vessel_loa` decimal(7,2) COMMENT 'Largest vessel LOA (m) this rank may pilot; NULL = no limit'
);

CREATE TABLE `pilot_schedule` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `pilot_user_id` bigint NOT NULL,
  `shift_date` date NOT NULL COMMENT 'Port local date the shift starts on',
  `port_shift_id` bigint NOT NULL,
  `created_by` bigint NOT NULL
);

CREATE TABLE `port_shift` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(20) UNIQUE NOT NULL,
  `name` varchar(60) NOT NULL,
  `start_time` time NOT NULL COMMENT 'Port local time (port_profile.timezone)',
  `end_time` time NOT NULL COMMENT 'If end_time < start_time the shift ends the next day',
  `is_active` tinyint(1) NOT NULL DEFAULT 1
);

CREATE TABLE `tug_master_profile` (
  `user_id` bigint PRIMARY KEY,
  `assigned_tugboat_id` bigint UNIQUE COMMENT 'NULL = explicitly unassigned; UNIQUE = one master per tug (FR-1.1.2)'
);

CREATE TABLE `tug_master_schedule` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `tug_master_user_id` bigint NOT NULL,
  `shift_date` date NOT NULL COMMENT 'Port local date the shift starts on',
  `port_shift_id` bigint NOT NULL,
  `created_by` bigint NOT NULL
);

CREATE TABLE `tugboat` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(100) UNIQUE NOT NULL,
  `tug_power` decimal(8,2) NOT NULL,
  `tug_power_unit` ENUM ('BOLLARD_PULL_TONNES', 'HP', 'KW') NOT NULL COMMENT 'Chosen by the Tug Manager',
  `capacity` decimal(8,2) COMMENT 'Kept as in source (P4-03 rejected)',
  `status` ENUM ('AVAILABLE', 'BUSY', 'MAINTENANCE', 'INACTIVE') NOT NULL DEFAULT 'AVAILABLE' COMMENT 'BUSY for the plan duration; Tug Master sets it back to AVAILABLE from the tablet; Tug Officer notified if still BUSY after planned_end'
);

CREATE TABLE `container` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `container_number` char(11) UNIQUE NOT NULL COMMENT 'ISO 6346, uppercase, no spaces; check digit validated by parser (warning)',
  `size_type_id` bigint NOT NULL
);

CREATE TABLE `container_size_type` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` char(4) UNIQUE NOT NULL COMMENT 'ISO 6346 size-type code, e.g. 22G1, 45R1',
  `length_ft` int NOT NULL,
  `is_high_cube` tinyint(1) NOT NULL DEFAULT 0 COMMENT '9''6" high',
  `type_group` ENUM ('GENERAL', 'REEFER', 'TANK', 'OPEN_TOP', 'FLAT_RACK', 'VENTILATED', 'BULK') NOT NULL,
  `teu_factor` decimal(3,2) NOT NULL,
  `is_reefer` tinyint(1) COMMENT 'GENERATED (stored): (type_group = _latin1''REEFER'')'
);

CREATE TABLE `vessel` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `imo_number` char(7) UNIQUE NOT NULL COMMENT '7 digits, no "IMO" prefix; backend validates the check digit',
  `name` varchar(120) NOT NULL,
  `vessel_type_id` bigint NOT NULL,
  `loa` decimal(7,2) NOT NULL COMMENT 'Length overall, m',
  `beam` decimal(6,2) NOT NULL COMMENT 'Breadth, m. Needed for squat (blockage)',
  `max_draft` decimal(5,2) NOT NULL COMMENT 'Summer (max) draft, m; arrival_draft must not exceed it',
  `net_tonnage` int COMMENT 'NT. Nullable: not in BAPLIE; backend requires it at Preliminary Approval',
  `moulded_depth` decimal(5,2) COMMENT 'm. Nullable, same rule',
  `call_sign` varchar(10) COMMENT 'Nullable',
  `flag_state` char(2) COMMENT 'ISO 3166-1 alpha-2. Nullable'
);

CREATE TABLE `vessel_type` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(30) UNIQUE NOT NULL,
  `name` varchar(80) NOT NULL
);

CREATE TABLE `cargo_type` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(30) UNIQUE NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` ENUM ('GENERAL_CARGO', 'BREAK_BULK', 'DRY_BULK', 'LIQUID_BULK', 'GAS', 'RO_RO', 'CONTAINERIZED') NOT NULL,
  `imdg_class` varchar(3) COMMENT 'NULL = not dangerous',
  `min_temperature` decimal(5,2) COMMENT 'Â°C; NULL = no temperature requirement',
  `max_temperature` decimal(5,2) COMMENT 'Â°C; NULL = no temperature requirement'
);

CREATE TABLE `imdg_class` (
  `code` varchar(3) PRIMARY KEY COMMENT 'IMDG class / division, e.g. 3, 2.1',
  `name` varchar(80) NOT NULL
);

CREATE TABLE `storage_facility` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `terminal_id` bigint NOT NULL,
  `terminal_type` ENUM ('CONTAINER', 'NON_CONTAINER') NOT NULL COMMENT 'Copy of terminal.terminal_type, enforced by composite FK',
  `storage_type_id` bigint NOT NULL,
  `detail_kind` ENUM ('NONE', 'WAREHOUSE', 'CONTAINER_YARD', 'TANK') NOT NULL COMMENT 'Copy of storage_type.detail_kind, enforced by composite FK',
  `name` varchar(100) NOT NULL,
  `code` varchar(30) NOT NULL,
  `area` decimal(12,2) COMMENT 'm2',
  `max_floor_load` decimal(6,2) COMMENT 'Ground/floor weight limit, tonnes per m2 (open yard, warehouse); NULL = not applicable',
  `capacity` decimal(14,2) COMMENT 'NULL for CONTAINER_YARD (derived from yard_slot)',
  `capacity_unit` ENUM ('TONNES', 'CUBIC_METRES', 'SQUARE_METRES', 'TEU', 'UNITS') COMMENT 'NULL for CONTAINER_YARD',
  `current_occupancy` decimal(14,2) NOT NULL DEFAULT 0,
  `reserved_capacity` decimal(14,2) NOT NULL DEFAULT 0,
  `blocked_capacity` decimal(14,2) NOT NULL DEFAULT 0,
  `available_capacity` decimal(14,2) COMMENT 'GENERATED (stored): (((capacity - current_occupancy) - reserved_capacity) - blocked_capacity)',
  `status` ENUM ('ACTIVE', 'MAINTENANCE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE' COMMENT 'Administrative only; fullness comes from the capacity numbers',
  `latitude` decimal(9,6),
  `longitude` decimal(9,6)
);

CREATE TABLE `storage_facility_cargo_compatibility` (
  `storage_facility_id` bigint NOT NULL,
  `terminal_id` bigint NOT NULL,
  `cargo_type_id` bigint NOT NULL,
  PRIMARY KEY (`storage_facility_id`, `cargo_type_id`)
);

CREATE TABLE `storage_movement` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `storage_facility_id` bigint NOT NULL,
  `cargo_type_id` bigint NOT NULL,
  `direction` ENUM ('IN', 'OUT') NOT NULL,
  `quantity` decimal(14,2) NOT NULL COMMENT 'In the facility capacity_unit',
  `source` ENUM ('VESSEL_DISCHARGE', 'VESSEL_LOADING', 'MANUAL') NOT NULL,
  `plan_component_id` bigint COMMENT 'FK added in Module 18 (D-49); NULL for MANUAL',
  `occurred_at` datetime NOT NULL COMMENT 'UTC',
  `recorded_by` bigint COMMENT 'user.id (Module 3); NULL = recorded by the system'
);

CREATE TABLE `storage_type` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(30) UNIQUE NOT NULL,
  `name` varchar(80) NOT NULL,
  `detail_kind` ENUM ('NONE', 'WAREHOUSE', 'CONTAINER_YARD', 'TANK') NOT NULL DEFAULT 'NONE' COMMENT 'Which detail table facilities of this type may use'
);

CREATE TABLE `tank_detail` (
  `storage_facility_id` bigint PRIMARY KEY,
  `detail_kind` ENUM ('NONE', 'WAREHOUSE', 'CONTAINER_YARD', 'TANK') NOT NULL DEFAULT 'TANK' COMMENT 'Same ENUM as parent (FK compares ENUM index)',
  `min_temperature` decimal(5,2) COMMENT 'Â°C',
  `max_temperature` decimal(5,2) COMMENT 'Â°C',
  `max_pressure_bar` decimal(6,2) COMMENT 'Pressure limit (bar); NULL = atmospheric tank'
);

CREATE TABLE `terminal_cargo_type` (
  `terminal_id` bigint NOT NULL,
  `cargo_type_id` bigint NOT NULL,
  PRIMARY KEY (`terminal_id`, `cargo_type_id`)
);

CREATE TABLE `warehouse_detail` (
  `storage_facility_id` bigint PRIMARY KEY,
  `detail_kind` ENUM ('NONE', 'WAREHOUSE', 'CONTAINER_YARD', 'TANK') NOT NULL DEFAULT 'WAREHOUSE' COMMENT 'Same ENUM as parent (FK compares ENUM index)',
  `min_temperature` decimal(5,2) COMMENT 'Â°C',
  `max_temperature` decimal(5,2) COMMENT 'Â°C',
  `temperature_monitoring` tinyint(1) NOT NULL DEFAULT 0
);

CREATE TABLE `dg_zone_allowed_class` (
  `yard_zone_id` bigint NOT NULL,
  `zone_detail` ENUM ('NONE', 'REEFER', 'DANGEROUS_GOODS', 'OOG') NOT NULL DEFAULT 'DANGEROUS_GOODS',
  `imdg_class` varchar(3) NOT NULL,
  PRIMARY KEY (`yard_zone_id`, `imdg_class`)
);

CREATE TABLE `oog_zone_detail` (
  `yard_zone_id` bigint PRIMARY KEY,
  `zone_detail` ENUM ('NONE', 'REEFER', 'DANGEROUS_GOODS', 'OOG') NOT NULL DEFAULT 'OOG',
  `max_length` decimal(6,2) COMMENT 'm',
  `max_width` decimal(6,2) COMMENT 'm',
  `max_height` decimal(6,2) COMMENT 'm',
  `max_weight` decimal(8,2) COMMENT 'tonnes'
);

CREATE TABLE `reefer_zone_detail` (
  `yard_zone_id` bigint PRIMARY KEY,
  `zone_detail` ENUM ('NONE', 'REEFER', 'DANGEROUS_GOODS', 'OOG') NOT NULL DEFAULT 'REEFER',
  `total_power_plugs` int NOT NULL COMMENT 'Free plugs = total - reefer containers in the zone (derived)',
  `power_type` varchar(30) COMMENT 'Display only, e.g. 380-460V 3-phase'
);

CREATE TABLE `yard_block` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `yard_zone_id` bigint NOT NULL,
  `code` varchar(30) NOT NULL
);

CREATE TABLE `yard_slot` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `yard_block_id` bigint NOT NULL,
  `bay` int NOT NULL,
  `row_no` int NOT NULL,
  `tier` int NOT NULL,
  `status` ENUM ('ACTIVE', 'BLOCKED') NOT NULL DEFAULT 'ACTIVE' COMMENT 'Administrative only; occupied = container_id IS NOT NULL',
  `container_id` bigint UNIQUE
);

CREATE TABLE `yard_zone` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `container_yard_id` bigint NOT NULL COMMENT 'storage_facility.id of a CONTAINER_YARD',
  `detail_kind` ENUM ('NONE', 'WAREHOUSE', 'CONTAINER_YARD', 'TANK') NOT NULL DEFAULT 'CONTAINER_YARD' COMMENT 'Same ENUM as storage_facility (FK compares ENUM index)',
  `yard_zone_type_id` bigint NOT NULL,
  `zone_detail` ENUM ('NONE', 'REEFER', 'DANGEROUS_GOODS', 'OOG') NOT NULL COMMENT 'Copy of yard_zone_type.zone_detail, enforced by composite FK',
  `code` varchar(30) NOT NULL
);

CREATE TABLE `yard_zone_type` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(30) UNIQUE NOT NULL,
  `name` varchar(80) NOT NULL,
  `zone_detail` ENUM ('NONE', 'REEFER', 'DANGEROUS_GOODS', 'OOG') NOT NULL DEFAULT 'NONE' COMMENT 'Which detail table zones of this type use; chosen by the admin when adding a type'
);

CREATE TABLE `capability_type` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(60) UNIQUE NOT NULL,
  `name` varchar(100) NOT NULL,
  `unit` ENUM ('TONNES', 'METRES', 'TONNES_PER_HOUR', 'CUBIC_METRES_PER_HOUR', 'MOVES_PER_HOUR', 'COUNT', 'BAR') NOT NULL
);

CREATE TABLE `equipment` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `terminal_id` bigint NOT NULL,
  `equipment_category_id` bigint NOT NULL,
  `quantity` int NOT NULL COMMENT 'How many units of this type the terminal has',
  `maintenance_quantity` int NOT NULL DEFAULT 0,
  `blocked_quantity` int NOT NULL DEFAULT 0,
  `available_quantity` int COMMENT 'GENERATED (stored): ((quantity - maintenance_quantity) - blocked_quantity) | Usable units; units busy in a plan are derived from plans (Module 18)'
);

CREATE TABLE `equipment_capability` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `equipment_id` bigint NOT NULL,
  `capability_type_id` bigint NOT NULL,
  `capability_value` decimal(12,3) NOT NULL COMMENT 'In capability_type.unit'
);

CREATE TABLE `equipment_cargo_compatibility` (
  `equipment_id` bigint NOT NULL,
  `terminal_id` bigint NOT NULL,
  `cargo_type_id` bigint NOT NULL,
  PRIMARY KEY (`equipment_id`, `cargo_type_id`)
);

CREATE TABLE `equipment_category` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(40) UNIQUE NOT NULL,
  `name` varchar(100) NOT NULL,
  `equipment_role` ENUM ('VESSEL_HANDLING', 'CARGO_TRANSPORT', 'TRANSFER_SYSTEM') NOT NULL
);

CREATE TABLE `constraint_type` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(60) UNIQUE NOT NULL,
  `category` ENUM ('ENVIRONMENTAL', 'UKC', 'VESSEL_SAFETY') NOT NULL,
  `unit` ENUM ('KNOTS', 'METRES_PER_SECOND', 'METRES', 'NAUTICAL_MILES', 'PERCENT', 'DEGREES', 'MINUTES', 'TONNES') NOT NULL,
  `comparator` ENUM ('MAX_ALLOWED', 'MIN_REQUIRED') NOT NULL,
  `is_hard_safety_limit` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'TRUE = never modified by Part 3 / retraining (D12). Not the solver hard/soft choice (D13, deferred)',
  `description` varchar(255)
);

CREATE TABLE `safety_constraint` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `constraint_type_id` bigint NOT NULL,
  `terminal_id` bigint COMMENT 'NULL = port-wide',
  `berth_id` bigint COMMENT 'NULL = whole terminal / port; if set, terminal_id is the berth''s terminal',
  `vessel_type_id` bigint COMMENT 'NULL = all vessel types',
  `imdg_class` varchar(3) COMMENT 'NULL = any cargo',
  `equipment_category_id` bigint COMMENT 'NULL = not equipment-specific (e.g. cranes stop above a wind limit)',
  `value` decimal(12,3) NOT NULL COMMENT 'In constraint_type.unit',
  `created_by` bigint NOT NULL,
  `active_from` datetime NOT NULL COMMENT 'UTC',
  `active_to` datetime COMMENT 'UTC; NULL = open-ended'
);

CREATE TABLE `terminal_productivity_profile` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `terminal_id` bigint NOT NULL,
  `terminal_type` ENUM ('CONTAINER', 'NON_CONTAINER') NOT NULL COMMENT 'Copy of terminal.terminal_type, enforced by composite FK',
  `vessel_type_id` bigint COMMENT 'NULL = all vessel types (terminal default)',
  `productivity_rate` decimal(10,2) NOT NULL,
  `rate_unit` ENUM ('MOVES_PER_HOUR', 'TONNES_PER_HOUR', 'CUBIC_METRES_PER_HOUR', 'UNITS_PER_HOUR') NOT NULL,
  `fixed_overhead_minutes` int NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date
);

CREATE TABLE `berthing_request` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `vessel_id` bigint NOT NULL,
  `requested_terminal_id` bigint NOT NULL,
  `source` ENUM ('MANUAL', 'EXTERNAL') NOT NULL,
  `external_system_id` bigint COMMENT 'Required when EXTERNAL. Optional for MANUAL: staff enter the EGMSW trip number (seed external_system row EGMSW)',
  `external_reference` varchar(100) COMMENT 'Pairs with external_system_id (both set or both empty for MANUAL); unique per external system',
  `submitted_by` bigint COMMENT 'Required when MANUAL; NULL when ingested by the external adapter',
  `requested_eta` datetime NOT NULL COMMENT 'UTC',
  `arrival_draft` decimal(5,2) NOT NULL COMMENT 'm, this call; backend checks > 0 and <= vessel.max_draft',
  `departure_draft` decimal(5,2) COMMENT 'm, expected draft on departure. Nullable (not known at arrival)',
  `previous_port` char(5) COMMENT 'UN/LOCODE, nullable',
  `next_port` char(5) COMMENT 'UN/LOCODE, nullable',
  `draft_source` ENUM ('MANUAL', 'EGMSW_FEED', 'REGISTRY') NOT NULL DEFAULT 'MANUAL',
  `eta_source` ENUM ('MANUAL', 'EGMSW_FEED', 'REGISTRY') NOT NULL DEFAULT 'MANUAL',
  `ports_source` ENUM ('MANUAL', 'EGMSW_FEED', 'REGISTRY') NOT NULL DEFAULT 'MANUAL',
  `block_coefficient` decimal(4,3) NOT NULL COMMENT 'Cb entered on the request form (D-44)',
  `gross_tonnage` int NOT NULL COMMENT 'GT, entered on the request form; input to the AI tug formula (OQ-15-1)',
  `deadweight_tonnes` int NOT NULL COMMENT 'DWT (tonnes), entered on the request form; input to the AI tug formula (OQ-15-1)',
  `status` ENUM ('SUBMITTED', 'NEEDS_REVISION', 'PRELIMINARY_APPROVED', 'CUSTOMS_APPROVED', 'ENTRY_WINDOW_CONFIRMED', 'RECOMMENDATION_GENERATED', 'PLAN_CONFIRMED', 'PLAN_CANCELLED', 'COMPLETED', 'REJECTED', 'CANCELLED') NOT NULL DEFAULT 'SUBMITTED',
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `berthing_request_cargo` (
  `berthing_request_id` bigint NOT NULL,
  `terminal_id` bigint NOT NULL COMMENT 'Copy of the request terminal, enforced by composite FK',
  `cargo_type_id` bigint NOT NULL,
  `operation` ENUM ('DISCHARGE', 'LOAD') NOT NULL,
  `quantity` decimal(14,2) NOT NULL,
  `quantity_unit` ENUM ('TONNES', 'CUBIC_METRES', 'UNITS') NOT NULL,
  PRIMARY KEY (`berthing_request_id`, `cargo_type_id`, `operation`)
);

CREATE TABLE `berthing_request_status_history` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `berthing_request_id` bigint NOT NULL,
  `from_status` ENUM ('SUBMITTED', 'NEEDS_REVISION', 'PRELIMINARY_APPROVED', 'CUSTOMS_APPROVED', 'ENTRY_WINDOW_CONFIRMED', 'RECOMMENDATION_GENERATED', 'PLAN_CONFIRMED', 'PLAN_CANCELLED', 'COMPLETED', 'REJECTED', 'CANCELLED') COMMENT 'NULL on creation',
  `to_status` ENUM ('SUBMITTED', 'NEEDS_REVISION', 'PRELIMINARY_APPROVED', 'CUSTOMS_APPROVED', 'ENTRY_WINDOW_CONFIRMED', 'RECOMMENDATION_GENERATED', 'PLAN_CONFIRMED', 'PLAN_CANCELLED', 'COMPLETED', 'REJECTED', 'CANCELLED') NOT NULL,
  `changed_by` bigint COMMENT 'NULL = system / external adapter',
  `changed_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `note` varchar(500)
);

CREATE TABLE `external_system` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(30) UNIQUE NOT NULL,
  `name` varchar(100) NOT NULL
);

CREATE TABLE `baplie_file` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `vessel_id` bigint NOT NULL,
  `berthing_request_id` bigint NOT NULL COMMENT 'Must be a request for the same vessel (composite FK below)',
  `uploaded_by` bigint NOT NULL,
  `file_hash_sha256` char(64) NOT NULL COMMENT 'Not globally unique: the same file may be uploaded for a different request',
  `live_hash_key` char(64) COMMENT 'GENERATED (stored): IF(status = WITHDRAWN, NULL, file_hash_sha256)',
  `storage_path` varchar(255) NOT NULL,
  `parse_status` ENUM ('PENDING', 'PARSED', 'FAILED', 'QUARANTINED') NOT NULL DEFAULT 'PENDING',
  `status` ENUM ('STAGED', 'ACTIVE', 'REPLACED', 'WITHDRAWN') NOT NULL DEFAULT 'STAGED' COMMENT 'STAGED = uploaded, not yet in use; ACTIVE = in use; REPLACED = replaced by a revision; WITHDRAWN = uploaded by mistake',
  `active_request_key` bigint UNIQUE COMMENT 'GENERATED (stored): IF(status = ACTIVE, berthing_request_id, NULL). One ACTIVE file per request.',
  `staged_payload` json COMMENT 'Quarantine: extracted data of a QUARANTINED file, kept so staff can correct it. Commercially sensitive (terminal isolation rules)',
  `is_manually_corrected` boolean NOT NULL DEFAULT false,
  `corrected_by` bigint,
  `corrected_at` datetime,
  `uploaded_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `baplie_parse_issue` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `baplie_file_id` bigint NOT NULL,
  `severity` ENUM ('ERROR', 'WARNING') NOT NULL,
  `segment_ref` varchar(60),
  `message` varchar(500) NOT NULL
);

CREATE TABLE `container_manifest_entry` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `baplie_file_id` bigint NOT NULL,
  `container_id` bigint NOT NULL,
  `full_empty` ENUM ('FULL', 'EMPTY') COMMENT 'NULL = missing in BAPLIE; staff fixes it (FR-1.2.5). Backend blocks CUSTOMS_APPROVED and yard recommendations while any line is incomplete',
  `weight_kg` int COMMENT 'Gross weight (VGM), kg',
  `imdg_class` varchar(3) COMMENT 'NULL = not dangerous',
  `oog_over_front_cm` int COMMENT 'BAPLIE DIM overhang, cm; NULL = none. Overall size = standard size (container_size_type) + overhangs, compared with oog_zone_detail',
  `oog_over_back_cm` int,
  `oog_over_right_cm` int,
  `oog_over_left_cm` int,
  `oog_over_height_cm` int,
  `reefer_set_temperature` decimal(5,2) COMMENT 'Â°C. Reefer (container_size_type.is_reefer) with NULL here = data error flagged by the parser',
  `stowage_bay` int,
  `stowage_row` int COMMENT '00 = centre line',
  `stowage_tier` int,
  `port_of_load` char(5) COMMENT 'UN/LOCODE',
  `port_of_discharge` char(5) COMMENT 'UN/LOCODE; = port_profile.port_code means discharged here'
);

CREATE TABLE `environment_forecast_point` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `forecast_run_id` bigint NOT NULL,
  `valid_at` datetime NOT NULL,
  `wind_speed` decimal(5,2) COMMENT 'Unit: forecast_run.wind_speed_unit',
  `wind_gust` decimal(5,2) COMMENT 'Unit: forecast_run.wind_speed_unit',
  `wind_direction_deg` decimal(4,1) COMMENT 'Direction the wind comes from, degrees true',
  `visibility` decimal(8,2) COMMENT 'Unit: forecast_run.visibility_unit',
  `wave_height` decimal(4,2) COMMENT 'm (significant)',
  `wave_period` decimal(4,1) COMMENT 's',
  `wave_direction_deg` decimal(4,1) COMMENT 'Direction the waves come from, degrees true',
  `current_speed` decimal(4,2) COMMENT 'Unit: forecast_run.current_speed_unit',
  `sea_level` decimal(5,3) COMMENT 'm above the active tide datum'
);

CREATE TABLE `forecast_run` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `source` varchar(80) NOT NULL COMMENT 'Weather provider',
  `pull_type` ENUM ('SCHEDULED', 'ON_DEMAND', 'MANUAL') NOT NULL,
  `provider_rank` ENUM ('PRIMARY', 'SECONDARY', 'MANUAL') COMMENT 'Which provider supplied the data',
  `data_stale` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = latest data older than the allowed age',
  `berthing_request_id` bigint COMMENT 'Set only for ON_DEMAND (Customs Approval)',
  `submitted_by` bigint COMMENT 'Required when pull_type = MANUAL',
  `status` ENUM ('SUCCESS', 'FAILED') NOT NULL,
  `pulled_at` datetime NOT NULL,
  `horizon_start` datetime,
  `horizon_end` datetime,
  `wind_speed_unit` ENUM ('KNOTS', 'METRES_PER_SECOND', 'KM_PER_HOUR') NOT NULL COMMENT 'Unit of wind_speed / wind_gust as sent by the provider',
  `current_speed_unit` ENUM ('KNOTS', 'METRES_PER_SECOND', 'KM_PER_HOUR') NOT NULL,
  `visibility_unit` ENUM ('NAUTICAL_MILES', 'KILOMETRES', 'METRES') NOT NULL
);

CREATE TABLE `tide_constituent` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `constituent_set_id` bigint NOT NULL,
  `constituent_code` varchar(10) NOT NULL COMMENT 'M2, S2, K1, O1 ...',
  `amplitude` decimal(8,4) NOT NULL COMMENT 'm',
  `phase_lag` decimal(7,3) NOT NULL COMMENT 'degrees',
  `speed_deg_per_hour` decimal(10,6) NOT NULL
);

CREATE TABLE `tide_constituent_set` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `version_label` varchar(40) UNIQUE NOT NULL,
  `datum_reference` varchar(40) NOT NULL COMMENT 'Must equal navigation_channel / berth depth_datum (backend check on activation)',
  `valid_from` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `active_key` tinyint UNIQUE COMMENT 'GENERATED (stored): if(is_active,1,NULL)'
);

CREATE TABLE `tide_prediction_point` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `tide_prediction_run_id` bigint NOT NULL,
  `valid_at` datetime NOT NULL,
  `height` decimal(5,3) NOT NULL COMMENT 'm above datum_reference'
);

CREATE TABLE `tide_prediction_run` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `constituent_set_id` bigint NOT NULL,
  `computed_at` datetime NOT NULL,
  `horizon_start` datetime NOT NULL,
  `horizon_end` datetime NOT NULL
);

CREATE TABLE `ml_model` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `model_key` ENUM ('M2_SERVICE_TIME', 'M3_DEMAND_FORECAST', 'M4_DELAY_RISK', 'FORECAST_BIAS_CORRECTION') UNIQUE NOT NULL,
  `algorithm_family` varchar(40) NOT NULL,
  `description` varchar(255)
);

CREATE TABLE `ml_model_version` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `ml_model_id` bigint NOT NULL,
  `version_no` int NOT NULL,
  `status` ENUM ('CHALLENGER', 'CHAMPION', 'ARCHIVED', 'REJECTED') NOT NULL DEFAULT 'CHALLENGER',
  `champion_key` bigint UNIQUE COMMENT 'GENERATED (stored): if((status = _latin1''CHAMPION''),ml_model_id,NULL)',
  `hyperparameters` json,
  `training_window_start` datetime,
  `training_window_end` datetime,
  `training_row_count` int,
  `artifact_path` varchar(255) NOT NULL COMMENT 'Local file volume only (D8)',
  `trained_at` datetime NOT NULL,
  `deployed_at` datetime,
  `retired_at` datetime
);

CREATE TABLE `blocked_period` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `proposal_id` bigint NOT NULL,
  `period_start` datetime NOT NULL,
  `period_end` datetime NOT NULL,
  `limiting_constraint_type_id` bigint NOT NULL
);

CREATE TABLE `demand_forecast_slot` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `generated_at` datetime NOT NULL,
  `slot_start` datetime NOT NULL,
  `slot_end` datetime NOT NULL,
  `expected_arrivals` int NOT NULL,
  `congestion_score` decimal(4,3) NOT NULL,
  `method` ENUM ('AGGREGATION', 'LIGHTGBM') NOT NULL,
  `model_version_id` bigint COMMENT 'NULL in Phase 1 (aggregation)'
);

CREATE TABLE `entry_window_proposal` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `berthing_request_id` bigint NOT NULL,
  `forecast_run_id` bigint NOT NULL,
  `tide_prediction_run_id` bigint NOT NULL,
  `bias_correction_version_id` bigint,
  `peak_congestion_score` decimal(4,3),
  `status` ENUM ('PROPOSED', 'CONFIRMED', 'REPLACED', 'REJECTED') NOT NULL DEFAULT 'PROPOSED',
  `confirmed_key` bigint UNIQUE COMMENT 'GENERATED (stored): if((status = ''CONFIRMED''),berthing_request_id,NULL)',
  `generated_at` datetime NOT NULL,
  `confirmed_window_id` bigint,
  `confirmed_by` bigint,
  `confirmed_at` datetime,
  `rejection_reason` varchar(500) COMMENT 'Required when REJECTED (all windows rejected)',
  `rejected_by` bigint,
  `rejected_at` datetime
);

CREATE TABLE `feasible_window` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `proposal_id` bigint NOT NULL,
  `window_start` datetime NOT NULL,
  `window_end` datetime NOT NULL,
  `confidence` decimal(4,3)
);

CREATE TABLE `rationale_template` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(60) NOT NULL,
  `version` int NOT NULL,
  `template_text` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `active_code` varchar(60) UNIQUE COMMENT 'GENERATED (stored): if(is_active,code,NULL)'
);

CREATE TABLE `recommendation` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `solver_run_id` bigint NOT NULL,
  `berthing_request_id` bigint NOT NULL,
  `terminal_id` bigint NOT NULL COMMENT 'Copy of the requested terminal, enforced by composite FK',
  `entry_window_proposal_id` bigint NOT NULL COMMENT 'Confirmed Part 1 input of the same request',
  `status` ENUM ('PENDING_REVIEW', 'CONFIRMED', 'REJECTED', 'INVALIDATED', 'REPLACED', 'MODIFIED_BY_STAFF') NOT NULL DEFAULT 'PENDING_REVIEW',
  `source` ENUM ('AI', 'FIFO') NOT NULL DEFAULT 'AI' COMMENT 'FIFO = fallback after a solver technical failure',
  `purpose` ENUM ('INITIAL', 'DELAY_REPLAN') NOT NULL DEFAULT 'INITIAL',
  `active_key` bigint UNIQUE COMMENT 'GENERATED (stored): IF(status IN (PENDING_REVIEW, MODIFIED_BY_STAFF, CONFIRMED) AND purpose = INITIAL, berthing_request_id, NULL)',
  `generated_at` datetime NOT NULL
);

CREATE TABLE `recommendation_option` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `recommendation_id` bigint NOT NULL,
  `terminal_id` bigint NOT NULL COMMENT 'Copy, enforced by composite FKs: berth must be allowed for the requested terminal (berth_terminal)',
  `option_rank` int NOT NULL,
  `berth_id` bigint NOT NULL,
  `planned_start` datetime NOT NULL,
  `planned_end` datetime NOT NULL,
  `pilot_user_id` bigint,
  `service_time_method` ENUM ('FORMULA', 'ML') NOT NULL,
  `service_time_p50_hours` decimal(6,2) NOT NULL,
  `service_time_p90_hours` decimal(6,2) COMMENT 'ML only',
  `delay_risk` decimal(4,3),
  `objective_score` decimal(14,4),
  `rationale_template_id` bigint
);

CREATE TABLE `recommendation_option_tug` (
  `option_id` bigint NOT NULL,
  `tugboat_id` bigint NOT NULL,
  PRIMARY KEY (`option_id`, `tugboat_id`)
);

CREATE TABLE `solver_run` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `trigger_type` ENUM ('NEW_REQUEST', 'REPLAN', 'RESOURCE_CHANGE', 'MANUAL') NOT NULL,
  `triggered_by` bigint,
  `horizon_start` datetime NOT NULL,
  `horizon_end` datetime NOT NULL,
  `status` ENUM ('QUEUED', 'RUNNING', 'OPTIMAL', 'FEASIBLE', 'INFEASIBLE', 'ERROR') NOT NULL DEFAULT 'QUEUED',
  `objective_value` decimal(14,4),
  `solve_time_ms` int,
  `solver_version` varchar(40) NOT NULL,
  `result_detail` json COMMENT 'INFEASIBLE: binding constraints; ERROR: error message',
  `queued_at` datetime NOT NULL,
  `started_at` datetime,
  `finished_at` datetime
);

CREATE TABLE `solver_run_model_version` (
  `solver_run_id` bigint NOT NULL,
  `model_version_id` bigint NOT NULL,
  PRIMARY KEY (`solver_run_id`, `model_version_id`)
);

CREATE TABLE `objective_term` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(40) UNIQUE NOT NULL,
  `name` varchar(100) NOT NULL
);

CREATE TABLE `recommendation_override` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `recommendation_option_id` bigint NOT NULL,
  `component_type` ENUM ('BERTH', 'PILOT', 'TUG', 'START_TIME') NOT NULL,
  `original_berth_id` bigint,
  `override_berth_id` bigint,
  `original_pilot_user_id` bigint,
  `override_pilot_user_id` bigint,
  `original_tugboat_id` bigint COMMENT 'NULL = tug added',
  `override_tugboat_id` bigint COMMENT 'NULL = tug removed',
  `original_start` datetime,
  `override_start` datetime COMMENT 'New end is computed: override_start + option service_time_p50_hours (P16-03)',
  `override_reason` varchar(500) NOT NULL,
  `overridden_by` bigint NOT NULL,
  `overridden_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `recommendation_rationale_factor` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `option_id` bigint NOT NULL,
  `factor_type` ENUM ('CONSTRAINT', 'OBJECTIVE_TERM') NOT NULL,
  `constraint_type_id` bigint,
  `objective_term_id` bigint,
  `factor_value` decimal(14,4),
  `display_order` int NOT NULL DEFAULT 0
);

CREATE TABLE `storage_allocation_item` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `terminal_recommendation_id` bigint NOT NULL,
  `rec_type` ENUM ('TIMING', 'YARD_PLACEMENT', 'STORAGE_ALLOCATION') NOT NULL DEFAULT 'STORAGE_ALLOCATION',
  `storage_facility_id` bigint NOT NULL,
  `cargo_type_id` bigint NOT NULL,
  `quantity` decimal(14,2) NOT NULL,
  `quantity_unit` ENUM ('TONNES', 'CUBIC_METRES', 'SQUARE_METRES', 'TEU', 'UNITS') NOT NULL COMMENT 'Must equal the facility capacity_unit (composite FK)'
);

CREATE TABLE `terminal_recommendation` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `recommendation_id` bigint NOT NULL,
  `terminal_id` bigint NOT NULL,
  `terminal_type` ENUM ('CONTAINER', 'NON_CONTAINER') NOT NULL COMMENT 'Copy, enforced by composite FK',
  `rec_type` ENUM ('TIMING', 'YARD_PLACEMENT', 'STORAGE_ALLOCATION') NOT NULL,
  `window_start` datetime,
  `window_end` datetime,
  `decision` ENUM ('PENDING', 'ACCEPTED', 'REJECTED', 'ADJUSTED') NOT NULL DEFAULT 'PENDING',
  `decided_by` bigint,
  `decided_at` datetime,
  `decision_note` varchar(500),
  `lifecycle_status` ENUM ('YARD_REC_REQUESTED', 'YARD_REC_GENERATED', 'GENERATION_FAILED', 'YARD_REC_ACCEPTED', 'YARD_REC_REJECTED', 'EXPORTED_TO_TOS') COMMENT 'YARD_PLACEMENT only',
  `failure_reason` varchar(500),
  `exported_at` datetime,
  `exported_by` bigint,
  `rationale_template_id` bigint,
  `confidence` decimal(4,3)
);

CREATE TABLE `yard_placement_item` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `terminal_recommendation_id` bigint NOT NULL,
  `rec_type` ENUM ('TIMING', 'YARD_PLACEMENT', 'STORAGE_ALLOCATION') NOT NULL DEFAULT 'YARD_PLACEMENT',
  `manifest_entry_id` bigint NOT NULL,
  `yard_block_id` bigint NOT NULL,
  `yard_slot_id` bigint COMMENT 'Optional; coordination level is block (FR-2.2.3)'
);

CREATE TABLE `operational_plan` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `berthing_request_id` bigint NOT NULL,
  `recommendation_id` bigint NOT NULL COMMENT 'Enforces option <-> recommendation <-> request consistency',
  `recommendation_option_id` bigint NOT NULL COMMENT 'The confirmed (selected) option',
  `status` ENUM ('CONFIRMED', 'CANCELLED', 'COMPLETED') NOT NULL DEFAULT 'CONFIRMED',
  `active_key` bigint UNIQUE COMMENT 'GENERATED (stored): IF(status = CONFIRMED, berthing_request_id, NULL)',
  `confirmed_by` bigint NOT NULL,
  `confirmed_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `plan_component` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `operational_plan_id` bigint NOT NULL,
  `component_type` ENUM ('BERTH', 'PILOT', 'TUG', 'TERMINAL_OPS') NOT NULL,
  `berth_id` bigint,
  `tugboat_id` bigint,
  `terminal_id` bigint,
  `assigned_user_id` bigint COMMENT 'Pilot / Tug Master / terminal supervisor (role checked by backend, D-33)',
  `assigned_by` bigint COMMENT 'Dept. Officer (FR-1.5.1/2)',
  `assigned_at` datetime,
  `planned_start` datetime NOT NULL,
  `planned_end` datetime NOT NULL,
  `reservation_state` ENUM ('RESERVED', 'RELEASED', 'CONSUMED') NOT NULL DEFAULT 'RESERVED' COMMENT 'RELEASED on plan cancellation'
);

CREATE TABLE `actual_status_update` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `client_event_id` char(36) UNIQUE NOT NULL COMMENT 'UUID generated on the device; duplicates from offline re-sends are ignored',
  `plan_component_id` bigint NOT NULL,
  `component_type` ENUM ('BERTH', 'PILOT', 'TUG', 'TERMINAL_OPS') NOT NULL COMMENT 'Copy of the component type, enforced by composite FK',
  `reported_by` bigint NOT NULL,
  `status` varchar(30) NOT NULL COMMENT 'Must be a status of this component type (status_type)',
  `reported_at` datetime NOT NULL COMMENT 'Time on the ground (device)',
  `client_sent_at` datetime COMMENT 'Device clock when the batch was sent',
  `received_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP) COMMENT 'Server receipt (offline sync)',
  `clock_skew_suspect` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = received_at - client_sent_at > 5 min; excluded from evaluation',
  `sync_source` ENUM ('MOBILE', 'TABLET', 'WEB', 'EXTERNAL') NOT NULL
);

CREATE TABLE `deviation_log` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `plan_component_id` bigint NOT NULL,
  `status_update_id` bigint,
  `deviation_type` ENUM ('DELAY', 'RESOURCE_CHANGED', 'STATE_MISMATCH') NOT NULL,
  `planned_value` varchar(255) NOT NULL,
  `actual_value` varchar(255) NOT NULL,
  `delay_minutes` int,
  `deviation_reason` varchar(500),
  `detected_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `status_type` (
  `component_type` ENUM ('BERTH', 'PILOT', 'TUG', 'TERMINAL_OPS') NOT NULL COMMENT 'Same ENUM as plan_component.component_type',
  `code` varchar(30) NOT NULL,
  `name` varchar(80) NOT NULL,
  PRIMARY KEY (`component_type`, `code`)
);

CREATE TABLE `drift_measurement` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `model_version_id` bigint NOT NULL,
  `metric_type` ENUM ('PSI', 'ROLLING_MAE') NOT NULL,
  `feature_name` varchar(60) COMMENT 'Required for PSI',
  `metric_value` decimal(14,5) NOT NULL,
  `threshold` decimal(14,5) NOT NULL,
  `is_drifted` tinyint(1) COMMENT 'GENERATED (stored): (metric_value > threshold)',
  `measured_at` datetime NOT NULL
);

CREATE TABLE `evaluation_metric` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `model_version_id` bigint COMMENT 'NULL for system-level metrics',
  `retraining_run_id` bigint,
  `metric_name` ENUM ('MAE', 'OVERRIDE_RATE', 'WAIT_TIME', 'WINDOW_ACCURACY') NOT NULL,
  `dataset_type` ENUM ('HOLDOUT', 'ROLLING', 'PRODUCTION') NOT NULL,
  `metric_value` decimal(14,5) NOT NULL,
  `period_start` datetime NOT NULL,
  `period_end` datetime NOT NULL,
  `computed_at` datetime NOT NULL
);

CREATE TABLE `retraining_run` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `ml_model_id` bigint NOT NULL,
  `champion_version_id` bigint COMMENT 'NULL at cold start',
  `challenger_version_id` bigint NOT NULL,
  `started_at` datetime NOT NULL,
  `finished_at` datetime,
  `decision` ENUM ('PROMOTED', 'KEPT_CHAMPION', 'FAILED') NOT NULL,
  `decision_reason` varchar(500),
  `review_pending` tinyint(1) COMMENT 'GENERATED (stored): ((decision in (_latin1''KEPT_CHAMPION'',_latin1''FAILED'')) and (reviewed_at is null)) | TRUE until someone reviews a kept-champion or failed run (D12, P20-02)',
  `reviewed_by` bigint,
  `reviewed_at` datetime,
  `review_note` varchar(500)
);

CREATE TABLE `audit_log` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `actor_id` bigint COMMENT 'NULL = system / AI worker',
  `entity_type` varchar(60) NOT NULL COMMENT 'Table name, e.g. safety_constraint, user_role_assignment',
  `entity_id` bigint COMMENT 'NULL when a refused attempt has no entity (e.g. failed login)',
  `action` ENUM ('CREATE', 'UPDATE', 'DELETE', 'STATUS_CHANGE', 'SAFETY_EXCEPTION', 'REJECTED_ATTEMPT') NOT NULL,
  `before_state` json,
  `after_state` json,
  `occurred_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `notification_log` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `related_entity_type` ENUM ('PLAN_COMPONENT', 'BERTHING_REQUEST', 'RECOMMENDATION', 'ENTRY_WINDOW_PROPOSAL', 'TUGBOAT', 'RETRAINING_RUN', 'LICENSE') NOT NULL,
  `related_entity_id` bigint NOT NULL,
  `type` ENUM ('PLAN_ASSIGNMENT', 'PRE_EXECUTION_REMINDER', 'TUG_STILL_BUSY', 'ENTRY_WINDOW_READY', 'RECOMMENDATION_READY', 'PLAN_CANCELLED', 'RETRAINING_REVIEW_NEEDED', 'LICENSE_EXPIRY_WARNING', 'ACK_ESCALATION', 'YARD_RECOMMENDATION_READY', 'YARD_RECOMMENDATION_FAILED', 'DRIFT_ALERT') NOT NULL,
  `channel` ENUM ('WEB', 'MOBILE') NOT NULL,
  `status` ENUM ('SENT', 'FAILED', 'ACKNOWLEDGED') NOT NULL,
  `failure_reason` varchar(255),
  `sent_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `acknowledged_at` datetime
);

CREATE TABLE `user_device` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `device_id` varchar(100) NOT NULL,
  `platform` ENUM ('ANDROID', 'IOS') NOT NULL,
  `app_version` varchar(20) NOT NULL,
  `last_seen_at` datetime,
  `revoked_at` datetime COMMENT 'Never deleted: set to revoke'
);

CREATE TABLE `part1_run` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `berthing_request_id` bigint NOT NULL,
  `status` ENUM ('QUEUED', 'RUNNING', 'DONE', 'FAILED', 'REPLACED') NOT NULL DEFAULT 'QUEUED',
  `outcome` ENUM ('WINDOWS_FOUND', 'NO_FEASIBLE_WINDOW', 'ENV_DATA_UNAVAILABLE'),
  `retry_count` tinyint NOT NULL DEFAULT 0 COMMENT '0..3',
  `entry_window_proposal_id` bigint COMMENT 'Set only when windows are found',
  `queued_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `started_at` datetime,
  `finished_at` datetime
);

CREATE TABLE `recommendation_review` (
  `recommendation_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `viewed_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  PRIMARY KEY (`recommendation_id`, `user_id`, `viewed_at`)
);

CREATE UNIQUE INDEX `uq_channel_waypoint_label` ON `channel_waypoint` (`channel_id`, `label`);

CREATE UNIQUE INDEX `uq_channel_waypoint_order` ON `channel_waypoint` (`channel_id`, `sequence_order`);

CREATE INDEX `fk_port_setting_updated_by` ON `port_setting` (`updated_by`);

CREATE INDEX `idx_berth_status` ON `berth` (`status`);

CREATE INDEX `idx_bt_terminal` ON `berth_terminal` (`terminal_id`);

CREATE UNIQUE INDEX `uq_terminal_id_type` ON `terminal` (`id`, `terminal_type`);

CREATE INDEX `fk_org_unit_terminal` ON `organization_unit` (`terminal_id`);

CREATE UNIQUE INDEX `uq_org_unit_id_type` ON `organization_unit` (`id`, `unit_type`);

CREATE UNIQUE INDEX `uq_org_unit_type_key` ON `organization_unit` (`unit_type`, `single_key`);

CREATE INDEX `fk_ura_unit` ON `user_role_assignment` (`organization_unit_id`, `unit_type`);

CREATE INDEX `idx_ura_unit` ON `user_role_assignment` (`organization_unit_id`);

CREATE UNIQUE INDEX `uq_ura_active` ON `user_role_assignment` (`user_id`, `role`, `organization_unit_id`, `active_assignment_key`);

CREATE INDEX `fk_pilot_schedule_created_by` ON `pilot_schedule` (`created_by`);

CREATE INDEX `fk_pilot_schedule_shift` ON `pilot_schedule` (`port_shift_id`);

CREATE INDEX `idx_pilot_schedule_date` ON `pilot_schedule` (`shift_date`, `port_shift_id`);

CREATE UNIQUE INDEX `uq_pilot_schedule` ON `pilot_schedule` (`pilot_user_id`, `shift_date`, `port_shift_id`);

CREATE INDEX `fk_tms_created_by` ON `tug_master_schedule` (`created_by`);

CREATE INDEX `fk_tms_shift` ON `tug_master_schedule` (`port_shift_id`);

CREATE INDEX `idx_tug_master_schedule_date` ON `tug_master_schedule` (`shift_date`, `port_shift_id`);

CREATE UNIQUE INDEX `uq_tug_master_schedule` ON `tug_master_schedule` (`tug_master_user_id`, `shift_date`, `port_shift_id`);

CREATE INDEX `idx_container_size_type` ON `container` (`size_type_id`);

CREATE INDEX `fk_ct_imdg` ON `cargo_type` (`imdg_class`);

CREATE INDEX `fk_sf_storage_type` ON `storage_facility` (`storage_type_id`, `detail_kind`);

CREATE INDEX `fk_sf_terminal` ON `storage_facility` (`terminal_id`, `terminal_type`);

CREATE INDEX `idx_sf_terminal_status` ON `storage_facility` (`terminal_id`, `status`);

CREATE UNIQUE INDEX `uq_sf_id_kind` ON `storage_facility` (`id`, `detail_kind`);

CREATE UNIQUE INDEX `uq_sf_id_terminal` ON `storage_facility` (`id`, `terminal_id`);

CREATE UNIQUE INDEX `uq_sf_id_unit` ON `storage_facility` (`id`, `capacity_unit`);

CREATE UNIQUE INDEX `uq_sf_terminal_code` ON `storage_facility` (`terminal_id`, `code`);

CREATE INDEX `fk_sfcc_facility` ON `storage_facility_cargo_compatibility` (`storage_facility_id`, `terminal_id`);

CREATE INDEX `fk_sfcc_terminal_cargo` ON `storage_facility_cargo_compatibility` (`terminal_id`, `cargo_type_id`);

CREATE INDEX `fk_sm_compatible` ON `storage_movement` (`storage_facility_id`, `cargo_type_id`);

CREATE INDEX `fk_sm_plan_component` ON `storage_movement` (`plan_component_id`);

CREATE INDEX `fk_sm_user` ON `storage_movement` (`recorded_by`);

CREATE INDEX `idx_sm_facility_time` ON `storage_movement` (`storage_facility_id`, `occurred_at`);

CREATE UNIQUE INDEX `uq_storage_type_id_kind` ON `storage_type` (`id`, `detail_kind`);

CREATE INDEX `fk_td_facility` ON `tank_detail` (`storage_facility_id`, `detail_kind`);

CREATE INDEX `fk_tct_cargo_type` ON `terminal_cargo_type` (`cargo_type_id`);

CREATE INDEX `fk_wd_facility` ON `warehouse_detail` (`storage_facility_id`, `detail_kind`);

CREATE INDEX `fk_dzac_imdg` ON `dg_zone_allowed_class` (`imdg_class`);

CREATE INDEX `fk_dzac_zone` ON `dg_zone_allowed_class` (`yard_zone_id`, `zone_detail`);

CREATE INDEX `fk_ozd_zone` ON `oog_zone_detail` (`yard_zone_id`, `zone_detail`);

CREATE INDEX `fk_rzd_zone` ON `reefer_zone_detail` (`yard_zone_id`, `zone_detail`);

CREATE UNIQUE INDEX `uq_yb_zone_code` ON `yard_block` (`yard_zone_id`, `code`);

CREATE UNIQUE INDEX `uq_ys_id_block` ON `yard_slot` (`id`, `yard_block_id`);

CREATE UNIQUE INDEX `uq_ys_position` ON `yard_slot` (`yard_block_id`, `bay`, `row_no`, `tier`);

CREATE INDEX `fk_yz_type` ON `yard_zone` (`yard_zone_type_id`, `zone_detail`);

CREATE INDEX `fk_yz_yard` ON `yard_zone` (`container_yard_id`, `detail_kind`);

CREATE UNIQUE INDEX `uq_yz_id_detail` ON `yard_zone` (`id`, `zone_detail`);

CREATE UNIQUE INDEX `uq_yz_yard_code` ON `yard_zone` (`container_yard_id`, `code`);

CREATE UNIQUE INDEX `uq_yzt_id_detail` ON `yard_zone_type` (`id`, `zone_detail`);

CREATE INDEX `idx_eq_category` ON `equipment` (`equipment_category_id`);

CREATE UNIQUE INDEX `uq_eq_id_terminal` ON `equipment` (`id`, `terminal_id`);

CREATE UNIQUE INDEX `uq_eq_terminal_category` ON `equipment` (`terminal_id`, `equipment_category_id`);

CREATE INDEX `fk_ecap_type` ON `equipment_capability` (`capability_type_id`);

CREATE UNIQUE INDEX `uq_ecap_equipment_type` ON `equipment_capability` (`equipment_id`, `capability_type_id`);

CREATE INDEX `fk_ecc_equipment` ON `equipment_cargo_compatibility` (`equipment_id`, `terminal_id`);

CREATE INDEX `fk_ecc_terminal_cargo` ON `equipment_cargo_compatibility` (`terminal_id`, `cargo_type_id`);

CREATE INDEX `fk_sc_berth_terminal` ON `safety_constraint` (`berth_id`, `terminal_id`);

CREATE INDEX `fk_sc_eqcat` ON `safety_constraint` (`equipment_category_id`);

CREATE INDEX `fk_sc_imdg` ON `safety_constraint` (`imdg_class`);

CREATE INDEX `fk_sc_terminal` ON `safety_constraint` (`terminal_id`);

CREATE INDEX `fk_sc_user` ON `safety_constraint` (`created_by`);

CREATE INDEX `fk_sc_vtype` ON `safety_constraint` (`vessel_type_id`);

CREATE INDEX `idx_sc_lookup` ON `safety_constraint` (`constraint_type_id`, `terminal_id`, `berth_id`, `vessel_type_id`, `active_from`);

CREATE INDEX `fk_tpp_terminal` ON `terminal_productivity_profile` (`terminal_id`, `terminal_type`);

CREATE INDEX `fk_tpp_vtype` ON `terminal_productivity_profile` (`vessel_type_id`);

CREATE INDEX `idx_tpp_lookup` ON `terminal_productivity_profile` (`terminal_id`, `vessel_type_id`, `valid_from`);

CREATE INDEX `fk_br_user` ON `berthing_request` (`submitted_by`);

CREATE INDEX `fk_br_vessel` ON `berthing_request` (`vessel_id`);

CREATE INDEX `idx_br_status` ON `berthing_request` (`status`);

CREATE INDEX `idx_br_terminal_status` ON `berthing_request` (`requested_terminal_id`, `status`);

CREATE UNIQUE INDEX `uq_br_external_ref` ON `berthing_request` (`external_system_id`, `external_reference`);

CREATE UNIQUE INDEX `uq_br_id_terminal` ON `berthing_request` (`id`, `requested_terminal_id`);

CREATE UNIQUE INDEX `uq_br_id_vessel` ON `berthing_request` (`id`, `vessel_id`);

CREATE INDEX `idx_br_vessel_eta` ON `berthing_request` (`vessel_id`, `requested_eta`);

CREATE INDEX `fk_brc_request` ON `berthing_request_cargo` (`berthing_request_id`, `terminal_id`);

CREATE INDEX `fk_brc_terminal_cargo` ON `berthing_request_cargo` (`terminal_id`, `cargo_type_id`);

CREATE INDEX `fk_brsh_user` ON `berthing_request_status_history` (`changed_by`);

CREATE INDEX `idx_brsh_request` ON `berthing_request_status_history` (`berthing_request_id`, `changed_at`);

CREATE INDEX `fk_bf_request` ON `baplie_file` (`berthing_request_id`, `vessel_id`);

CREATE INDEX `fk_bf_user` ON `baplie_file` (`uploaded_by`);

CREATE INDEX `fk_bf_vessel` ON `baplie_file` (`vessel_id`);

CREATE INDEX `fk_bf_corrected_by` ON `baplie_file` (`corrected_by`);

CREATE INDEX `idx_bf_request` ON `baplie_file` (`berthing_request_id`, `uploaded_at`);

CREATE UNIQUE INDEX `uq_bf_request_live_hash` ON `baplie_file` (`berthing_request_id`, `live_hash_key`);

CREATE INDEX `fk_bpi_file` ON `baplie_parse_issue` (`baplie_file_id`);

CREATE INDEX `fk_cme_container` ON `container_manifest_entry` (`container_id`);

CREATE INDEX `fk_cme_imdg` ON `container_manifest_entry` (`imdg_class`);

CREATE UNIQUE INDEX `uq_cme_file_container` ON `container_manifest_entry` (`baplie_file_id`, `container_id`);

CREATE UNIQUE INDEX `uq_efp_run_time` ON `environment_forecast_point` (`forecast_run_id`, `valid_at`);

CREATE INDEX `fk_fr_request` ON `forecast_run` (`berthing_request_id`);

CREATE INDEX `fk_fr_submitted_by` ON `forecast_run` (`submitted_by`);

CREATE INDEX `idx_fr_pulled` ON `forecast_run` (`pulled_at`);

CREATE UNIQUE INDEX `uq_tc_set_code` ON `tide_constituent` (`constituent_set_id`, `constituent_code`);

CREATE UNIQUE INDEX `uq_tpp_run_time` ON `tide_prediction_point` (`tide_prediction_run_id`, `valid_at`);

CREATE INDEX `fk_tpr_set` ON `tide_prediction_run` (`constituent_set_id`);

CREATE UNIQUE INDEX `uq_mlv_id_model` ON `ml_model_version` (`id`, `ml_model_id`);

CREATE UNIQUE INDEX `uq_mlv_model_version` ON `ml_model_version` (`ml_model_id`, `version_no`);

CREATE INDEX `fk_bp_constraint` ON `blocked_period` (`limiting_constraint_type_id`);

CREATE INDEX `fk_bp_proposal` ON `blocked_period` (`proposal_id`);

CREATE INDEX `fk_dfs_model` ON `demand_forecast_slot` (`model_version_id`);

CREATE INDEX `idx_dfs_slot` ON `demand_forecast_slot` (`slot_start`);

CREATE INDEX `fk_ewp_bias` ON `entry_window_proposal` (`bias_correction_version_id`);

CREATE INDEX `fk_ewp_forecast` ON `entry_window_proposal` (`forecast_run_id`);

CREATE INDEX `fk_ewp_request` ON `entry_window_proposal` (`berthing_request_id`);

CREATE INDEX `fk_ewp_tide` ON `entry_window_proposal` (`tide_prediction_run_id`);

CREATE INDEX `fk_ewp_user` ON `entry_window_proposal` (`confirmed_by`);

CREATE INDEX `fk_ewp_window` ON `entry_window_proposal` (`confirmed_window_id`, `id`);

CREATE UNIQUE INDEX `uq_ewp_id_request` ON `entry_window_proposal` (`id`, `berthing_request_id`);

CREATE INDEX `fk_fw_proposal` ON `feasible_window` (`proposal_id`);

CREATE UNIQUE INDEX `uq_fw_id_proposal` ON `feasible_window` (`id`, `proposal_id`);

CREATE UNIQUE INDEX `uq_rt_code_version` ON `rationale_template` (`code`, `version`);

CREATE INDEX `fk_rec_proposal` ON `recommendation` (`entry_window_proposal_id`, `berthing_request_id`);

CREATE INDEX `fk_rec_request` ON `recommendation` (`berthing_request_id`, `terminal_id`);

CREATE INDEX `fk_rec_run` ON `recommendation` (`solver_run_id`);

CREATE UNIQUE INDEX `uq_rec_id_request` ON `recommendation` (`id`, `berthing_request_id`);

CREATE UNIQUE INDEX `uq_rec_id_terminal` ON `recommendation` (`id`, `terminal_id`);

CREATE INDEX `fk_ro_berth` ON `recommendation_option` (`berth_id`, `terminal_id`);

CREATE INDEX `fk_ro_pilot` ON `recommendation_option` (`pilot_user_id`);

CREATE INDEX `fk_ro_rec` ON `recommendation_option` (`recommendation_id`, `terminal_id`);

CREATE INDEX `fk_ro_template` ON `recommendation_option` (`rationale_template_id`);

CREATE INDEX `idx_ro_berth_time` ON `recommendation_option` (`berth_id`, `planned_start`);

CREATE UNIQUE INDEX `uq_ro_id_rec` ON `recommendation_option` (`id`, `recommendation_id`);

CREATE UNIQUE INDEX `uq_ro_rank` ON `recommendation_option` (`recommendation_id`, `option_rank`);

CREATE INDEX `fk_rot_tug` ON `recommendation_option_tug` (`tugboat_id`);

CREATE INDEX `fk_sr_user` ON `solver_run` (`triggered_by`);

CREATE INDEX `fk_srmv_model` ON `solver_run_model_version` (`model_version_id`);

CREATE INDEX `fk_rov_new_berth` ON `recommendation_override` (`override_berth_id`);

CREATE INDEX `fk_rov_new_pilot` ON `recommendation_override` (`override_pilot_user_id`);

CREATE INDEX `fk_rov_new_tug` ON `recommendation_override` (`override_tugboat_id`);

CREATE INDEX `fk_rov_option` ON `recommendation_override` (`recommendation_option_id`);

CREATE INDEX `fk_rov_orig_berth` ON `recommendation_override` (`original_berth_id`);

CREATE INDEX `fk_rov_orig_pilot` ON `recommendation_override` (`original_pilot_user_id`);

CREATE INDEX `fk_rov_orig_tug` ON `recommendation_override` (`original_tugboat_id`);

CREATE INDEX `fk_rov_user` ON `recommendation_override` (`overridden_by`);

CREATE INDEX `fk_rrf_constraint` ON `recommendation_rationale_factor` (`constraint_type_id`);

CREATE INDEX `fk_rrf_objective` ON `recommendation_rationale_factor` (`objective_term_id`);

CREATE INDEX `fk_rrf_option` ON `recommendation_rationale_factor` (`option_id`);

CREATE INDEX `fk_sai_compatible` ON `storage_allocation_item` (`storage_facility_id`, `cargo_type_id`);

CREATE INDEX `fk_sai_tr` ON `storage_allocation_item` (`terminal_recommendation_id`, `rec_type`);

CREATE INDEX `fk_sai_unit` ON `storage_allocation_item` (`storage_facility_id`, `quantity_unit`);

CREATE UNIQUE INDEX `uq_sai_rec_facility_cargo` ON `storage_allocation_item` (`terminal_recommendation_id`, `storage_facility_id`, `cargo_type_id`);

CREATE INDEX `fk_tr_rec` ON `terminal_recommendation` (`recommendation_id`, `terminal_id`);

CREATE INDEX `fk_tr_terminal` ON `terminal_recommendation` (`terminal_id`, `terminal_type`);

CREATE INDEX `fk_tr_user` ON `terminal_recommendation` (`decided_by`);

CREATE INDEX `fk_tr_exported_by` ON `terminal_recommendation` (`exported_by`);

CREATE INDEX `fk_tr_template` ON `terminal_recommendation` (`rationale_template_id`);

CREATE INDEX `idx_tr_terminal_decision` ON `terminal_recommendation` (`terminal_id`, `decision`);

CREATE UNIQUE INDEX `uq_tr_id_type` ON `terminal_recommendation` (`id`, `rec_type`);

CREATE UNIQUE INDEX `uq_tr_rec_type` ON `terminal_recommendation` (`recommendation_id`, `rec_type`);

CREATE INDEX `fk_ypi_block` ON `yard_placement_item` (`yard_block_id`);

CREATE INDEX `fk_ypi_manifest` ON `yard_placement_item` (`manifest_entry_id`);

CREATE INDEX `fk_ypi_slot` ON `yard_placement_item` (`yard_slot_id`, `yard_block_id`);

CREATE INDEX `fk_ypi_tr` ON `yard_placement_item` (`terminal_recommendation_id`, `rec_type`);

CREATE UNIQUE INDEX `uq_ypi_rec_container` ON `yard_placement_item` (`terminal_recommendation_id`, `manifest_entry_id`);

CREATE INDEX `fk_op_option` ON `operational_plan` (`recommendation_option_id`, `recommendation_id`);

CREATE INDEX `fk_op_rec` ON `operational_plan` (`recommendation_id`, `berthing_request_id`);

CREATE INDEX `fk_op_user` ON `operational_plan` (`confirmed_by`);

CREATE INDEX `fk_pc_assigner` ON `plan_component` (`assigned_by`);

CREATE INDEX `fk_pc_plan` ON `plan_component` (`operational_plan_id`);

CREATE INDEX `fk_pc_terminal` ON `plan_component` (`terminal_id`);

CREATE INDEX `idx_pc_berth_time` ON `plan_component` (`berth_id`, `planned_start`);

CREATE INDEX `idx_pc_tug_time` ON `plan_component` (`tugboat_id`, `planned_start`);

CREATE INDEX `idx_pc_user_time` ON `plan_component` (`assigned_user_id`, `planned_start`);

CREATE UNIQUE INDEX `uq_pc_id_type` ON `plan_component` (`id`, `component_type`);

CREATE INDEX `fk_asu_component` ON `actual_status_update` (`plan_component_id`, `component_type`);

CREATE INDEX `fk_asu_status` ON `actual_status_update` (`component_type`, `status`);

CREATE INDEX `fk_asu_user` ON `actual_status_update` (`reported_by`);

CREATE INDEX `idx_asu_component_time` ON `actual_status_update` (`plan_component_id`, `reported_at`);

CREATE INDEX `fk_dl_component` ON `deviation_log` (`plan_component_id`);

CREATE INDEX `fk_dl_update` ON `deviation_log` (`status_update_id`);

CREATE INDEX `fk_dm_version` ON `drift_measurement` (`model_version_id`);

CREATE INDEX `fk_em_run` ON `evaluation_metric` (`retraining_run_id`);

CREATE INDEX `fk_em_version` ON `evaluation_metric` (`model_version_id`);

CREATE INDEX `fk_rr_challenger` ON `retraining_run` (`challenger_version_id`, `ml_model_id`);

CREATE INDEX `fk_rr_champion` ON `retraining_run` (`champion_version_id`, `ml_model_id`);

CREATE INDEX `fk_rr_model` ON `retraining_run` (`ml_model_id`);

CREATE INDEX `fk_rr_user` ON `retraining_run` (`reviewed_by`);

CREATE INDEX `fk_al_user` ON `audit_log` (`actor_id`);

CREATE INDEX `idx_al_entity` ON `audit_log` (`entity_type`, `entity_id`);

CREATE INDEX `idx_al_time` ON `audit_log` (`occurred_at`);

CREATE INDEX `idx_nl_entity` ON `notification_log` (`related_entity_type`, `related_entity_id`);

CREATE INDEX `idx_nl_user_status` ON `notification_log` (`user_id`, `status`);

CREATE UNIQUE INDEX `uq_user_device` ON `user_device` (`user_id`, `device_id`);

ALTER TABLE `channel_waypoint` COMMENT = 'CHECK chk_waypoint_lat: (latitude between -(90) and 90)
CHECK chk_waypoint_lon: (longitude between -(180) and 180)
CHECK chk_waypoint_order: (sequence_order > 0)
';

ALTER TABLE `license_state` COMMENT = 'Local copy of the SaaS licence (hybrid signed token). Public key and API credential live in config, not here.
CHECK chk_license_state_reason: ((mode IN (''FULL'',''SETUP'')) = (read_only_reason = ''NONE''))
CHECK chk_license_state_singleton: (id = 1)
';

ALTER TABLE `navigation_channel` COMMENT = 'CHECK chk_channel_dimensions: ((depth > 0) and (width > 0) and (speed_limit_knots > 0))
';

ALTER TABLE `port_profile` COMMENT = 'Singleton per deployment. GM is a user_role_assignment, not a column here.
CHECK chk_port_profile_lat: (latitude between -(90) and 90)
CHECK chk_port_profile_lon: (longitude between -(180) and 180)
CHECK chk_port_profile_singleton: (id = 1)
';

ALTER TABLE `berth` COMMENT = 'CHECK chk_berth_dimensions: ((length > 0) and (water_depth > 0) and (max_vessel_loa > 0))
CHECK chk_berth_lat: ((latitude is null) or (latitude between -(90) and 90))
CHECK chk_berth_loa_fits: (max_vessel_loa <= length)
CHECK chk_berth_lon: ((longitude is null) or (longitude between -(180) and 180))
';

ALTER TABLE `berth_terminal` COMMENT = 'One row per terminal allowed to use the berth. Normal berth = 1 row, shared berth = more. Backend: every berth has at least one row.
';

ALTER TABLE `organization_unit` COMMENT = 'Where a user works: the Harbour Authority, Marine Services, or one terminal. A TERMINAL row is created with each terminal.
CHECK chk_org_unit_terminal: ((unit_type = _latin1''TERMINAL'') = (terminal_id is not null))
';

ALTER TABLE `user` COMMENT = 'CHECK chk_user_username_lower: ((username = lower(username)) and (char_length(username) >= 3))
';

ALTER TABLE `user_role_assignment` COMMENT = 'Who holds which role, in which organization unit. Roles are a fixed ENUM (P3-04).
CHECK chk_ura_period: (active_to IS NULL OR active_to > active_from)
CHECK chk_ura_role_matches_unit: (((unit_type = ''HARBOUR_AUTHORITY'') and (role in (''PORT_GM'',''PORT_STAFF'',''MARINE_SAFETY_OFFICER''))) or ((unit_type = ''MARINE_SERVICES'') and (role in (''PILOTAGE_MANAGER'',''PILOTAGE_OFFICER'',''MARINE_PILOT'',''TUG_MANAGER'',''TUG_OFFICER'',''TUG_MASTER''))) or ((unit_type = ''TERMINAL'') and (role in (''TERMINAL_ADMIN'',''TERMINAL_OFFICER'',''TERMINAL_SUPERVISOR''))))
';

ALTER TABLE `pilot_profile` COMMENT = '1:1 with user. App rule: user must hold MARINE_PILOT in MARINE_SERVICES (D-29).
';

ALTER TABLE `pilot_rank_limit` COMMENT = 'Settings screen: one row per rank.
CHECK chk_pilot_rank_limit_loa: ((max_vessel_loa is null) or (max_vessel_loa > 0))
';

ALTER TABLE `pilot_schedule` COMMENT = 'Which pilot works which fixed shift on which day.
';

ALTER TABLE `port_shift` COMMENT = 'Fixed port shifts, defined on the settings screen. App rule: active shifts must not overlap.
CHECK chk_port_shift_times: (end_time <> start_time)
';

ALTER TABLE `tug_master_profile` COMMENT = '1:1 with user. App rule: user must hold TUG_MASTER in MARINE_SERVICES (D-29).
';

ALTER TABLE `tug_master_schedule` COMMENT = 'Which tug master works which fixed shift on which day.
';

ALTER TABLE `tugboat` COMMENT = 'Port tug fleet (shared Marine Services resource).
CHECK chk_tugboat_power: (tug_power > 0)
';

ALTER TABLE `container` COMMENT = 'Identity only. Per-call attributes (weight, stowage, ports) live in container_manifest_entry.
CHECK chk_container_number: regexp_like(container_number,_latin1''^[A-Z]{3}[UJZ][0-9]{7}$'',_latin1''c'')
';

ALTER TABLE `container_size_type` COMMENT = 'CHECK chk_cst_length: (length_ft in (10,20,40,45))
CHECK chk_cst_teu: (teu_factor > 0)
';

ALTER TABLE `vessel` COMMENT = 'Block coefficient (Cb), GT and DWT are per call: they live on berthing_request.
CHECK chk_vessel_dims: (loa > 0) and (beam > 0) and (beam < loa) and (max_draft > 0)
CHECK chk_vessel_imo: regexp_like(imo_number, ''^[0-9]{7}$'')
CHECK chk_vessel_tonnage: (net_tonnage IS NULL OR net_tonnage > 0) and (moulded_depth IS NULL OR moulded_depth > 0)
';

ALTER TABLE `cargo_type` COMMENT = 'CHECK chk_ct_temp: ((min_temperature is null) or (max_temperature is null) or (min_temperature <= max_temperature))
';

ALTER TABLE `imdg_class` COMMENT = 'Fixed IMDG list; one source for every imdg_class column.
';

ALTER TABLE `storage_facility` COMMENT = 'CHECK chk_sf_area: ((area is null) or (area > 0))
CHECK chk_sf_capacity: (((capacity is null) or (capacity > 0)) and (current_occupancy >= 0) and (reserved_capacity >= 0) and (blocked_capacity >= 0))
CHECK chk_sf_capacity_sum: ((capacity is null) or (((current_occupancy + reserved_capacity) + blocked_capacity) <= capacity))
CHECK chk_sf_floor_load: ((max_floor_load is null) or (max_floor_load > 0))
CHECK chk_sf_lat: ((latitude is null) or (latitude between -(90) and 90))
CHECK chk_sf_lon: ((longitude is null) or (longitude between -(180) and 180))
CHECK chk_sf_yard_rules: (((detail_kind = ''CONTAINER_YARD'') and (terminal_type = ''CONTAINER'') and (capacity is null) and (capacity_unit is null) and (current_occupancy = 0) and (reserved_capacity = 0) and (blocked_capacity = 0)) or ((detail_kind <> ''CONTAINER_YARD'') and (capacity is not null) and (capacity_unit is not null)))
';

ALTER TABLE `storage_movement` COMMENT = 'CHECK chk_sm_quantity: (quantity > 0)
CHECK chk_sm_source_direction: (((source = ''VESSEL_DISCHARGE'') and (direction = ''IN'')) or ((source = ''VESSEL_LOADING'') and (direction = ''OUT'')) or (source = ''MANUAL''))
';

ALTER TABLE `tank_detail` COMMENT = 'CHECK chk_td_kind: (detail_kind = _latin1''TANK'')
CHECK chk_td_pressure: ((max_pressure_bar is null) or (max_pressure_bar > 0))
CHECK chk_td_temp: ((min_temperature is null) or (max_temperature is null) or (min_temperature <= max_temperature))
';

ALTER TABLE `warehouse_detail` COMMENT = 'CHECK chk_wd_kind: (detail_kind = _latin1''WAREHOUSE'')
CHECK chk_wd_temp: ((min_temperature is null) or (max_temperature is null) or (min_temperature <= max_temperature))
';

ALTER TABLE `dg_zone_allowed_class` COMMENT = 'CHECK chk_dzac_type: (zone_detail = ''DANGEROUS_GOODS'')
';

ALTER TABLE `oog_zone_detail` COMMENT = 'CHECK chk_ozd_positive: (((max_length is null) or (max_length > 0)) and ((max_width is null) or (max_width > 0)) and ((max_height is null) or (max_height > 0)) and ((max_weight is null) or (max_weight > 0)))
CHECK chk_ozd_type: (zone_detail = _latin1''OOG'')
';

ALTER TABLE `reefer_zone_detail` COMMENT = 'CHECK chk_rzd_plugs: (total_power_plugs > 0)
CHECK chk_rzd_type: (zone_detail = _latin1''REEFER'')
';

ALTER TABLE `yard_slot` COMMENT = 'CHECK chk_ys_blocked_empty: ((status = ''ACTIVE'') or (container_id is null))
CHECK chk_ys_position: ((bay > 0) and (row_no > 0) and (tier > 0))
';

ALTER TABLE `yard_zone` COMMENT = 'Capacity/occupancy are derived from yard_slot.
CHECK chk_yz_kind: (detail_kind = _latin1''CONTAINER_YARD'')
';

ALTER TABLE `equipment` COMMENT = 'CHECK chk_eq_quantity: ((quantity > 0) and (maintenance_quantity >= 0) and (blocked_quantity >= 0) and ((maintenance_quantity + blocked_quantity) <= quantity))
';

ALTER TABLE `equipment_capability` COMMENT = 'CHECK chk_ecap_value: (capability_value >= 0)
';

ALTER TABLE `safety_constraint` COMMENT = 'Overlap of the same type + scope is rejected by the backend. Several scopes apply to one case: the STRICTEST value wins (Moustafa, OQ-P9-2).
CHECK chk_sc_period: ((active_to is null) or (active_to > active_from))
';

ALTER TABLE `terminal_productivity_profile` COMMENT = 'Inputs for the AI service-time calculation (Module 15). Overlapping periods rejected by the backend.
CHECK chk_tpp_period: ((valid_to is null) or (valid_to >= valid_from))
CHECK chk_tpp_unit: ((terminal_type = _latin1''CONTAINER'') = (rate_unit = _latin1''MOVES_PER_HOUR''))
CHECK chk_tpp_values: ((productivity_rate > 0) and (fixed_overhead_minutes >= 0))
';

ALTER TABLE `berthing_request` COMMENT = 'status = current state; history in berthing_request_status_history (same transaction). Return for revision only from SUBMITTED; ENTRY_WINDOW_CONFIRMED may be reopened to CUSTOMS_APPROVED while no plan is confirmed. Cancel only before Preliminary Approval.
CHECK chk_br_cb: (block_coefficient > 0.3) and (block_coefficient < 1.0)
CHECK chk_br_tonnage: (gross_tonnage > 0) and (deadweight_tonnes > 0)
CHECK chk_br_source: (source = ''EXTERNAL'' AND external_system_id IS NOT NULL AND external_reference IS NOT NULL) OR (source = ''MANUAL'' AND submitted_by IS NOT NULL AND (external_system_id IS NULL) = (external_reference IS NULL))
CHECK chk_br_departure_draft: departure_draft IS NULL OR departure_draft > 0
CHECK chk_br_ports: (previous_port IS NULL OR regexp_like(previous_port, ''^[A-Z]{2}[A-Z2-9]{3}$'')) AND (next_port IS NULL OR regexp_like(next_port, ''^[A-Z]{2}[A-Z2-9]{3}$''))
';

ALTER TABLE `berthing_request_cargo` COMMENT = 'Cargo of non-container calls (container calls use the BAPLIE manifest).
CHECK chk_brc_quantity: (quantity > 0)
';

ALTER TABLE `berthing_request_status_history` COMMENT = 'CUSTOMS_APPROVED row = who/when Customs Approval was recorded (D1, D4).
CHECK chk_brsh_change: ((from_status is null) or (from_status <> to_status))
';

ALTER TABLE `external_system` COMMENT = 'External adapters that send berthing requests (P10-03).
';

ALTER TABLE `baplie_file` COMMENT = 'Exactly one ACTIVE file per request (active_request_key). Replacing a revision: old ACTIVE -> REPLACED, new -> ACTIVE, in one transaction. Mistaken upload -> WITHDRAWN.
Diagnostics rows live in baplie_parse_issue (no JSON copy here).
CHECK chk_bf_hash: regexp_like(file_hash_sha256, ''^[0-9a-f]{64}$'', ''c'')
CHECK chk_bf_correction: (is_manually_corrected = false) OR (corrected_by IS NOT NULL AND corrected_at IS NOT NULL)
';

ALTER TABLE `baplie_parse_issue` COMMENT = 'FR-1.2.4 diagnostics.
';

ALTER TABLE `container_manifest_entry` COMMENT = 'Per-call container data from BAPLIE. Container identity in container (Module 5).
CHECK chk_cme_oog: (((oog_over_front_cm is null) or (oog_over_front_cm > 0)) and ((oog_over_back_cm is null) or (oog_over_back_cm > 0)) and ((oog_over_right_cm is null) or (oog_over_right_cm > 0)) and ((oog_over_left_cm is null) or (oog_over_left_cm > 0)) and ((oog_over_height_cm is null) or (oog_over_height_cm > 0)))
CHECK chk_cme_pod: ((port_of_discharge is null) or regexp_like(port_of_discharge,_latin1''^[A-Z]{2}[A-Z2-9]{3}$'',_latin1''c''))
CHECK chk_cme_pol: ((port_of_load is null) or regexp_like(port_of_load,_latin1''^[A-Z]{2}[A-Z2-9]{3}$'',_latin1''c''))
CHECK chk_cme_reefer: ((reefer_set_temperature is null) or (reefer_set_temperature between -(40) and 40))
CHECK chk_cme_stowage: (((stowage_bay is null) or (stowage_bay > 0)) and ((stowage_row is null) or (stowage_row >= 0)) and ((stowage_tier is null) or (stowage_tier > 0)))
CHECK chk_cme_weight: ((weight_kg is null) or (weight_kg > 0))
';

ALTER TABLE `environment_forecast_point` COMMENT = 'Raw provider values; unit conversion and derived values (e.g. surge) are AI-side (P12-02).
CHECK chk_efp_direction: (((wind_direction_deg is null) or ((wind_direction_deg >= 0) and (wind_direction_deg < 360))) and ((wave_direction_deg is null) or ((wave_direction_deg >= 0) and (wave_direction_deg < 360))))
CHECK chk_efp_values: (((wind_speed is null) or (wind_speed >= 0)) and ((wind_gust is null) or (wind_gust >= 0)) and ((visibility is null) or (visibility >= 0)) and ((wave_height is null) or (wave_height >= 0)) and ((wave_period is null) or (wave_period >= 0)) and ((current_speed is null) or (current_speed >= 0)))
';

ALTER TABLE `forecast_run` COMMENT = 'Location = port_profile lat/lon (single port).
CHECK chk_fr_horizon: ((horizon_start is null) or (horizon_end is null) or (horizon_end > horizon_start))
CHECK chk_fr_on_demand: ((pull_type = ''SCHEDULED'') = (berthing_request_id is null))
CHECK chk_fr_manual: ((pull_type = ''MANUAL'') = (submitted_by is not null))
';

ALTER TABLE `tide_constituent` COMMENT = 'CHECK chk_tc_values: ((amplitude >= 0) and (phase_lag >= 0) and (phase_lag < 360) and (speed_deg_per_hour > 0))
';

ALTER TABLE `tide_prediction_run` COMMENT = 'Daily in-house harmonic computation (D7, D10).
CHECK chk_tpr_horizon: (horizon_end > horizon_start)
';

ALTER TABLE `ml_model_version` COMMENT = 'CHECK chk_mlv_champion_deployed: ((status <> _latin1''CHAMPION'') or (deployed_at is not null))
CHECK chk_mlv_version: ((version_no > 0) and ((training_row_count is null) or (training_row_count >= 0)))
CHECK chk_mlv_window: ((training_window_start is null) or (training_window_end is null) or (training_window_end > training_window_start))
';

ALTER TABLE `blocked_period` COMMENT = 'CHECK chk_bp_period: (period_end > period_start)
';

ALTER TABLE `demand_forecast_slot` COMMENT = 'CHECK chk_dfs_method: ((method = _latin1''LIGHTGBM'') = (model_version_id is not null))
CHECK chk_dfs_values: ((slot_end > slot_start) and (expected_arrivals >= 0) and (congestion_score between 0 and 1))
';

ALTER TABLE `entry_window_proposal` COMMENT = 'Part 1 output (D2). Confirmed window feeds Part 2 (D3). data_versions = the three run FKs.
CHECK chk_ewp_confirm_set: (((confirmed_window_id is null) = (confirmed_by is null)) and ((confirmed_by is null) = (confirmed_at is null)))
CHECK chk_ewp_confirmed: ((status <> ''CONFIRMED'') or (confirmed_window_id is not null))
CHECK chk_ewp_score: ((peak_congestion_score is null) or (peak_congestion_score between 0 and 1))
CHECK chk_ewp_reject_set: (((rejected_by IS NULL) = (rejected_at IS NULL)) AND ((status = ''REJECTED'') = (rejection_reason IS NOT NULL)))
';

ALTER TABLE `feasible_window` COMMENT = 'CHECK chk_fw_values: ((window_end > window_start) and ((confidence is null) or (confidence between 0 and 1)))
';

ALTER TABLE `rationale_template` COMMENT = 'Fixed, versioned templates; never LLM (D11).
CHECK chk_rt_version: (version > 0)
';

ALTER TABLE `recommendation_option` COMMENT = 'No is_selected column: the selected option is the one operational_plan references (backend reads it, P15-03).
CHECK chk_ro_p90: (((service_time_method = _latin1''ML'') = (service_time_p90_hours is not null)) and ((service_time_p90_hours is null) or (service_time_p90_hours >= service_time_p50_hours)))
CHECK chk_ro_values: ((option_rank > 0) and (planned_end > planned_start) and (service_time_p50_hours > 0) and ((delay_risk is null) or (delay_risk between 0 and 1)))
';

ALTER TABLE `recommendation_option_tug` COMMENT = 'Number of tugs decided by the AI formula.
';

ALTER TABLE `solver_run` COMMENT = 'Port-wide joint solve (FR-1.4.1). INFEASIBLE is final; FIFO fallback only on technical failure (recommendation.source = FIFO).
CHECK chk_sr_finished: ((status in (''QUEUED'',''RUNNING'')) or (finished_at is not null))
CHECK chk_sr_horizon: (horizon_end > horizon_start)
CHECK chk_sr_manual: ((trigger_type <> ''MANUAL'') or (triggered_by is not null))
CHECK chk_sr_time: ((solve_time_ms is null) or (solve_time_ms >= 0))
';

ALTER TABLE `objective_term` COMMENT = 'CP-SAT objective terms (P16-02). Codes agreed with the AI team.
';

ALTER TABLE `recommendation_override` COMMENT = 'Typed override of a recommendation option (FR-1.4.7). Signal for Part 3, not an automatic retrain trigger (D12).
CHECK chk_rov_berth: (((component_type = _latin1''BERTH'') = (override_berth_id is not null)) and ((component_type = _latin1''BERTH'') = (original_berth_id is not null)))
CHECK chk_rov_pilot: ((component_type = _latin1''PILOT'') = ((original_pilot_user_id is not null) or (override_pilot_user_id is not null)))
CHECK chk_rov_time: (((component_type = _latin1''START_TIME'') = (override_start is not null)) and ((component_type = _latin1''START_TIME'') = (original_start is not null)))
CHECK chk_rov_tug: ((component_type = _latin1''TUG'') = ((original_tugboat_id is not null) or (override_tugboat_id is not null)))
';

ALTER TABLE `recommendation_rationale_factor` COMMENT = 'Constraints/objective terms that actually drove the solve (FR-1.4.10).
CHECK chk_rrf_kind: (((factor_type = _latin1''CONSTRAINT'') and (constraint_type_id is not null) and (objective_term_id is null)) or ((factor_type = _latin1''OBJECTIVE_TERM'') and (objective_term_id is not null) and (constraint_type_id is null)))
';

ALTER TABLE `storage_allocation_item` COMMENT = 'On ACCEPTED, backend adds quantity to storage_facility.reserved_capacity (D-46).
CHECK chk_sai_quantity: (quantity > 0)
CHECK chk_sai_type: (rec_type = _latin1''STORAGE_ALLOCATION'')
';

ALTER TABLE `terminal_recommendation` COMMENT = 'Common terminal output contract (FR-3.2.2). Visible to that terminal only (§42).
CHECK chk_tr_decision: (((decision = ''PENDING'') = (decided_by is null)) and ((decided_by is null) = (decided_at is null)))
CHECK chk_tr_type_terminal: (((rec_type <> ''YARD_PLACEMENT'') or (terminal_type = ''CONTAINER'')) and ((rec_type <> ''STORAGE_ALLOCATION'') or (terminal_type = ''NON_CONTAINER'')))
CHECK chk_tr_window: (((rec_type = ''TIMING'') = ((window_start is not null) and (window_end is not null))) and ((window_start is null) or (window_end > window_start)))
CHECK chk_tr_lifecycle_yard: (lifecycle_status IS NULL OR rec_type = ''YARD_PLACEMENT'')
CHECK chk_tr_confidence: (confidence IS NULL OR confidence BETWEEN 0 AND 1)
';

ALTER TABLE `yard_placement_item` COMMENT = 'CHECK chk_ypi_type: (rec_type = _latin1''YARD_PLACEMENT'')
';

ALTER TABLE `plan_component` COMMENT = 'One row per dispatched unit (one per tug). Berth/tug occupancy = RESERVED rows overlapping a window (D-20).
CHECK chk_pc_assigned: ((assigned_user_id is null) or (assigned_at is not null))
CHECK chk_pc_resource: (((component_type = _latin1''BERTH'') and (berth_id is not null) and (tugboat_id is null) and (terminal_id is null)) or ((component_type = _latin1''TUG'') and (berth_id is null) and (tugboat_id is not null) and (terminal_id is null)) or ((component_type = _latin1''TERMINAL_OPS'') and (berth_id is null) and (tugboat_id is null) and (terminal_id is not null)) or ((component_type = _latin1''PILOT'') and (berth_id is null) and (tugboat_id is null) and (terminal_id is null)))
CHECK chk_pc_time: (planned_end > planned_start)
';

ALTER TABLE `deviation_log` COMMENT = 'P19-03 deferred (2026-10-08): kept as in source for now.
';

ALTER TABLE `status_type` COMMENT = 'Allowed field statuses per component type (P19-01). New status = INSERT, not ALTER.
';

ALTER TABLE `drift_measurement` COMMENT = 'CHECK chk_dm_feature: ((metric_type = _latin1''PSI'') = (feature_name is not null))
';

ALTER TABLE `evaluation_metric` COMMENT = 'CHECK chk_em_period: (period_end > period_start)
CHECK chk_em_scope: (((metric_name <> _latin1''MAE'') or (model_version_id is not null)) and ((metric_name not in (_latin1''OVERRIDE_RATE'',_latin1''WAIT_TIME'')) or (model_version_id is null)))
';

ALTER TABLE `retraining_run` COMMENT = 'Champion vs Challenger history. Hard safety limits are never changed by retraining (D12).
CHECK chk_rr_review: ((reviewed_by is null) = (reviewed_at is null))
CHECK chk_rr_time: ((finished_at is null) or (finished_at >= started_at))
CHECK chk_rr_versions: ((champion_version_id is null) or (champion_version_id <> challenger_version_id))
';

ALTER TABLE `audit_log` COMMENT = 'Append-only: the application DB user gets INSERT + SELECT only on this table (P21-03).
CHECK chk_al_states: (((action <> ''CREATE'') or (before_state is null)) and ((action <> ''DELETE'') or (after_state is null)))
';

ALTER TABLE `notification_log` COMMENT = 'CHECK chk_nl_ack: ((status = ''ACKNOWLEDGED'') = (acknowledged_at is not null))
CHECK chk_nl_failed: ((status = ''FAILED'') = (failure_reason is not null))
';

ALTER TABLE `part1_run` COMMENT = 'CHECK chk_p1r_retry: (retry_count BETWEEN 0 AND 3)
';

ALTER TABLE `user_device` ADD FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);

ALTER TABLE `part1_run` ADD FOREIGN KEY (`berthing_request_id`) REFERENCES `berthing_request` (`id`);

ALTER TABLE `part1_run` ADD FOREIGN KEY (`entry_window_proposal_id`) REFERENCES `entry_window_proposal` (`id`);

ALTER TABLE `recommendation_review` ADD FOREIGN KEY (`recommendation_id`) REFERENCES `recommendation` (`id`);

ALTER TABLE `recommendation_review` ADD FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);

ALTER TABLE `channel_waypoint` ADD CONSTRAINT `fk_channel_waypoint_channel` FOREIGN KEY (`channel_id`) REFERENCES `navigation_channel` (`id`);

ALTER TABLE `port_setting` ADD CONSTRAINT `fk_port_setting_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`);

ALTER TABLE `berth_terminal` ADD CONSTRAINT `fk_bt_berth` FOREIGN KEY (`berth_id`) REFERENCES `berth` (`id`);

ALTER TABLE `berth_terminal` ADD CONSTRAINT `fk_bt_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `terminal` (`id`);

ALTER TABLE `organization_unit` ADD CONSTRAINT `fk_org_unit_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `terminal` (`id`);

ALTER TABLE `user_role_assignment` ADD CONSTRAINT `fk_ura_unit` FOREIGN KEY (`organization_unit_id`, `unit_type`) REFERENCES `organization_unit` (`id`, `unit_type`);

ALTER TABLE `user_role_assignment` ADD CONSTRAINT `fk_ura_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);

ALTER TABLE `pilot_profile` ADD CONSTRAINT `fk_pilot_profile_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);

ALTER TABLE `pilot_schedule` ADD CONSTRAINT `fk_pilot_schedule_created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`);

ALTER TABLE `pilot_schedule` ADD CONSTRAINT `fk_pilot_schedule_pilot` FOREIGN KEY (`pilot_user_id`) REFERENCES `pilot_profile` (`user_id`);

ALTER TABLE `pilot_schedule` ADD CONSTRAINT `fk_pilot_schedule_shift` FOREIGN KEY (`port_shift_id`) REFERENCES `port_shift` (`id`);

ALTER TABLE `tug_master_profile` ADD CONSTRAINT `fk_tug_master_tugboat` FOREIGN KEY (`assigned_tugboat_id`) REFERENCES `tugboat` (`id`);

ALTER TABLE `tug_master_profile` ADD CONSTRAINT `fk_tug_master_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);

ALTER TABLE `tug_master_schedule` ADD CONSTRAINT `fk_tms_created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`);

ALTER TABLE `tug_master_schedule` ADD CONSTRAINT `fk_tms_master` FOREIGN KEY (`tug_master_user_id`) REFERENCES `tug_master_profile` (`user_id`);

ALTER TABLE `tug_master_schedule` ADD CONSTRAINT `fk_tms_shift` FOREIGN KEY (`port_shift_id`) REFERENCES `port_shift` (`id`);

ALTER TABLE `container` ADD CONSTRAINT `fk_container_size_type` FOREIGN KEY (`size_type_id`) REFERENCES `container_size_type` (`id`);

ALTER TABLE `vessel` ADD CONSTRAINT `fk_vessel_type` FOREIGN KEY (`vessel_type_id`) REFERENCES `vessel_type` (`id`);

ALTER TABLE `cargo_type` ADD CONSTRAINT `fk_ct_imdg` FOREIGN KEY (`imdg_class`) REFERENCES `imdg_class` (`code`);

ALTER TABLE `storage_facility` ADD CONSTRAINT `fk_sf_storage_type` FOREIGN KEY (`storage_type_id`, `detail_kind`) REFERENCES `storage_type` (`id`, `detail_kind`);

ALTER TABLE `storage_facility` ADD CONSTRAINT `fk_sf_terminal` FOREIGN KEY (`terminal_id`, `terminal_type`) REFERENCES `terminal` (`id`, `terminal_type`);

ALTER TABLE `storage_facility_cargo_compatibility` ADD CONSTRAINT `fk_sfcc_facility` FOREIGN KEY (`storage_facility_id`, `terminal_id`) REFERENCES `storage_facility` (`id`, `terminal_id`);

ALTER TABLE `storage_facility_cargo_compatibility` ADD CONSTRAINT `fk_sfcc_terminal_cargo` FOREIGN KEY (`terminal_id`, `cargo_type_id`) REFERENCES `terminal_cargo_type` (`terminal_id`, `cargo_type_id`);

ALTER TABLE `storage_movement` ADD CONSTRAINT `fk_sm_compatible` FOREIGN KEY (`storage_facility_id`, `cargo_type_id`) REFERENCES `storage_facility_cargo_compatibility` (`storage_facility_id`, `cargo_type_id`);

ALTER TABLE `storage_movement` ADD CONSTRAINT `fk_sm_plan_component` FOREIGN KEY (`plan_component_id`) REFERENCES `plan_component` (`id`);

ALTER TABLE `storage_movement` ADD CONSTRAINT `fk_sm_user` FOREIGN KEY (`recorded_by`) REFERENCES `user` (`id`);

ALTER TABLE `tank_detail` ADD CONSTRAINT `fk_td_facility` FOREIGN KEY (`storage_facility_id`, `detail_kind`) REFERENCES `storage_facility` (`id`, `detail_kind`);

ALTER TABLE `terminal_cargo_type` ADD CONSTRAINT `fk_tct_cargo_type` FOREIGN KEY (`cargo_type_id`) REFERENCES `cargo_type` (`id`);

ALTER TABLE `terminal_cargo_type` ADD CONSTRAINT `fk_tct_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `terminal` (`id`);

ALTER TABLE `warehouse_detail` ADD CONSTRAINT `fk_wd_facility` FOREIGN KEY (`storage_facility_id`, `detail_kind`) REFERENCES `storage_facility` (`id`, `detail_kind`);

ALTER TABLE `dg_zone_allowed_class` ADD CONSTRAINT `fk_dzac_imdg` FOREIGN KEY (`imdg_class`) REFERENCES `imdg_class` (`code`);

ALTER TABLE `dg_zone_allowed_class` ADD CONSTRAINT `fk_dzac_zone` FOREIGN KEY (`yard_zone_id`, `zone_detail`) REFERENCES `yard_zone` (`id`, `zone_detail`);

ALTER TABLE `oog_zone_detail` ADD CONSTRAINT `fk_ozd_zone` FOREIGN KEY (`yard_zone_id`, `zone_detail`) REFERENCES `yard_zone` (`id`, `zone_detail`);

ALTER TABLE `reefer_zone_detail` ADD CONSTRAINT `fk_rzd_zone` FOREIGN KEY (`yard_zone_id`, `zone_detail`) REFERENCES `yard_zone` (`id`, `zone_detail`);

ALTER TABLE `yard_block` ADD CONSTRAINT `fk_yb_zone` FOREIGN KEY (`yard_zone_id`) REFERENCES `yard_zone` (`id`);

ALTER TABLE `yard_slot` ADD CONSTRAINT `fk_ys_block` FOREIGN KEY (`yard_block_id`) REFERENCES `yard_block` (`id`);

ALTER TABLE `yard_slot` ADD CONSTRAINT `fk_ys_container` FOREIGN KEY (`container_id`) REFERENCES `container` (`id`);

ALTER TABLE `yard_zone` ADD CONSTRAINT `fk_yz_type` FOREIGN KEY (`yard_zone_type_id`, `zone_detail`) REFERENCES `yard_zone_type` (`id`, `zone_detail`);

ALTER TABLE `yard_zone` ADD CONSTRAINT `fk_yz_yard` FOREIGN KEY (`container_yard_id`, `detail_kind`) REFERENCES `storage_facility` (`id`, `detail_kind`);

ALTER TABLE `equipment` ADD CONSTRAINT `fk_eq_category` FOREIGN KEY (`equipment_category_id`) REFERENCES `equipment_category` (`id`);

ALTER TABLE `equipment` ADD CONSTRAINT `fk_eq_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `terminal` (`id`);

ALTER TABLE `equipment_capability` ADD CONSTRAINT `fk_ecap_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`);

ALTER TABLE `equipment_capability` ADD CONSTRAINT `fk_ecap_type` FOREIGN KEY (`capability_type_id`) REFERENCES `capability_type` (`id`);

ALTER TABLE `equipment_cargo_compatibility` ADD CONSTRAINT `fk_ecc_equipment` FOREIGN KEY (`equipment_id`, `terminal_id`) REFERENCES `equipment` (`id`, `terminal_id`);

ALTER TABLE `equipment_cargo_compatibility` ADD CONSTRAINT `fk_ecc_terminal_cargo` FOREIGN KEY (`terminal_id`, `cargo_type_id`) REFERENCES `terminal_cargo_type` (`terminal_id`, `cargo_type_id`);

ALTER TABLE `safety_constraint` ADD CONSTRAINT `fk_sc_berth` FOREIGN KEY (`berth_id`) REFERENCES `berth` (`id`);

ALTER TABLE `safety_constraint` ADD CONSTRAINT `fk_sc_berth_terminal` FOREIGN KEY (`berth_id`, `terminal_id`) REFERENCES `berth_terminal` (`berth_id`, `terminal_id`);

ALTER TABLE `safety_constraint` ADD CONSTRAINT `fk_sc_eqcat` FOREIGN KEY (`equipment_category_id`) REFERENCES `equipment_category` (`id`);

ALTER TABLE `safety_constraint` ADD CONSTRAINT `fk_sc_imdg` FOREIGN KEY (`imdg_class`) REFERENCES `imdg_class` (`code`);

ALTER TABLE `safety_constraint` ADD CONSTRAINT `fk_sc_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `terminal` (`id`);

ALTER TABLE `safety_constraint` ADD CONSTRAINT `fk_sc_type` FOREIGN KEY (`constraint_type_id`) REFERENCES `constraint_type` (`id`);

ALTER TABLE `safety_constraint` ADD CONSTRAINT `fk_sc_user` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`);

ALTER TABLE `safety_constraint` ADD CONSTRAINT `fk_sc_vtype` FOREIGN KEY (`vessel_type_id`) REFERENCES `vessel_type` (`id`);

ALTER TABLE `terminal_productivity_profile` ADD CONSTRAINT `fk_tpp_terminal` FOREIGN KEY (`terminal_id`, `terminal_type`) REFERENCES `terminal` (`id`, `terminal_type`);

ALTER TABLE `terminal_productivity_profile` ADD CONSTRAINT `fk_tpp_vtype` FOREIGN KEY (`vessel_type_id`) REFERENCES `vessel_type` (`id`);

ALTER TABLE `berthing_request` ADD CONSTRAINT `fk_br_external` FOREIGN KEY (`external_system_id`) REFERENCES `external_system` (`id`);

ALTER TABLE `berthing_request` ADD CONSTRAINT `fk_br_terminal` FOREIGN KEY (`requested_terminal_id`) REFERENCES `terminal` (`id`);

ALTER TABLE `berthing_request` ADD CONSTRAINT `fk_br_user` FOREIGN KEY (`submitted_by`) REFERENCES `user` (`id`);

ALTER TABLE `berthing_request` ADD CONSTRAINT `fk_br_vessel` FOREIGN KEY (`vessel_id`) REFERENCES `vessel` (`id`);

ALTER TABLE `berthing_request_cargo` ADD CONSTRAINT `fk_brc_request` FOREIGN KEY (`berthing_request_id`, `terminal_id`) REFERENCES `berthing_request` (`id`, `requested_terminal_id`);

ALTER TABLE `berthing_request_cargo` ADD CONSTRAINT `fk_brc_terminal_cargo` FOREIGN KEY (`terminal_id`, `cargo_type_id`) REFERENCES `terminal_cargo_type` (`terminal_id`, `cargo_type_id`);

ALTER TABLE `berthing_request_status_history` ADD CONSTRAINT `fk_brsh_request` FOREIGN KEY (`berthing_request_id`) REFERENCES `berthing_request` (`id`);

ALTER TABLE `berthing_request_status_history` ADD CONSTRAINT `fk_brsh_user` FOREIGN KEY (`changed_by`) REFERENCES `user` (`id`);

ALTER TABLE `baplie_file` ADD CONSTRAINT `fk_bf_request` FOREIGN KEY (`berthing_request_id`, `vessel_id`) REFERENCES `berthing_request` (`id`, `vessel_id`);

ALTER TABLE `baplie_file` ADD CONSTRAINT `fk_bf_user` FOREIGN KEY (`uploaded_by`) REFERENCES `user` (`id`);

ALTER TABLE `baplie_file` ADD CONSTRAINT `fk_bf_vessel` FOREIGN KEY (`vessel_id`) REFERENCES `vessel` (`id`);

ALTER TABLE `baplie_parse_issue` ADD CONSTRAINT `fk_bpi_file` FOREIGN KEY (`baplie_file_id`) REFERENCES `baplie_file` (`id`);

ALTER TABLE `container_manifest_entry` ADD CONSTRAINT `fk_cme_container` FOREIGN KEY (`container_id`) REFERENCES `container` (`id`);

ALTER TABLE `container_manifest_entry` ADD CONSTRAINT `fk_cme_file` FOREIGN KEY (`baplie_file_id`) REFERENCES `baplie_file` (`id`);

ALTER TABLE `container_manifest_entry` ADD CONSTRAINT `fk_cme_imdg` FOREIGN KEY (`imdg_class`) REFERENCES `imdg_class` (`code`);

ALTER TABLE `environment_forecast_point` ADD CONSTRAINT `fk_efp_run` FOREIGN KEY (`forecast_run_id`) REFERENCES `forecast_run` (`id`);

ALTER TABLE `forecast_run` ADD CONSTRAINT `fk_fr_request` FOREIGN KEY (`berthing_request_id`) REFERENCES `berthing_request` (`id`);

ALTER TABLE `tide_constituent` ADD CONSTRAINT `fk_tc_set` FOREIGN KEY (`constituent_set_id`) REFERENCES `tide_constituent_set` (`id`);

ALTER TABLE `tide_prediction_point` ADD CONSTRAINT `fk_tpp_run` FOREIGN KEY (`tide_prediction_run_id`) REFERENCES `tide_prediction_run` (`id`);

ALTER TABLE `tide_prediction_run` ADD CONSTRAINT `fk_tpr_set` FOREIGN KEY (`constituent_set_id`) REFERENCES `tide_constituent_set` (`id`);

ALTER TABLE `ml_model_version` ADD CONSTRAINT `fk_mlv_model` FOREIGN KEY (`ml_model_id`) REFERENCES `ml_model` (`id`);

ALTER TABLE `blocked_period` ADD CONSTRAINT `fk_bp_constraint` FOREIGN KEY (`limiting_constraint_type_id`) REFERENCES `constraint_type` (`id`);

ALTER TABLE `blocked_period` ADD CONSTRAINT `fk_bp_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `entry_window_proposal` (`id`);

ALTER TABLE `demand_forecast_slot` ADD CONSTRAINT `fk_dfs_model` FOREIGN KEY (`model_version_id`) REFERENCES `ml_model_version` (`id`);

ALTER TABLE `entry_window_proposal` ADD CONSTRAINT `fk_ewp_bias` FOREIGN KEY (`bias_correction_version_id`) REFERENCES `ml_model_version` (`id`);

ALTER TABLE `entry_window_proposal` ADD CONSTRAINT `fk_ewp_forecast` FOREIGN KEY (`forecast_run_id`) REFERENCES `forecast_run` (`id`);

ALTER TABLE `entry_window_proposal` ADD CONSTRAINT `fk_ewp_request` FOREIGN KEY (`berthing_request_id`) REFERENCES `berthing_request` (`id`);

ALTER TABLE `entry_window_proposal` ADD CONSTRAINT `fk_ewp_tide` FOREIGN KEY (`tide_prediction_run_id`) REFERENCES `tide_prediction_run` (`id`);

ALTER TABLE `entry_window_proposal` ADD CONSTRAINT `fk_ewp_user` FOREIGN KEY (`confirmed_by`) REFERENCES `user` (`id`);

ALTER TABLE `entry_window_proposal` ADD CONSTRAINT `fk_ewp_window` FOREIGN KEY (`confirmed_window_id`, `id`) REFERENCES `feasible_window` (`id`, `proposal_id`);

ALTER TABLE `feasible_window` ADD CONSTRAINT `fk_fw_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `entry_window_proposal` (`id`);

ALTER TABLE `recommendation` ADD CONSTRAINT `fk_rec_proposal` FOREIGN KEY (`entry_window_proposal_id`, `berthing_request_id`) REFERENCES `entry_window_proposal` (`id`, `berthing_request_id`);

ALTER TABLE `recommendation` ADD CONSTRAINT `fk_rec_request` FOREIGN KEY (`berthing_request_id`, `terminal_id`) REFERENCES `berthing_request` (`id`, `requested_terminal_id`);

ALTER TABLE `recommendation` ADD CONSTRAINT `fk_rec_run` FOREIGN KEY (`solver_run_id`) REFERENCES `solver_run` (`id`);

ALTER TABLE `recommendation_option` ADD CONSTRAINT `fk_ro_berth` FOREIGN KEY (`berth_id`, `terminal_id`) REFERENCES `berth_terminal` (`berth_id`, `terminal_id`);

ALTER TABLE `recommendation_option` ADD CONSTRAINT `fk_ro_pilot` FOREIGN KEY (`pilot_user_id`) REFERENCES `pilot_profile` (`user_id`);

ALTER TABLE `recommendation_option` ADD CONSTRAINT `fk_ro_rec` FOREIGN KEY (`recommendation_id`, `terminal_id`) REFERENCES `recommendation` (`id`, `terminal_id`);

ALTER TABLE `recommendation_option` ADD CONSTRAINT `fk_ro_template` FOREIGN KEY (`rationale_template_id`) REFERENCES `rationale_template` (`id`);

ALTER TABLE `recommendation_option_tug` ADD CONSTRAINT `fk_rot_option` FOREIGN KEY (`option_id`) REFERENCES `recommendation_option` (`id`);

ALTER TABLE `recommendation_option_tug` ADD CONSTRAINT `fk_rot_tug` FOREIGN KEY (`tugboat_id`) REFERENCES `tugboat` (`id`);

ALTER TABLE `solver_run` ADD CONSTRAINT `fk_sr_user` FOREIGN KEY (`triggered_by`) REFERENCES `user` (`id`);

ALTER TABLE `solver_run_model_version` ADD CONSTRAINT `fk_srmv_model` FOREIGN KEY (`model_version_id`) REFERENCES `ml_model_version` (`id`);

ALTER TABLE `solver_run_model_version` ADD CONSTRAINT `fk_srmv_run` FOREIGN KEY (`solver_run_id`) REFERENCES `solver_run` (`id`);

ALTER TABLE `recommendation_override` ADD CONSTRAINT `fk_rov_new_berth` FOREIGN KEY (`override_berth_id`) REFERENCES `berth` (`id`);

ALTER TABLE `recommendation_override` ADD CONSTRAINT `fk_rov_new_pilot` FOREIGN KEY (`override_pilot_user_id`) REFERENCES `pilot_profile` (`user_id`);

ALTER TABLE `recommendation_override` ADD CONSTRAINT `fk_rov_new_tug` FOREIGN KEY (`override_tugboat_id`) REFERENCES `tugboat` (`id`);

ALTER TABLE `recommendation_override` ADD CONSTRAINT `fk_rov_option` FOREIGN KEY (`recommendation_option_id`) REFERENCES `recommendation_option` (`id`);

ALTER TABLE `recommendation_override` ADD CONSTRAINT `fk_rov_orig_berth` FOREIGN KEY (`original_berth_id`) REFERENCES `berth` (`id`);

ALTER TABLE `recommendation_override` ADD CONSTRAINT `fk_rov_orig_pilot` FOREIGN KEY (`original_pilot_user_id`) REFERENCES `pilot_profile` (`user_id`);

ALTER TABLE `recommendation_override` ADD CONSTRAINT `fk_rov_orig_tug` FOREIGN KEY (`original_tugboat_id`) REFERENCES `tugboat` (`id`);

ALTER TABLE `recommendation_override` ADD CONSTRAINT `fk_rov_user` FOREIGN KEY (`overridden_by`) REFERENCES `user` (`id`);

ALTER TABLE `recommendation_rationale_factor` ADD CONSTRAINT `fk_rrf_constraint` FOREIGN KEY (`constraint_type_id`) REFERENCES `constraint_type` (`id`);

ALTER TABLE `recommendation_rationale_factor` ADD CONSTRAINT `fk_rrf_objective` FOREIGN KEY (`objective_term_id`) REFERENCES `objective_term` (`id`);

ALTER TABLE `recommendation_rationale_factor` ADD CONSTRAINT `fk_rrf_option` FOREIGN KEY (`option_id`) REFERENCES `recommendation_option` (`id`);

ALTER TABLE `storage_allocation_item` ADD CONSTRAINT `fk_sai_compatible` FOREIGN KEY (`storage_facility_id`, `cargo_type_id`) REFERENCES `storage_facility_cargo_compatibility` (`storage_facility_id`, `cargo_type_id`);

ALTER TABLE `storage_allocation_item` ADD CONSTRAINT `fk_sai_tr` FOREIGN KEY (`terminal_recommendation_id`, `rec_type`) REFERENCES `terminal_recommendation` (`id`, `rec_type`);

ALTER TABLE `storage_allocation_item` ADD CONSTRAINT `fk_sai_unit` FOREIGN KEY (`storage_facility_id`, `quantity_unit`) REFERENCES `storage_facility` (`id`, `capacity_unit`);

ALTER TABLE `terminal_recommendation` ADD CONSTRAINT `fk_tr_rec` FOREIGN KEY (`recommendation_id`, `terminal_id`) REFERENCES `recommendation` (`id`, `terminal_id`);

ALTER TABLE `terminal_recommendation` ADD CONSTRAINT `fk_tr_terminal` FOREIGN KEY (`terminal_id`, `terminal_type`) REFERENCES `terminal` (`id`, `terminal_type`);

ALTER TABLE `terminal_recommendation` ADD CONSTRAINT `fk_tr_user` FOREIGN KEY (`decided_by`) REFERENCES `user` (`id`);

ALTER TABLE `yard_placement_item` ADD CONSTRAINT `fk_ypi_block` FOREIGN KEY (`yard_block_id`) REFERENCES `yard_block` (`id`);

ALTER TABLE `yard_placement_item` ADD CONSTRAINT `fk_ypi_manifest` FOREIGN KEY (`manifest_entry_id`) REFERENCES `container_manifest_entry` (`id`);

ALTER TABLE `yard_placement_item` ADD CONSTRAINT `fk_ypi_slot` FOREIGN KEY (`yard_slot_id`, `yard_block_id`) REFERENCES `yard_slot` (`id`, `yard_block_id`);

ALTER TABLE `yard_placement_item` ADD CONSTRAINT `fk_ypi_tr` FOREIGN KEY (`terminal_recommendation_id`, `rec_type`) REFERENCES `terminal_recommendation` (`id`, `rec_type`);

ALTER TABLE `operational_plan` ADD CONSTRAINT `fk_op_option` FOREIGN KEY (`recommendation_option_id`, `recommendation_id`) REFERENCES `recommendation_option` (`id`, `recommendation_id`);

ALTER TABLE `operational_plan` ADD CONSTRAINT `fk_op_rec` FOREIGN KEY (`recommendation_id`, `berthing_request_id`) REFERENCES `recommendation` (`id`, `berthing_request_id`);

ALTER TABLE `operational_plan` ADD CONSTRAINT `fk_op_user` FOREIGN KEY (`confirmed_by`) REFERENCES `user` (`id`);

ALTER TABLE `plan_component` ADD CONSTRAINT `fk_pc_assignee` FOREIGN KEY (`assigned_user_id`) REFERENCES `user` (`id`);

ALTER TABLE `plan_component` ADD CONSTRAINT `fk_pc_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `user` (`id`);

ALTER TABLE `plan_component` ADD CONSTRAINT `fk_pc_berth` FOREIGN KEY (`berth_id`) REFERENCES `berth` (`id`);

ALTER TABLE `plan_component` ADD CONSTRAINT `fk_pc_plan` FOREIGN KEY (`operational_plan_id`) REFERENCES `operational_plan` (`id`);

ALTER TABLE `plan_component` ADD CONSTRAINT `fk_pc_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `terminal` (`id`);

ALTER TABLE `plan_component` ADD CONSTRAINT `fk_pc_tug` FOREIGN KEY (`tugboat_id`) REFERENCES `tugboat` (`id`);

ALTER TABLE `actual_status_update` ADD CONSTRAINT `fk_asu_component` FOREIGN KEY (`plan_component_id`, `component_type`) REFERENCES `plan_component` (`id`, `component_type`);

ALTER TABLE `actual_status_update` ADD CONSTRAINT `fk_asu_status` FOREIGN KEY (`component_type`, `status`) REFERENCES `status_type` (`component_type`, `code`);

ALTER TABLE `actual_status_update` ADD CONSTRAINT `fk_asu_user` FOREIGN KEY (`reported_by`) REFERENCES `user` (`id`);

ALTER TABLE `deviation_log` ADD CONSTRAINT `fk_dl_component` FOREIGN KEY (`plan_component_id`) REFERENCES `plan_component` (`id`);

ALTER TABLE `deviation_log` ADD CONSTRAINT `fk_dl_update` FOREIGN KEY (`status_update_id`) REFERENCES `actual_status_update` (`id`);

ALTER TABLE `drift_measurement` ADD CONSTRAINT `fk_dm_version` FOREIGN KEY (`model_version_id`) REFERENCES `ml_model_version` (`id`);

ALTER TABLE `evaluation_metric` ADD CONSTRAINT `fk_em_run` FOREIGN KEY (`retraining_run_id`) REFERENCES `retraining_run` (`id`);

ALTER TABLE `evaluation_metric` ADD CONSTRAINT `fk_em_version` FOREIGN KEY (`model_version_id`) REFERENCES `ml_model_version` (`id`);

ALTER TABLE `retraining_run` ADD CONSTRAINT `fk_rr_challenger` FOREIGN KEY (`challenger_version_id`, `ml_model_id`) REFERENCES `ml_model_version` (`id`, `ml_model_id`);

ALTER TABLE `retraining_run` ADD CONSTRAINT `fk_rr_champion` FOREIGN KEY (`champion_version_id`, `ml_model_id`) REFERENCES `ml_model_version` (`id`, `ml_model_id`);

ALTER TABLE `retraining_run` ADD CONSTRAINT `fk_rr_model` FOREIGN KEY (`ml_model_id`) REFERENCES `ml_model` (`id`);

ALTER TABLE `retraining_run` ADD CONSTRAINT `fk_rr_user` FOREIGN KEY (`reviewed_by`) REFERENCES `user` (`id`);

ALTER TABLE `audit_log` ADD CONSTRAINT `fk_al_user` FOREIGN KEY (`actor_id`) REFERENCES `user` (`id`);

ALTER TABLE `notification_log` ADD CONSTRAINT `fk_nl_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);

ALTER TABLE `baplie_file` ADD CONSTRAINT `fk_cb_user` FOREIGN KEY (`corrected_by`) REFERENCES `user` (`id`);

ALTER TABLE `entry_window_proposal` ADD CONSTRAINT `fk_ewp_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `user` (`id`);

ALTER TABLE `forecast_run` ADD CONSTRAINT `fk_fr_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `user` (`id`);

ALTER TABLE `terminal_recommendation` ADD CONSTRAINT `fk_tr_exported_by` FOREIGN KEY (`exported_by`) REFERENCES `user` (`id`);

ALTER TABLE `terminal_recommendation` ADD CONSTRAINT `fk_tr_template` FOREIGN KEY (`rationale_template_id`) REFERENCES `rationale_template` (`id`);
```

</details>
