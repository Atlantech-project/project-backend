<!-- Generated from docs/reference/database/PortSync.sql lines 614-623 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `demand_forecast_slot` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `generated_at` datetime NOT NULL,
  `slot_start` datetime NOT NULL,
  `slot_end` datetime NOT NULL,
  `expected_arrivals` int NOT NULL,
  `congestion_score` decimal(4,3) NOT NULL,
  `method` ENUM ('AGGREGATION', 'LIGHTGBM') NOT NULL,
  `model_version_id` bigint COMMENT 'NULL in Phase 1 (aggregation)'
);

<!-- Generated from docs/reference/database/PortSync.md lines 1994-2027 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `demand_forecast_slot`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `generated_at` | `datetime` | NO |  |  |  |
| `slot_start` | `datetime` | NO |  |  |  |
| `slot_end` | `datetime` | NO |  |  |  |
| `expected_arrivals` | `int` | NO |  |  |  |
| `congestion_score` | `decimal(4,3)` | NO |  |  |  |
| `method` | `ENUM(AGGREGATION, LIGHTGBM)` | NO |  |  |  |
| `model_version_id` | `bigint` | YES |  | FK → `ml_model_version.id` | NULL in Phase 1 (aggregation) |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_dfs_method` | `((method = 'LIGHTGBM') = (model_version_id is not null))` |
| `chk_dfs_values` | `((slot_end > slot_start) and (expected_arrivals >= 0) and (congestion_score between 0 and 1))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_dfs_model` | No | model_version_id |
| `idx_dfs_slot` | No | slot_start |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_dfs_model` | model_version_id | [`ml_model_version`](#ml_model_version) (id) |  |

---
