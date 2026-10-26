<!-- Generated from docs/reference/database/PortSync.sql lines 81-85 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `berth_terminal` (
  `berth_id` bigint NOT NULL,
  `terminal_id` bigint NOT NULL,
  PRIMARY KEY (`berth_id`, `terminal_id`)
);

<!-- Generated from docs/reference/database/PortSync.md lines 294-320 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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
