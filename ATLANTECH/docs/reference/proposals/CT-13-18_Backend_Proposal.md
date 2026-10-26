# CT-13 to CT-18 — Backend proposal for AI review

The Backend Team proposes the remaining AI contracts below, built on everything already agreed (CT-09 to CT-12, DEC-04, DEC-06, DEC-08, DEC-10, DEC-12, DEC-17, DEC-23).

- 🟦 = the Backend owns the decision (DEC-01, DEC-02, CT-17). Please **review** and flag anything that blocks you.
- 🟨 = the AI Team owns the decision (DEC-03, DEC-18, DEC-19, CT-18). This is our **proposal**; approve it or send your version.

Please reply per section: **Approved** or your change.

---

# CT-13 · C-05 · Validation (now a queue job) 🟨

DEC-10 cancelled FastAPI, so validation of override and yard adjustment becomes an async job on **`queue:validate`**, run by **`w_validate`** (SELECT only, DEC-08). BAPLIE correction stays on `queue:edi` (CT-12, `source = STAGED_PAYLOAD`). The window re-check on confirm stays in Laravel (DEC-03).

## 13.1 Message

```json
{
  "contract_version": "1.0",
  "job_type": "VALIDATE",
  "job_id": "e17…",
  "run_id": 4410,
  "enqueued_at": "2026-11-02T11:20:00Z",
  "triggered_by_user_id": 41,
  "payload": {
    "validation_type": "OVERRIDE",
    "subject_id": 880,
    "proposed_change": { "component_type": "BERTH", "recommendation_option_id": 1771, "new_value": 14 }
  }
}
```

| `validation_type` | `subject_id` | `proposed_change` |
| --- | --- | --- |
| `OVERRIDE` | `recommendation.id` | `component_type` (`BERTH` · `PILOT` · `TUG` · `START_TIME`, same enum as `recommendation_override`), `recommendation_option_id`, `new_value` (berth id · pilot user id · `{ "add": id }` / `{ "remove": id }` for tugs · ISO start time) |
| `YARD_ADJUSTMENT` | `terminal_recommendation.id` | `manifest_entry_id`, `new_yard_block_id` |

`run_id` is `validation_request.id` (§13.3). Payload-only schema referenced from the envelope.

## 13.2 Result (inside C-06, because `w_validate` cannot write)

```json
{
  "valid": false,
  "violations": [
    { "kind": "STRUCTURAL", "constraint_type_code": "MAX_DRAFT", "message_key": "validation.berth.draft_exceeded", "detail": "Draft 14.2 m > berth depth 13.5 m" }
  ]
}
```

- `kind` and `constraint_type_code` use the same lists as CT-10 (`SAFETY` codes from the CT-18 seed, closed `STRUCTURAL` list).
- `message_key` lets the Frontend translate the message; `detail` is English diagnostic text, max 255 characters.
- The rules are the same Python modules used by Part 2 and yard (DEC-03: one implementation).

## 13.3 Backend side 🟦 (for your information)

- New table `validation_request` (CT-17) holds each request: type, subject, `proposed_change`, `requested_by`, `status` (`PENDING` · `VALID` · `VIOLATIONS` · `UNAVAILABLE` · `APPLIED` · `CANCELLED`), `result` JSON, timestamps.
- **Flow:** the officer submits a change → the Backend inserts `validation_request` (PENDING) and RPUSH → C-06 arrives → the Backend stores the result and pushes it to the officer over SSE.
  - `valid = true` → the Backend applies the change.
  - Violations → the officer sees them and either cancels or confirms **with a reason** (warning, not a block). The override is written to `recommendation_override` and audited.
- **No answer within `validation_timeout_seconds` (10):** status `UNAVAILABLE`. The officer may still proceed only with a reason, and the audit marks it as unverified.
- **Duplicates:** the Backend handles only the first C-06 for a request that is still `PENDING`.

---

# CT-14 · C-06 · Completion message

## 14.1 Mechanism (DEC-01) 🟦

- After committing, the worker **RPUSHes** a JSON message to **`queue:ai-results`** (no Redis prefix, DEC-04).
- The Backend runs a supervised consumer (`php artisan ai:consume-results`, BLPOP). Target: SSE and notifications within 5 s of the worker's commit.
- **Safety-net poller** every 30 s: finds runs that are DONE or FAILED with `result_handled_at IS NULL` and handles them as if C-06 had arrived. This covers a lost message.
- **Idempotent handling:** the handler claims each run with `UPDATE … SET result_handled_at = NOW() WHERE id = ? AND result_handled_at IS NULL`. 0 rows → already handled, ignore. (`result_handled_at` is a Backend-only column added to every run table through CT-17.)
- **Handler failure:** after 3 failed attempts the message moves to `queue:ai-results:failed` and an alert is raised.
- **Stale runs:** the Backend sweeper marks a run RUNNING longer than `run_stale_after_seconds` as FAILED / `TIMEOUT`, then handles it like a FAILED C-06.

## 14.2 Message shape (DEC-19) 🟨

```json
{
  "contract_version": "1.0",
  "job_type": "PART1_RUN",
  "job_id": "3f6b2c1e-…",
  "run_id": 812,
  "status": "DONE",
  "outcome": "WINDOWS_FOUND",
  "error_category": null,
  "finished_at": "2026-11-02T09:15:40Z",
  "worker_version": "0.6.0",
  "refs": { "entry_window_proposal_id": 4120 }
}
```

| Field | Rule |
| --- | --- |
| `job_id` | Copied from the envelope, for log correlation |
| `status` | `DONE` · `FAILED` |
| `outcome` | Required when `DONE`; per job type below |
| `error_category` | Required when `FAILED`; CT-08 list |
| `refs` | Per job type below; tells the Backend which new rows to act on |

**Per job type:**

| `job_type` | `outcome` (DONE) | FAILED cases | `refs` |
| --- | --- | --- | --- |
| `PART1_RUN` | `WINDOWS_FOUND` · `NO_FEASIBLE_WINDOW` · `ENV_DATA_UNAVAILABLE` | technical errors | `entry_window_proposal_id` (null when no proposal was inserted) |
| `PART2_SOLVE` | `SOLVED` · `PARTIALLY_SOLVED` · `NO_FEASIBLE_PLAN` | `FIFO_FALLBACK` · `GENERATION_FAILED` (sent as `outcome` with `status = FAILED`, matching `solver_run.status = ERROR`) | none: the Backend reads `solver_run.result_detail.requests[]` |
| `YARD_PLACEMENT` | `YARD_REC_GENERATED` | `GENERATION_FAILED` | `terminal_recommendation_id` |
| `BAPLIE_PARSE` | `PARSED` · `QUARANTINED` · `REJECTED` | technical errors | `baplie_file_id` |
| `VALIDATE` | `VALID` · `VIOLATIONS` | technical errors | `validation_request_id`, plus `result` = the §13.2 object |
| `PART3_EVALUATION` | `NO_DRIFT` · `DRIFT_DETECTED` | technical errors | `drift_measurement_id` |

## 14.3 What the Backend does on receipt 🟦 (for your information)

| `job_type` | Backend actions |
| --- | --- |
| `PART1_RUN` | Marks the previous `entry_window_proposal` REPLACED · request transition · audit · SSE `ENTRY_WINDOW_READY` or `RUN_FAILED` · notification |
| `PART2_SOLVE` | Marks the previous PENDING_REVIEW recommendation of each solved request REPLACED · request transitions · audit · SSE `RECOMMENDATION_READY` · sends the held triggers as the next solve (CT-10) |
| `YARD_PLACEMENT` | Audit · SSE `YARD_RECOMMENDATION_READY` / `YARD_RECOMMENDATION_FAILED` |
| `BAPLIE_PARSE` | On PARSED: new file ACTIVE, previous REPLACED · stale yard regeneration (CT-11) · audit · SSE `BAPLIE_PARSED` |
| `VALIDATE` | Stores the result on `validation_request` · SSE to the officer |
| `PART3_EVALUATION` | On DRIFT_DETECTED: SSE `DRIFT_ALERT` · notification |

---

# CT-15 · AI result tables and write ownership (DEC-02) 🟦

**Rule (agreed in DEC-08):** a worker inserts new rows and completes **its own** run; the Backend creates run rows, closes old rows, writes every request transition, history row and audit entry.

| Table | Created by | Completed by | Closed / changed later by |
| --- | --- | --- | --- |
| `part1_run` | Backend (QUEUED) | AI (own run) | Backend (REPLACED on a new run, sweeper FAILED) |
| `entry_window_proposal` · `feasible_window` · `blocked_period` | AI | — | Backend (CONFIRMED · REJECTED · REPLACED) |
| `forecast_run` · `environment_forecast_point` | AI (SCHEDULED, ON_DEMAND; FAILED pulls as new rows) · Backend (MANUAL) | — | — |
| `tide_prediction_run` · `tide_prediction_point` · `demand_forecast_slot` | AI | — | — |
| `solver_run` | Backend (QUEUED, `solver_version` from `port_setting`) | AI (own run; overwrites `solver_version` if different) | Backend (sweeper FAILED) |
| `solver_run_model_version` | AI | — | — |
| `recommendation` · `recommendation_option` · `recommendation_option_tug` · `recommendation_rationale_factor` | AI | — | Backend (CONFIRMED · MODIFIED_BY_STAFF · REPLACED) |
| `recommendation_override` | Backend | — | — |
| `terminal_recommendation` (YARD_PLACEMENT) | Backend (row, `YARD_REC_REQUESTED`) | AI (`lifecycle_status` → GENERATED / FAILED, `confidence`, `rationale_template_id`, `failure_reason`) | Backend (ACCEPTED · REJECTED · EXPORTED, decision fields, regeneration reset) |
| `yard_run` | Backend (QUEUED) | AI (own run) | Backend (REPLACED, sweeper FAILED) |
| `yard_placement_item` | AI | — | Backend (deletes on regeneration) |
| `baplie_file` | Backend (STAGED, PENDING) | AI (`parse_status`, `staged_payload`) | Backend (`status` ACTIVE · REPLACED · WITHDRAWN, correction fields) |
| `baplie_parse_run` | Backend (QUEUED) | AI (own run) | Backend (sweeper FAILED) |
| `baplie_parse_issue` · `container` · `container_manifest_entry` | AI | — | Backend (staff line fixes, FR-1.2.5) |
| `vessel` | Backend | AI (only NULL `call_sign`, `flag_state`) | Backend |
| `validation_request` | Backend | — (result arrives in C-06) | Backend |
| `evaluation_metric` · `drift_measurement` | AI | — | — |
| `berthing_request` (+ history) · `audit_log` · `notification_log` | Backend only | — | — |
| `result_handled_at` (every run table) | — | — | Backend only |

The yard worker writing `lifecycle_status` is accepted as "completing its own result", not a business transition: the CT Operation Planner's decisions (ACCEPTED / REJECTED / EXPORTED) stay Backend-only.

---

# CT-16 · Worker heartbeat (replaces the FastAPI internal API) 🟨

DEC-10 cancelled FastAPI, so CT-16 is redefined as the heartbeat contract.

```json
// Redis key: worker:part2:heartbeat   (TTL 60 s, refreshed every 15 s)
{ "name": "part2", "queue": "queue:part2", "at": "2026-11-02T10:05:00Z", "version": "0.6.0", "queue_depth": 0, "busy_run_id": 2302 }
```

- Names: `part1` · `part2` · `yard` · `edi` · `validate` · `part3`.
- **Backend 🟦:** a worker is `UP` if its key exists and `at` is under 60 s old, otherwise `DOWN`. The Backend exposes this on an admin health endpoint and raises an alert when a worker is DOWN for more than 2 minutes.
- Run diagnosis that used `GET /internal/v1/runs/…` is replaced by reading the run tables directly.

---

# CT-17 · Database schema (migrations) 🟦

- **Single owner:** the Backend. Migrations live in `project-backend` only.
- **`contracts/db/schema.sql`** is regenerated in the same PR as every migration, with the last migration name in its header. The AI Team builds its test schema from it.
- **Types follow `PortSync`:** signed `bigint` ids (the `UNSIGNED` in the old CT-17 example is dropped).
- **Change process:** any migration touching a table a worker reads or writes carries the label `ai-impact` and needs an AI Team reviewer.
- **Grants script:** `database/grants/workers.sql` (DEC-08), updated in the same PR when a worker's tables change.

**Pending additions from the agreed contracts:**

| Change | From |
| --- | --- |
| New `yard_run` | CT-11 |
| New `baplie_parse_run` | CT-12 |
| New `validation_request` | CT-13 |
| `result_handled_at datetime NULL` on `part1_run`, `solver_run`, `yard_run`, `baplie_parse_run` | CT-14 |
| `objective_term` weights → `port_setting` keys (see CT-18) | CT-18 |
| `part1_run.entry_window_proposal_id` note → "set whenever a proposal is inserted" | if DEC-07 closes with Option 1 |

---

# CT-18 · AI seed data and `port_setting` keys 🟨

- **Format:** `contracts/ai/seed-spec.json`, owned by the AI Team. The Backend writes idempotent seeders from it (upsert by `code` / `setting_key`).
- **Who edits settings (DEC-12) 🟦:** only `PORT_GM` (there is no system-admin role in `user_role_assignment`). Every change is audited and fills `updated_by`.
- **Validation of admin edits:** each `port_setting` entry in the seed spec carries `value_type`, `min`, `max` and `description`, so the admin screen can reject bad values.

**Objective weights (DEC-23).** `objective_term` has no weight column (`code`, `name` only). Proposal: keep weights as admin-editable settings, consistent with DEC-12:

| Key | Type | Value |
| --- | --- | --- |
| `objective_weight_waiting_time` | DECIMAL | 1.0 |
| `objective_weight_resource` | DECIMAL | 1.0 |

**`port_setting` keys (DEC-12 list + keys added by the contracts):**

| Key | Type | Proposed value | Source |
| --- | --- | --- | --- |
| `weather_pull_hours` | INT | AI to fill | DEC-12 |
| `tide_horizon_days` | INT | AI to fill | DEC-12 |
| `part1_stored_data_max_age_hours` | INT | 48 | DEC-12 |
| `part1_assessment_horizon_hours` | INT | AI to fill (DEC-24) | DEC-12 |
| `part1_time_step_minutes` | INT | AI to fill (DEC-24) | DEC-12 |
| `part1_min_window_minutes` | INT | AI to fill (DEC-24) | DEC-12 |
| `demand_slot_minutes` | INT | AI to fill (DEC-24) | DEC-12 |
| `solver_time_limit_seconds` | INT | 10 | DEC-12 |
| `solver_options_per_request` | INT | 3 (maximum) | DEC-23 |
| `solver_option_equivalence_pct` | DECIMAL | 10 | DEC-23 |
| `part1_max_retries` | INT | 3 | DEC-04 |
| `run_stale_after_seconds` | INT | 300 | DEC-04 (replaces `part1_run_timeout_seconds` from DEC-14) |
| `validation_timeout_seconds` | INT | 10 | CT-13 |
| `part3_evaluation_cron` | STRING | AI to fill (DEC-31) | DEC-12 |
| `drift_psi_threshold` | DECIMAL | AI to fill (DEC-30) | DEC-12 |
| `drift_mae_threshold` | DECIMAL | AI to fill (DEC-30) | DEC-12 |

**Questions for the AI Team:**

1. DEC-23 says "resources cost"; our seed says `RESOURCE_UTILISATION`. Which `objective_term.code` is final?
2. With equal weights, are both terms normalised before they are added (minutes of waiting vs. cost or utilisation)?
3. Does each worker read `port_setting` per job (an admin change applies to the next job), or cache it? We propose per job.

---

## Impact summary

| Contract | Change |
| --- | --- |
| CT-08 | Queues: `queue:part1` · `queue:part2` · `queue:yard` · `queue:edi` · `queue:validate`; result list `queue:ai-results`; `job_type` adds `VALIDATE` |
| CT-19 | Mocks for `queue:yard` and `queue:validate` |
| CT-20 | Read endpoints show 1 to 3 options per request |
| CT-22 | Admin settings screen (PORT_GM); validation result and override-with-reason dialog |
| CT-24 | No BAPLIE performance tests until the Leads Council rules on NFR-PERF-2 |
