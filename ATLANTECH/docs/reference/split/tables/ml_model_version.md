<!-- Generated from docs/reference/database/PortSync.sql lines 590-604 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `ml_model_version` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `ml_model_id` bigint NOT NULL,
  `version_no` int NOT NULL,
  `status` ENUM ('CHALLENGER', 'CHAMPION', 'ARCHIVED', 'REJECTED') NOT NULL DEFAULT 'CHALLENGER',
  `champion_key` bigint UNIQUE COMMENT 'GENERATED (stored): if((status = _latin1''CHAMPION''),ml_model_id,NULL)',
  `hyperparameters` json,
  `training_window_start` datetime,
  `training_window_end` datetime,
  `training_row_count` int,
  `artifact_path` varchar(255) NOT NULL COMMENT 'Local file volume only (D8)',
  `trained_at` datetime NOT NULL,
  `deployed_at` datetime,
  `retired_at` datetime
);

<!-- Generated from docs/reference/database/PortSync.md lines 1919-1960 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `ml_model_version`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `ml_model_id` | `bigint` | NO |  | FK → `ml_model.id` |  |
| `version_no` | `int` | NO |  |  |  |
| `status` | `ENUM(CHALLENGER, CHAMPION, ARCHIVED, REJECTED)` | NO | `'CHALLENGER'` |  |  |
| `champion_key` | `bigint` | YES |  | UQ | GENERATED (stored): if((status = 'CHAMPION'),ml_model_id,NULL) |
| `hyperparameters` | `json` | YES |  |  |  |
| `training_window_start` | `datetime` | YES |  |  |  |
| `training_window_end` | `datetime` | YES |  |  |  |
| `training_row_count` | `int` | YES |  |  |  |
| `artifact_path` | `varchar(255)` | NO |  |  | Local file volume only (D8) |
| `trained_at` | `datetime` | NO |  |  |  |
| `deployed_at` | `datetime` | YES |  |  |  |
| `retired_at` | `datetime` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_mlv_champion_deployed` | `((status <> 'CHAMPION') or (deployed_at is not null))` |
| `chk_mlv_version` | `((version_no > 0) and ((training_row_count is null) or (training_row_count >= 0)))` |
| `chk_mlv_window` | `((training_window_start is null) or (training_window_end is null) or (training_window_end > training_window_start))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_mlv_id_model` | Yes | id, ml_model_id |
| `uq_mlv_model_version` | Yes | ml_model_id, version_no |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_mlv_model` | ml_model_id | [`ml_model`](#ml_model) (id) |  |

**Referenced by:** [`demand_forecast_slot`](#demand_forecast_slot).model_version_id, [`entry_window_proposal`](#entry_window_proposal).bias_correction_version_id, [`solver_run_model_version`](#solver_run_model_version).model_version_id, [`drift_measurement`](#drift_measurement).model_version_id, [`evaluation_metric`](#evaluation_metric).model_version_id, [`retraining_run`](#retraining_run).challenger_version_id, ml_model_id, [`retraining_run`](#retraining_run).champion_version_id, ml_model_id

---
