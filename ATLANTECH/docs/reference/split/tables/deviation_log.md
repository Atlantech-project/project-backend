<!-- Generated from docs/reference/database/PortSync.sql lines 830-840 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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

<!-- Generated from docs/reference/database/PortSync.md lines 2708-2738 by build-index.mjs. Do not edit: edit the source and rebuild. -->
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
