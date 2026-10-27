<!-- Generated from docs/reference/database/PortSync.sql lines 872-885 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `retraining_run` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `ml_model_id` bigint NOT NULL,
  `champion_version_id` bigint COMMENT 'NULL at cold start',
  `challenger_version_id` bigint NOT NULL,
  `started_at` datetime NOT NULL,
  `finished_at` datetime,
  `decision` ENUM ('PROMOTED', 'KEPT_CHAMPION', 'FAILED') NOT NULL,
  `decision_reason` varchar(500),
  `review_pending` tinyint(1) COMMENT 'GENERATED (stored): ((decision in (_latin1''KEPT_CHAMPION'',_latin1''FAILED'')) and (reviewed_at is null)) | TRUE until someone reviews a kept-champion or failed run (D12, P20-02)',
  `reviewed_by` bigint,
  `reviewed_at` datetime,
  `review_note` varchar(500)
);

<!-- Generated from docs/reference/database/PortSync.md lines 2826-2873 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `retraining_run`

> Champion vs Challenger history. Hard safety limits are never changed by retraining (D12).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `ml_model_id` | `bigint` | NO |  | FK → `ml_model_version.id, ml_model_id` |  |
| `champion_version_id` | `bigint` | YES |  | FK → `ml_model_version.id, ml_model_id` | NULL at cold start |
| `challenger_version_id` | `bigint` | NO |  | FK → `ml_model_version.id, ml_model_id` |  |
| `started_at` | `datetime` | NO |  |  |  |
| `finished_at` | `datetime` | YES |  |  |  |
| `decision` | `ENUM(PROMOTED, KEPT_CHAMPION, FAILED)` | NO |  |  |  |
| `decision_reason` | `varchar(500)` | YES |  |  |  |
| `review_pending` | `tinyint(1)` | YES |  |  | GENERATED (stored): ((decision in ('KEPT_CHAMPION','FAILED')) and (reviewed_at is null)) \| TRUE until someone reviews a kept-champion or failed run (D12, P20-02) |
| `reviewed_by` | `bigint` | YES |  | FK → `user.id` |  |
| `reviewed_at` | `datetime` | YES |  |  |  |
| `review_note` | `varchar(500)` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_rr_review` | `((reviewed_by is null) = (reviewed_at is null))` |
| `chk_rr_time` | `((finished_at is null) or (finished_at >= started_at))` |
| `chk_rr_versions` | `((champion_version_id is null) or (champion_version_id <> challenger_version_id))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_rr_challenger` | No | challenger_version_id, ml_model_id |
| `fk_rr_champion` | No | champion_version_id, ml_model_id |
| `fk_rr_model` | No | ml_model_id |
| `fk_rr_user` | No | reviewed_by |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_rr_challenger` | challenger_version_id, ml_model_id | [`ml_model_version`](#ml_model_version) (id, ml_model_id) |  |
| `fk_rr_champion` | champion_version_id, ml_model_id | [`ml_model_version`](#ml_model_version) (id, ml_model_id) |  |
| `fk_rr_model` | ml_model_id | [`ml_model`](#ml_model) (id) |  |
| `fk_rr_user` | reviewed_by | [`user`](#user) (id) |  |

**Referenced by:** [`evaluation_metric`](#evaluation_metric).retraining_run_id

---
