<!-- Generated from docs/reference/database/PortSync.sql lines 575-581 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `tide_prediction_run` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `constituent_set_id` bigint NOT NULL,
  `computed_at` datetime NOT NULL,
  `horizon_start` datetime NOT NULL,
  `horizon_end` datetime NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 1872-1904 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `tide_prediction_run`

> Daily in-house harmonic computation (D7, D10).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `constituent_set_id` | `bigint` | NO |  | FK → `tide_constituent_set.id` |  |
| `computed_at` | `datetime` | NO |  |  |  |
| `horizon_start` | `datetime` | NO |  |  |  |
| `horizon_end` | `datetime` | NO |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_tpr_horizon` | `(horizon_end > horizon_start)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_tpr_set` | No | constituent_set_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_tpr_set` | constituent_set_id | [`tide_constituent_set`](#tide_constituent_set) (id) |  |

**Referenced by:** [`tide_prediction_point`](#tide_prediction_point).tide_prediction_run_id, [`entry_window_proposal`](#entry_window_proposal).tide_prediction_run_id

---
