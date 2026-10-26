<!-- Generated from docs/reference/database/PortSync.sql lines 673-688 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `recommendation_option` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `recommendation_id` bigint NOT NULL,
  `terminal_id` bigint NOT NULL COMMENT 'Copy, enforced by composite FKs: berth must be allowed for the requested terminal (berth_terminal)',
  `option_rank` int NOT NULL,
  `berth_id` bigint NOT NULL,
  `planned_start` datetime NOT NULL,
  `planned_end` datetime NOT NULL,
  `pilot_user_id` bigint,
  `service_time_method` ENUM ('FORMULA', 'ML') NOT NULL,
  `service_time_p50_hours` decimal(6,2) NOT NULL,
  `service_time_p90_hours` decimal(6,2) COMMENT 'ML only',
  `delay_risk` decimal(4,3),
  `objective_score` decimal(14,4),
  `rationale_template_id` bigint
);

<!-- Generated from docs/reference/database/PortSync.md lines 2187-2238 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `recommendation_option`

> No is_selected column: the selected option is the one operational_plan references (backend reads it, P15-03).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `recommendation_id` | `bigint` | NO |  | FK → `recommendation.id, terminal_id` |  |
| `terminal_id` | `bigint` | NO |  | FK → `berth_terminal.berth_id, terminal_id` | Copy, enforced by composite FKs: berth must be allowed for the requested terminal (berth_terminal) |
| `option_rank` | `int` | NO |  |  |  |
| `berth_id` | `bigint` | NO |  | FK → `berth_terminal.berth_id, terminal_id` |  |
| `planned_start` | `datetime` | NO |  |  |  |
| `planned_end` | `datetime` | NO |  |  |  |
| `pilot_user_id` | `bigint` | YES |  | FK → `pilot_profile.user_id` |  |
| `service_time_method` | `ENUM(FORMULA, ML)` | NO |  |  |  |
| `service_time_p50_hours` | `decimal(6,2)` | NO |  |  |  |
| `service_time_p90_hours` | `decimal(6,2)` | YES |  |  | ML only |
| `delay_risk` | `decimal(4,3)` | YES |  |  |  |
| `objective_score` | `decimal(14,4)` | YES |  |  |  |
| `rationale_template_id` | `bigint` | YES |  | FK → `rationale_template.id` |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ro_p90` | `(((service_time_method = 'ML') = (service_time_p90_hours is not null)) and ((service_time_p90_hours is null) or (service_time_p90_hours >= service_time_p50_hours)))` |
| `chk_ro_values` | `((option_rank > 0) and (planned_end > planned_start) and (service_time_p50_hours > 0) and ((delay_risk is null) or (delay_risk between 0 and 1)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_ro_berth` | No | berth_id, terminal_id |
| `fk_ro_pilot` | No | pilot_user_id |
| `fk_ro_rec` | No | recommendation_id, terminal_id |
| `fk_ro_template` | No | rationale_template_id |
| `idx_ro_berth_time` | No | berth_id, planned_start |
| `uq_ro_id_rec` | Yes | id, recommendation_id |
| `uq_ro_rank` | Yes | recommendation_id, option_rank |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ro_berth` | berth_id, terminal_id | [`berth_terminal`](#berth_terminal) (berth_id, terminal_id) |  |
| `fk_ro_pilot` | pilot_user_id | [`pilot_profile`](#pilot_profile) (user_id) |  |
| `fk_ro_rec` | recommendation_id, terminal_id | [`recommendation`](#recommendation) (id, terminal_id) |  |
| `fk_ro_template` | rationale_template_id | [`rationale_template`](#rationale_template) (id) |  |

**Referenced by:** [`recommendation_option_tug`](#recommendation_option_tug).option_id, [`recommendation_override`](#recommendation_override).recommendation_option_id, [`recommendation_rationale_factor`](#recommendation_rationale_factor).option_id, [`operational_plan`](#operational_plan).recommendation_option_id, recommendation_id

---
