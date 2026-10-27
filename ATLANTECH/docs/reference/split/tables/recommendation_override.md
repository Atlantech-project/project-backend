<!-- Generated from docs/reference/database/PortSync.sql lines 724-739 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `recommendation_override` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `recommendation_option_id` bigint NOT NULL,
  `component_type` ENUM ('BERTH', 'PILOT', 'TUG', 'START_TIME') NOT NULL,
  `original_berth_id` bigint,
  `override_berth_id` bigint,
  `original_pilot_user_id` bigint,
  `override_pilot_user_id` bigint,
  `original_tugboat_id` bigint COMMENT 'NULL = tug added',
  `override_tugboat_id` bigint COMMENT 'NULL = tug removed',
  `original_start` datetime,
  `override_start` datetime COMMENT 'New end is computed: override_start + option service_time_p50_hours (P16-03)',
  `override_reason` varchar(500) NOT NULL,
  `overridden_by` bigint NOT NULL,
  `overridden_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

<!-- Generated from docs/reference/database/PortSync.md lines 2349-2405 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `recommendation_override`

> Typed override of a recommendation option (FR-1.4.7). Signal for Part 3, not an automatic retrain trigger (D12).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `recommendation_option_id` | `bigint` | NO |  | FK → `recommendation_option.id` |  |
| `component_type` | `ENUM(BERTH, PILOT, TUG, START_TIME)` | NO |  |  |  |
| `original_berth_id` | `bigint` | YES |  | FK → `berth.id` |  |
| `override_berth_id` | `bigint` | YES |  | FK → `berth.id` |  |
| `original_pilot_user_id` | `bigint` | YES |  | FK → `pilot_profile.user_id` |  |
| `override_pilot_user_id` | `bigint` | YES |  | FK → `pilot_profile.user_id` |  |
| `original_tugboat_id` | `bigint` | YES |  | FK → `tugboat.id` | NULL = tug added |
| `override_tugboat_id` | `bigint` | YES |  | FK → `tugboat.id` | NULL = tug removed |
| `original_start` | `datetime` | YES |  |  |  |
| `override_start` | `datetime` | YES |  |  | New end is computed: override_start + option service_time_p50_hours (P16-03) |
| `override_reason` | `varchar(500)` | NO |  |  |  |
| `overridden_by` | `bigint` | NO |  | FK → `user.id` |  |
| `overridden_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_rov_berth` | `(((component_type = 'BERTH') = (override_berth_id is not null)) and ((component_type = 'BERTH') = (original_berth_id is not null)))` |
| `chk_rov_pilot` | `((component_type = 'PILOT') = ((original_pilot_user_id is not null) or (override_pilot_user_id is not null)))` |
| `chk_rov_time` | `(((component_type = 'START_TIME') = (override_start is not null)) and ((component_type = 'START_TIME') = (original_start is not null)))` |
| `chk_rov_tug` | `((component_type = 'TUG') = ((original_tugboat_id is not null) or (override_tugboat_id is not null)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_rov_new_berth` | No | override_berth_id |
| `fk_rov_new_pilot` | No | override_pilot_user_id |
| `fk_rov_new_tug` | No | override_tugboat_id |
| `fk_rov_option` | No | recommendation_option_id |
| `fk_rov_orig_berth` | No | original_berth_id |
| `fk_rov_orig_pilot` | No | original_pilot_user_id |
| `fk_rov_orig_tug` | No | original_tugboat_id |
| `fk_rov_user` | No | overridden_by |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_rov_new_berth` | override_berth_id | [`berth`](#berth) (id) |  |
| `fk_rov_new_pilot` | override_pilot_user_id | [`pilot_profile`](#pilot_profile) (user_id) |  |
| `fk_rov_new_tug` | override_tugboat_id | [`tugboat`](#tugboat) (id) |  |
| `fk_rov_option` | recommendation_option_id | [`recommendation_option`](#recommendation_option) (id) |  |
| `fk_rov_orig_berth` | original_berth_id | [`berth`](#berth) (id) |  |
| `fk_rov_orig_pilot` | original_pilot_user_id | [`pilot_profile`](#pilot_profile) (user_id) |  |
| `fk_rov_orig_tug` | original_tugboat_id | [`tugboat`](#tugboat) (id) |  |
| `fk_rov_user` | overridden_by | [`user`](#user) (id) |  |

---
