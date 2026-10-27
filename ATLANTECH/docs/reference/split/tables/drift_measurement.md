<!-- Generated from docs/reference/database/PortSync.sql lines 849-858 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `drift_measurement` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `model_version_id` bigint NOT NULL,
  `metric_type` ENUM ('PSI', 'ROLLING_MAE') NOT NULL,
  `feature_name` varchar(60) COMMENT 'Required for PSI',
  `metric_value` decimal(14,5) NOT NULL,
  `threshold` decimal(14,5) NOT NULL,
  `is_drifted` tinyint(1) COMMENT 'GENERATED (stored): (metric_value > threshold)',
  `measured_at` datetime NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 2756-2787 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `drift_measurement`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `model_version_id` | `bigint` | NO |  | FK → `ml_model_version.id` |  |
| `metric_type` | `ENUM(PSI, ROLLING_MAE)` | NO |  |  |  |
| `feature_name` | `varchar(60)` | YES |  |  | Required for PSI |
| `metric_value` | `decimal(14,5)` | NO |  |  |  |
| `threshold` | `decimal(14,5)` | NO |  |  |  |
| `is_drifted` | `tinyint(1)` | YES |  |  | GENERATED (stored): (metric_value > threshold) |
| `measured_at` | `datetime` | NO |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_dm_feature` | `((metric_type = 'PSI') = (feature_name is not null))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_dm_version` | No | model_version_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_dm_version` | model_version_id | [`ml_model_version`](#ml_model_version) (id) |  |

---
