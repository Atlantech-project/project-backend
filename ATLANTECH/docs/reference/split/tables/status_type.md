<!-- Generated from docs/reference/database/PortSync.sql lines 842-847 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `status_type` (
  `component_type` ENUM ('BERTH', 'PILOT', 'TUG', 'TERMINAL_OPS') NOT NULL COMMENT 'Same ENUM as plan_component.component_type',
  `code` varchar(30) NOT NULL,
  `name` varchar(80) NOT NULL,
  PRIMARY KEY (`component_type`, `code`)
);

<!-- Generated from docs/reference/database/PortSync.md lines 2740-2754 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `status_type`

> Allowed field statuses per component type (P19-01). New status = INSERT, not ALTER.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `component_type` | `ENUM(BERTH, PILOT, TUG, TERMINAL_OPS)` | NO |  | PK | Same ENUM as plan_component.component_type |
| `code` | `varchar(30)` | NO |  | PK |  |
| `name` | `varchar(80)` | NO |  |  |  |

**Composite primary key:** `component_type`, `code`

**Referenced by:** [`actual_status_update`](#actual_status_update).component_type, status

---
