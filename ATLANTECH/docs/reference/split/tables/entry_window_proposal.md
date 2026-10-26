<!-- Generated from docs/reference/database/PortSync.sql lines 625-641 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `entry_window_proposal` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `berthing_request_id` bigint NOT NULL,
  `forecast_run_id` bigint NOT NULL,
  `tide_prediction_run_id` bigint NOT NULL,
  `bias_correction_version_id` bigint,
  `peak_congestion_score` decimal(4,3),
  `status` ENUM ('PROPOSED', 'CONFIRMED', 'REPLACED', 'REJECTED') NOT NULL DEFAULT 'PROPOSED',
  `confirmed_key` bigint UNIQUE COMMENT 'GENERATED (stored): if((status = ''CONFIRMED''),berthing_request_id,NULL)',
  `generated_at` datetime NOT NULL,
  `confirmed_window_id` bigint,
  `confirmed_by` bigint,
  `confirmed_at` datetime,
  `rejection_reason` varchar(500) COMMENT 'Required when REJECTED (all windows rejected)',
  `rejected_by` bigint,
  `rejected_at` datetime
);

<!-- Generated from docs/reference/database/PortSync.md lines 2029-2086 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `entry_window_proposal`

> Part 1 output (D2). Confirmed window feeds Part 2 (D3). data_versions = the three run FKs.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI · FK → `feasible_window.id, proposal_id` |  |
| `berthing_request_id` | `bigint` | NO |  | FK → `berthing_request.id` |  |
| `forecast_run_id` | `bigint` | NO |  | FK → `forecast_run.id` |  |
| `tide_prediction_run_id` | `bigint` | NO |  | FK → `tide_prediction_run.id` |  |
| `bias_correction_version_id` | `bigint` | YES |  | FK → `ml_model_version.id` |  |
| `peak_congestion_score` | `decimal(4,3)` | YES |  |  |  |
| `status` | `ENUM(PROPOSED, CONFIRMED, REPLACED, REJECTED)` | NO | `'PROPOSED'` |  |  |
| `confirmed_key` | `bigint` | YES |  | UQ | GENERATED (stored): if((status = 'CONFIRMED'),berthing_request_id,NULL) |
| `generated_at` | `datetime` | NO |  |  |  |
| `confirmed_window_id` | `bigint` | YES |  | FK → `feasible_window.id, proposal_id` |  |
| `confirmed_by` | `bigint` | YES |  | FK → `user.id` |  |
| `confirmed_at` | `datetime` | YES |  |  |  |
| `rejection_reason` | `varchar(500)` | YES |  |  | Required when REJECTED (all windows rejected) |
| `rejected_by` | `bigint` | YES |  | FK → `user.id` |  |
| `rejected_at` | `datetime` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ewp_confirm_set` | `(((confirmed_window_id is null) = (confirmed_by is null)) and ((confirmed_by is null) = (confirmed_at is null)))` |
| `chk_ewp_confirmed` | `((status <> 'CONFIRMED') or (confirmed_window_id is not null))` |
| `chk_ewp_score` | `((peak_congestion_score is null) or (peak_congestion_score between 0 and 1))` |
| `chk_ewp_reject_set` | `(((rejected_by IS NULL) = (rejected_at IS NULL)) AND ((status = 'REJECTED') = (rejection_reason IS NOT NULL)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_ewp_bias` | No | bias_correction_version_id |
| `fk_ewp_forecast` | No | forecast_run_id |
| `fk_ewp_request` | No | berthing_request_id |
| `fk_ewp_tide` | No | tide_prediction_run_id |
| `fk_ewp_user` | No | confirmed_by |
| `fk_ewp_window` | No | confirmed_window_id, id |
| `uq_ewp_id_request` | Yes | id, berthing_request_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ewp_bias` | bias_correction_version_id | [`ml_model_version`](#ml_model_version) (id) |  |
| `fk_ewp_forecast` | forecast_run_id | [`forecast_run`](#forecast_run) (id) |  |
| `fk_ewp_request` | berthing_request_id | [`berthing_request`](#berthing_request) (id) |  |
| `fk_ewp_tide` | tide_prediction_run_id | [`tide_prediction_run`](#tide_prediction_run) (id) |  |
| `fk_ewp_user` | confirmed_by | [`user`](#user) (id) |  |
| `fk_ewp_window` | confirmed_window_id, id | [`feasible_window`](#feasible_window) (id, proposal_id) |  |
| `fk_ewp_rejected_by` | rejected_by | [`user`](#user) (id) |  |

**Referenced by:** [`part1_run`](#part1_run).entry_window_proposal_id, [`blocked_period`](#blocked_period).proposal_id, [`feasible_window`](#feasible_window).proposal_id, [`recommendation`](#recommendation).entry_window_proposal_id, berthing_request_id

---
