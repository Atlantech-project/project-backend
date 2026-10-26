<!-- Generated from docs/reference/database/PortSync.sql lines 568-573 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `tide_prediction_point` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `tide_prediction_run_id` bigint NOT NULL,
  `valid_at` datetime NOT NULL,
  `height` decimal(5,3) NOT NULL COMMENT 'm above datum_reference'
);

<!-- Generated from docs/reference/database/PortSync.md lines 1849-1870 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `tide_prediction_point`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `tide_prediction_run_id` | `bigint` | NO |  | FK → `tide_prediction_run.id` |  |
| `valid_at` | `datetime` | NO |  |  |  |
| `height` | `decimal(5,3)` | NO |  |  | m above datum_reference |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_tpp_run_time` | Yes | tide_prediction_run_id, valid_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_tpp_run` | tide_prediction_run_id | [`tide_prediction_run`](#tide_prediction_run) (id) |  |

---
