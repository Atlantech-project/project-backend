<!-- Generated from docs/reference/database/PortSync.sql lines 373-378 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `equipment_cargo_compatibility` (
  `equipment_id` bigint NOT NULL,
  `terminal_id` bigint NOT NULL,
  `cargo_type_id` bigint NOT NULL,
  PRIMARY KEY (`equipment_id`, `cargo_type_id`)
);

<!-- Generated from docs/reference/database/PortSync.md lines 1282-1306 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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
