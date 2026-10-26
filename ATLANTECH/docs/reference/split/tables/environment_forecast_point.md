<!-- Generated from docs/reference/database/PortSync.sql lines 518-531 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `environment_forecast_point` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `forecast_run_id` bigint NOT NULL,
  `valid_at` datetime NOT NULL,
  `wind_speed` decimal(5,2) COMMENT 'Unit: forecast_run.wind_speed_unit',
  `wind_gust` decimal(5,2) COMMENT 'Unit: forecast_run.wind_speed_unit',
  `wind_direction_deg` decimal(4,1) COMMENT 'Direction the wind comes from, degrees true',
  `visibility` decimal(8,2) COMMENT 'Unit: forecast_run.visibility_unit',
  `wave_height` decimal(4,2) COMMENT 'm (significant)',
  `wave_period` decimal(4,1) COMMENT 's',
  `wave_direction_deg` decimal(4,1) COMMENT 'Direction the waves come from, degrees true',
  `current_speed` decimal(4,2) COMMENT 'Unit: forecast_run.current_speed_unit',
  `sea_level` decimal(5,3) COMMENT 'm above the active tide datum'
);

<!-- Generated from docs/reference/database/PortSync.md lines 1715-1753 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `environment_forecast_point`

> Raw provider values; unit conversion and derived values (e.g. surge) are AI-side (P12-02).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `forecast_run_id` | `bigint` | NO |  | FK → `forecast_run.id` |  |
| `valid_at` | `datetime` | NO |  |  |  |
| `wind_speed` | `decimal(5,2)` | YES |  |  | Unit: forecast_run.wind_speed_unit |
| `wind_gust` | `decimal(5,2)` | YES |  |  | Unit: forecast_run.wind_speed_unit |
| `wind_direction_deg` | `decimal(4,1)` | YES |  |  | Direction the wind comes from, degrees true |
| `visibility` | `decimal(8,2)` | YES |  |  | Unit: forecast_run.visibility_unit |
| `wave_height` | `decimal(4,2)` | YES |  |  | m (significant) |
| `wave_period` | `decimal(4,1)` | YES |  |  | s |
| `wave_direction_deg` | `decimal(4,1)` | YES |  |  | Direction the waves come from, degrees true |
| `current_speed` | `decimal(4,2)` | YES |  |  | Unit: forecast_run.current_speed_unit |
| `sea_level` | `decimal(5,3)` | YES |  |  | m above the active tide datum |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_efp_direction` | `(((wind_direction_deg is null) or ((wind_direction_deg >= 0) and (wind_direction_deg < 360))) and ((wave_direction_deg is null) or ((wave_direction_deg >= 0) and (wave_direction_deg < 360))))` |
| `chk_efp_values` | `(((wind_speed is null) or (wind_speed >= 0)) and ((wind_gust is null) or (wind_gust >= 0)) and ((visibility is null) or (visibility >= 0)) and ((wave_height is null) or (wave_height >= 0)) and ((wave_period is null) or (wave_period >= 0)) and ((current_speed is null) or (current_speed >= 0)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_efp_run_time` | Yes | forecast_run_id, valid_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_efp_run` | forecast_run_id | [`forecast_run`](#forecast_run) (id) |  |

---
