<!-- Generated from docs/reference/database/PortSync.sql lines 643-649 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `feasible_window` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `proposal_id` bigint NOT NULL,
  `window_start` datetime NOT NULL,
  `window_end` datetime NOT NULL,
  `confidence` decimal(4,3)
);

<!-- Generated from docs/reference/database/PortSync.md lines 2088-2119 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `feasible_window`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `proposal_id` | `bigint` | NO |  | FK → `entry_window_proposal.id` |  |
| `window_start` | `datetime` | NO |  |  |  |
| `window_end` | `datetime` | NO |  |  |  |
| `confidence` | `decimal(4,3)` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_fw_values` | `((window_end > window_start) and ((confidence is null) or (confidence between 0 and 1)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_fw_proposal` | No | proposal_id |
| `uq_fw_id_proposal` | Yes | id, proposal_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_fw_proposal` | proposal_id | [`entry_window_proposal`](#entry_window_proposal) (id) |  |

**Referenced by:** [`entry_window_proposal`](#entry_window_proposal).confirmed_window_id, id

---
