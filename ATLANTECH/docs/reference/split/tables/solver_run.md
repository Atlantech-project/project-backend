<!-- Generated from docs/reference/database/PortSync.sql lines 696-710 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `solver_run` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `trigger_type` ENUM ('NEW_REQUEST', 'REPLAN', 'RESOURCE_CHANGE', 'MANUAL') NOT NULL,
  `triggered_by` bigint,
  `horizon_start` datetime NOT NULL,
  `horizon_end` datetime NOT NULL,
  `status` ENUM ('QUEUED', 'RUNNING', 'OPTIMAL', 'FEASIBLE', 'INFEASIBLE', 'ERROR') NOT NULL DEFAULT 'QUEUED',
  `objective_value` decimal(14,4),
  `solve_time_ms` int,
  `solver_version` varchar(40) NOT NULL,
  `result_detail` json COMMENT 'INFEASIBLE: binding constraints; ERROR: error message',
  `queued_at` datetime NOT NULL,
  `started_at` datetime,
  `finished_at` datetime
);

<!-- Generated from docs/reference/database/PortSync.md lines 2266-2309 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `solver_run`

> Port-wide joint solve (FR-1.4.1). INFEASIBLE is final; FIFO fallback only on technical failure (recommendation.source = FIFO).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `trigger_type` | `ENUM(NEW_REQUEST, REPLAN, RESOURCE_CHANGE, MANUAL)` | NO |  |  |  |
| `triggered_by` | `bigint` | YES |  | FK → `user.id` |  |
| `horizon_start` | `datetime` | NO |  |  |  |
| `horizon_end` | `datetime` | NO |  |  |  |
| `status` | `ENUM(QUEUED, RUNNING, OPTIMAL, FEASIBLE, INFEASIBLE, ERROR)` | NO | `'QUEUED'` |  |  |
| `objective_value` | `decimal(14,4)` | YES |  |  |  |
| `solve_time_ms` | `int` | YES |  |  |  |
| `solver_version` | `varchar(40)` | NO |  |  |  |
| `result_detail` | `json` | YES |  |  | INFEASIBLE: binding constraints; ERROR: error message |
| `queued_at` | `datetime` | NO |  |  |  |
| `started_at` | `datetime` | YES |  |  |  |
| `finished_at` | `datetime` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_sr_finished` | `((status in ('QUEUED','RUNNING')) or (finished_at is not null))` |
| `chk_sr_horizon` | `(horizon_end > horizon_start)` |
| `chk_sr_manual` | `((trigger_type <> 'MANUAL') or (triggered_by is not null))` |
| `chk_sr_time` | `((solve_time_ms is null) or (solve_time_ms >= 0))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_sr_user` | No | triggered_by |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_sr_user` | triggered_by | [`user`](#user) (id) |  |

**Referenced by:** [`recommendation`](#recommendation).solver_run_id, [`solver_run_model_version`](#solver_run_model_version).solver_run_id

---
