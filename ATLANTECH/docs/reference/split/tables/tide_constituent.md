<!-- Generated from docs/reference/database/PortSync.sql lines 550-557 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `tide_constituent` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `constituent_set_id` bigint NOT NULL,
  `constituent_code` varchar(10) NOT NULL COMMENT 'M2, S2, K1, O1 ...',
  `amplitude` decimal(8,4) NOT NULL COMMENT 'm',
  `phase_lag` decimal(7,3) NOT NULL COMMENT 'degrees',
  `speed_deg_per_hour` decimal(10,6) NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 1803-1832 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `tide_constituent`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `constituent_set_id` | `bigint` | NO |  | FK → `tide_constituent_set.id` |  |
| `constituent_code` | `varchar(10)` | NO |  |  | M2, S2, K1, O1 ... |
| `amplitude` | `decimal(8,4)` | NO |  |  | m |
| `phase_lag` | `decimal(7,3)` | NO |  |  | degrees |
| `speed_deg_per_hour` | `decimal(10,6)` | NO |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_tc_values` | `((amplitude >= 0) and (phase_lag >= 0) and (phase_lag < 360) and (speed_deg_per_hour > 0))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_tc_set_code` | Yes | constituent_set_id, constituent_code |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_tc_set` | constituent_set_id | [`tide_constituent_set`](#tide_constituent_set) (id) |  |

---
