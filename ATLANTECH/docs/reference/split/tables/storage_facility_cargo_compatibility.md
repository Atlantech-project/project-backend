<!-- Generated from docs/reference/database/PortSync.sql lines 246-251 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `storage_facility_cargo_compatibility` (
  `storage_facility_id` bigint NOT NULL,
  `terminal_id` bigint NOT NULL,
  `cargo_type_id` bigint NOT NULL,
  PRIMARY KEY (`storage_facility_id`, `cargo_type_id`)
);

<!-- Generated from docs/reference/database/PortSync.md lines 812-838 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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
