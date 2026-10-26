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
