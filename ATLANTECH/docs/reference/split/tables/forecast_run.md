<!-- Generated from docs/reference/database/PortSync.sql lines 533-548 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `forecast_run` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `source` varchar(80) NOT NULL COMMENT 'Weather provider',
  `pull_type` ENUM ('SCHEDULED', 'ON_DEMAND', 'MANUAL') NOT NULL,
  `provider_rank` ENUM ('PRIMARY', 'SECONDARY', 'MANUAL') COMMENT 'Which provider supplied the data',
  `data_stale` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = latest data older than the allowed age',
  `berthing_request_id` bigint COMMENT 'Set only for ON_DEMAND (Customs Approval)',
  `submitted_by` bigint COMMENT 'Required when pull_type = MANUAL',
  `status` ENUM ('SUCCESS', 'FAILED') NOT NULL,
  `pulled_at` datetime NOT NULL,
  `horizon_start` datetime,
  `horizon_end` datetime,
  `wind_speed_unit` ENUM ('KNOTS', 'METRES_PER_SECOND', 'KM_PER_HOUR') NOT NULL COMMENT 'Unit of wind_speed / wind_gust as sent by the provider',
  `current_speed_unit` ENUM ('KNOTS', 'METRES_PER_SECOND', 'KM_PER_HOUR') NOT NULL,
  `visibility_unit` ENUM ('NAUTICAL_MILES', 'KILOMETRES', 'METRES') NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 1755-1801 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `forecast_run`

> Location = port_profile lat/lon (single port).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `source` | `varchar(80)` | NO |  |  | Weather provider |
| `pull_type` | `ENUM(SCHEDULED, ON_DEMAND, MANUAL)` | NO |  |  |  |
| `provider_rank` | `ENUM(PRIMARY, SECONDARY, MANUAL)` | YES |  |  | Which provider supplied the data |
| `data_stale` | `tinyint(1)` | NO | `0` |  | 1 = latest data older than the allowed age |
| `berthing_request_id` | `bigint` | YES |  | FK → `berthing_request.id` | Set only for ON_DEMAND (Customs Approval) |
| `submitted_by` | `bigint` | YES |  | FK → `user.id` | Required when pull_type = MANUAL |
| `status` | `ENUM(SUCCESS, FAILED)` | NO |  |  |  |
| `pulled_at` | `datetime` | NO |  |  |  |
| `horizon_start` | `datetime` | YES |  |  |  |
| `horizon_end` | `datetime` | YES |  |  |  |
| `wind_speed_unit` | `ENUM(KNOTS, METRES_PER_SECOND, KM_PER_HOUR)` | NO |  |  | Unit of wind_speed / wind_gust as sent by the provider |
| `current_speed_unit` | `ENUM(KNOTS, METRES_PER_SECOND, KM_PER_HOUR)` | NO |  |  |  |
| `visibility_unit` | `ENUM(NAUTICAL_MILES, KILOMETRES, METRES)` | NO |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_fr_horizon` | `((horizon_start is null) or (horizon_end is null) or (horizon_end > horizon_start))` |
| `chk_fr_on_demand` | `((pull_type = 'SCHEDULED') = (berthing_request_id is null))` |
| `chk_fr_manual` | `((pull_type = 'MANUAL') = (submitted_by is not null))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_fr_request` | No | berthing_request_id |
| `fk_fr_submitted_by` | No | submitted_by |
| `idx_fr_pulled` | No | pulled_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_fr_request` | berthing_request_id | [`berthing_request`](#berthing_request) (id) |  |
| `fk_fr_submitted_by` | submitted_by | [`user`](#user) (id) |  |

**Referenced by:** [`environment_forecast_point`](#environment_forecast_point).forecast_run_id, [`entry_window_proposal`](#entry_window_proposal).forecast_run_id

---
