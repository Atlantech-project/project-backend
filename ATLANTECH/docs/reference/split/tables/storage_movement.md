<!-- Generated from docs/reference/database/PortSync.sql lines 253-263 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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

<!-- Generated from docs/reference/database/PortSync.md lines 840-878 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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
