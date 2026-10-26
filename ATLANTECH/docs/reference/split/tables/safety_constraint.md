<!-- Generated from docs/reference/database/PortSync.sql lines 397-409 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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

<!-- Generated from docs/reference/database/PortSync.md lines 1337-1386 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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
