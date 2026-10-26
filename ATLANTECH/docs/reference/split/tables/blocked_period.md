<!-- Generated from docs/reference/database/PortSync.sql lines 606-612 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `blocked_period` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `proposal_id` bigint NOT NULL,
  `period_start` datetime NOT NULL,
  `period_end` datetime NOT NULL,
  `limiting_constraint_type_id` bigint NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 1962-1992 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `blocked_period`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `proposal_id` | `bigint` | NO |  | FK → `entry_window_proposal.id` |  |
| `period_start` | `datetime` | NO |  |  |  |
| `period_end` | `datetime` | NO |  |  |  |
| `limiting_constraint_type_id` | `bigint` | NO |  | FK → `constraint_type.id` |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_bp_period` | `(period_end > period_start)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_bp_constraint` | No | limiting_constraint_type_id |
| `fk_bp_proposal` | No | proposal_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_bp_constraint` | limiting_constraint_type_id | [`constraint_type`](#constraint_type) (id) |  |
| `fk_bp_proposal` | proposal_id | [`entry_window_proposal`](#entry_window_proposal) (id) |  |

---
