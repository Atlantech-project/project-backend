<!-- Generated from docs/reference/database/PortSync.sql lines 583-588 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `ml_model` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `model_key` ENUM ('M2_SERVICE_TIME', 'M3_DEMAND_FORECAST', 'M4_DELAY_RISK', 'FORECAST_BIAS_CORRECTION') UNIQUE NOT NULL,
  `algorithm_family` varchar(40) NOT NULL,
  `description` varchar(255)
);

<!-- Generated from docs/reference/database/PortSync.md lines 1906-1917 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `ml_model`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `model_key` | `ENUM(M2_SERVICE_TIME, M3_DEMAND_FORECAST, M4_DELAY_RISK, FORECAST_BIAS_CORRECTION)` | NO |  | UQ |  |
| `algorithm_family` | `varchar(40)` | NO |  |  |  |
| `description` | `varchar(255)` | YES |  |  |  |

**Referenced by:** [`ml_model_version`](#ml_model_version).ml_model_id, [`retraining_run`](#retraining_run).ml_model_id

---
