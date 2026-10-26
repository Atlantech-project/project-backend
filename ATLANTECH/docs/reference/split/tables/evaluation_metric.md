<!-- Generated from docs/reference/database/PortSync.sql lines 860-870 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `evaluation_metric` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `model_version_id` bigint COMMENT 'NULL for system-level metrics',
  `retraining_run_id` bigint,
  `metric_name` ENUM ('MAE', 'OVERRIDE_RATE', 'WAIT_TIME', 'WINDOW_ACCURACY') NOT NULL,
  `dataset_type` ENUM ('HOLDOUT', 'ROLLING', 'PRODUCTION') NOT NULL,
  `metric_value` decimal(14,5) NOT NULL,
  `period_start` datetime NOT NULL,
  `period_end` datetime NOT NULL,
  `computed_at` datetime NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 2789-2824 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `evaluation_metric`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `model_version_id` | `bigint` | YES |  | FK → `ml_model_version.id` | NULL for system-level metrics |
| `retraining_run_id` | `bigint` | YES |  | FK → `retraining_run.id` |  |
| `metric_name` | `ENUM(MAE, OVERRIDE_RATE, WAIT_TIME, WINDOW_ACCURACY)` | NO |  |  |  |
| `dataset_type` | `ENUM(HOLDOUT, ROLLING, PRODUCTION)` | NO |  |  |  |
| `metric_value` | `decimal(14,5)` | NO |  |  |  |
| `period_start` | `datetime` | NO |  |  |  |
| `period_end` | `datetime` | NO |  |  |  |
| `computed_at` | `datetime` | NO |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_em_period` | `(period_end > period_start)` |
| `chk_em_scope` | `(((metric_name <> 'MAE') or (model_version_id is not null)) and ((metric_name not in ('OVERRIDE_RATE','WAIT_TIME')) or (model_version_id is null)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_em_run` | No | retraining_run_id |
| `fk_em_version` | No | model_version_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_em_run` | retraining_run_id | [`retraining_run`](#retraining_run) (id) |  |
| `fk_em_version` | model_version_id | [`ml_model_version`](#ml_model_version) (id) |  |

---
