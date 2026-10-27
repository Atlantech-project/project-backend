<!-- Generated from docs/reference/database/PortSync.sql lines 411-421 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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

<!-- Generated from docs/reference/database/PortSync.md lines 1388-1427 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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
