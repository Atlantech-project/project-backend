<!-- Generated from docs/reference/database/PortSync.sql lines 280-284 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `terminal_cargo_type` (
  `terminal_id` bigint NOT NULL,
  `cargo_type_id` bigint NOT NULL,
  PRIMARY KEY (`terminal_id`, `cargo_type_id`)
);

<!-- Generated from docs/reference/database/PortSync.md lines 931-955 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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
