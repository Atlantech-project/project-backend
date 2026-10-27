<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 257-313 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-08 · Database read source and worker privileges

| | |
| --- | --- |
| Source | D-09 · OD-21 · AIBD-028 |
| Owner | Backend Team |
| Status | Closed |
| Closing task | DEC-08 · Sprint 1 |
| Affects | P3-I5 · AI-013 |
| Decision | Closed (2026-10-27): Option 1. Workers read from the primary; six accounts (`w_part1`, `w_part2`, `w_yard`, `w_edi`, `w_validate`, `w_part3`); no DELETE, no DDL, column-level UPDATE on tables a worker does not own; no `audit_log` access (the Backend writes all audit entries on C-06). Rule: **workers insert new rows; the Backend retires old ones** (REPLACED / ACTIVE transitions). Full matrix under "Final grants" below. The Backend writes the grants script. |

<div dir="rtl">

**القصة.** لو كل الـ workers داخلين على الـ database بنفس الحساب بكل الصلاحيات، أي bug في الـ parser ممكن يمسح جدول الخطط. ولو قرأوا من نسخة متأخرة، ممكن يحسبوا على رصيف اتحجز من ثواني.

</div>

**Topic.** Each worker must read port state and write only its own results.

**Options:**

1. Read from the primary, one account per worker with limited rights, `audit_log` INSERT/SELECT only.
2. Read from the replica and tolerate lag.

**Recommendation.** Option 1 (agreed); the Backend writes the grants script.

**Final grants (agreed with the AI Team, 2026-10-27):**

| Account | Queue / trigger | INSERT | UPDATE (columns) |
| --- | --- | --- | --- |
| `w_part1` | `queue:part1` + Part 1 scheduler (weather pull, daily tide, demand forecast) | `entry_window_proposal` · `feasible_window` · `blocked_period` · `forecast_run` (SCHEDULED / ON_DEMAND) · `environment_forecast_point` · `tide_prediction_run` · `tide_prediction_point` · `demand_forecast_slot` | `part1_run` (`status`, `outcome`, `retry_count`, `entry_window_proposal_id`, `started_at`, `finished_at`) on its own run only |
| `w_part2` | `queue:part2` | `solver_run_model_version` · `recommendation` · `recommendation_option` · `recommendation_option_tug` · `recommendation_rationale_factor` | `solver_run` (`status`, `objective_value`, `solve_time_ms`, `solver_version`, `result_detail`, `started_at`, `finished_at`) |
| `w_yard` | `queue:yard` | `yard_placement_item` | `yard_run` (`status`, `outcome`, `error_category`, `result_detail`, `worker_version`, `started_at`, `finished_at`) · `terminal_recommendation` (`lifecycle_status`, `confidence`, `rationale_template_id`, `failure_reason`) |
| `w_edi` | `queue:edi` (parse and BAPLIE correction) | `baplie_parse_issue` · `container` (new numbers only) · `container_manifest_entry` | `baplie_parse_run` (`status`, `outcome`, `error_category`, `container_count`, `result_detail`, `worker_version`, `started_at`, `finished_at`) · `baplie_file` (`parse_status`, `staged_payload`) · `vessel` (`call_sign`, `flag_state`, only when NULL) |
| `w_validate` | `queue:validate` (override and yard-adjustment validation) | — | — |
| `w_part3` | Part 3 scheduler (host per DEC-31) | `evaluation_metric` · `drift_measurement` | — |

**Read grants (all accounts):** SELECT on every port table, except: `user` column-level (`id`, `name`, `is_active`); `license_state` column-level (`mode`, `read_only_reason`); no access to `audit_log`, `notification_log`, `user_device`, `recommendation_review` or the SaaS database.

**Done by the Backend, not by workers:**

- `baplie_file.status` (new file → ACTIVE, previous ACTIVE → REPLACED) on C-06 with PARSED.
- The previous `part1_run` → REPLACED when a new run is enqueued (same as `yard_run` in CT-11).
- The previous `entry_window_proposal` → REPLACED on C-06 when a new proposal was produced (same as recommendations in CT-10).
- Every `audit_log` entry, on C-06.
- Marking stale RUNNING runs as FAILED (sweeper, P3-I2).

**Not in the grants now:** `terminal_recommendation` TIMING / STORAGE_ALLOCATION and `storage_allocation_item` (out of MVP, DEC-55); Part 3 retraining tables (`ml_model_version`, `retraining_run`) until retraining is built. Both are added through CT-17.

**Worker health:** each worker writes a Redis key `worker:{name}:heartbeat` (timestamp, version, queue depth; TTL 60 s). No DB grant is needed.

**Rules confirmed by the AI Team (acknowledgement, 2026-10-27):**

- **Final run update is guarded:** `UPDATE <run> SET status = 'DONE' … WHERE id = :run_id AND status = 'RUNNING'`. 0 rows → the Backend replaced the run; the worker rolls back the whole transaction (including inserted proposals or recommendations) and sends no C-06. Applies to `part1_run`, `solver_run`, `yard_run` and `baplie_parse_run`.
- **Failed weather pull:** `w_part1` inserts a new `forecast_run` row with `status = FAILED`, `data_stale = 1` and no points; it never updates an older row. "Is the stored data stale?" is computed at read time from the age of the latest SUCCESS run against `part1_stored_data_max_age_hours` (48).
- **Backend reads the current proposal** through `part1_run.entry_window_proposal_id` of the latest DONE run, never by `status = 'PROPOSED'` (there is a short window with two PROPOSED rows before C-06 is handled). Applies to `GET …/part1-result` and the window re-check on confirm.
- **C-06 for `PART1_RUN`** carries `entry_window_proposal_id` (required when a proposal was inserted, otherwise `null`). Input to DEC-19.
