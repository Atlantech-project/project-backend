<!-- Generated from docs/reference/database/PortSync.sql lines 225-244 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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

<!-- Generated from docs/reference/database/PortSync.md lines 754-810 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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
