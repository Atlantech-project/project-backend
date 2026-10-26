# ATLANTECH — AI/Backend Dependencies

| Field | Value |
|---|---|
| Document | ATLANTECH AI/Backend Dependencies |
| Version | 1.0 — draft for AI and Backend review |
| Date | 22 October 2026 |
| Purpose | Primary input for the Backend implementation plan: what the Backend must provide, implement, consume, store, expose, authorize, validate or coordinate so the AI subsystem works |
| Related | `ATLANTECH_AI_IMPLEMENTATION_PLAN.md` (components, workflows WF-1–WF-8, open decisions OD-xx), `ATLANTECH_AI_TASKS.md` (tasks AI-xxx) |
| Not in scope | The Backend implementation plan itself, Frontend, Flutter and full-system plans |

**Tags.** [DOCUMENTED] established in a project document; [DERIVED] required to implement a documented requirement; **PROPOSED — REQUIRES APPROVAL** for every contract, name, field, queue payload or schema item that no project document defines; [OPEN] unresolved, tracked as `OD-xx` (same numbering as the implementation plan).

**Central question:** *What exactly must the Backend provide, implement, consume, store, expose, authorize, validate or coordinate to support the AI subsystem?* §2 lists every dependency; §3 gives the contracts; §4 the database ownership; §5 the per-workflow Backend responsibilities; §7 the acceptance checklist.

---

## 1. Responsibilities and Boundaries

### 1.1 Ownership

| Area | AI-owned | Backend-owned (Laravel modular monolith) | Shared |
|---|---|---|---|
| Environmental data | Pulling providers on schedule and on demand (Part 1 scheduler, D16); tide computation; surge; feasibility | Manual environmental data entry form and its validation, storage as `forecast_run` with `pull_type = MANUAL` (UML 2.11); conditions view (surge "derived on the fly" by the Port API, 2.12) | Surge formula must be identical in both (A3) |
| Entry window | Computing windows, blocked periods, peak congestion; writing `part1_run` progress and outcome | Customs Approval, creating `part1_run` QUEUED and enqueuing (2.10); window confirm / reject all / recompute / reopen; server re-check at confirmation; request status | Re-check logic ("still passes") — OD-03 |
| Recommendation | Hard filter, service time, delay risk, tug count, CP-SAT, FIFO fallback, ranking, rationale, writing recommendation rows | Enqueuing solves, review tracking (`recommendation_review`), override workflow and storage, plan confirmation and booking in one transaction, regeneration rules (409 after confirmation) | Override validation — OD-03 |
| Yard | Generating placements, re-validating adjustments | Creating the yard request on plan confirmation, accept / adjust / reject, TOS-ready payload, `EXPORTED_TO_TOS` | — |
| BAPLIE | Parsing, validation rule set, all-or-nothing commit (EDI Parser worker) | Upload, request matching (IMO + ETA ± 24 h), duplicate check, file storage, correction and withdraw flows, blocking Customs Approval on incomplete manifest | Validation rule set used for corrections — OD-03 |
| Execution feedback | Reading trusted outcomes (Part 3) | Actual status ingestion (idempotent, `clock_skew_suspect`), deviation detection and significance, delay re-plan trigger | — |
| Learning | Evaluation, drift, (data-gated) retraining and bias correction | Notifications (`DRIFT_ALERT`, `RETRAINING_REVIEW_NEEDED`), review UI fields on `retraining_run` | — |
| Cross-cutting | Logging inside workers | Authentication, RBAC, terminal scope, licence mode, write chain, audit of user actions, SSE/WebSocket, schema migrations | Audit of AI-written changes — OD-02 |

**Rule [DOCUMENTED, Master_Context §2, §6.1].** The AI never executes operational decisions. Confirmation, booking, dispatch, cancellation and TOS entry are Backend or human actions.

### 1.2 Data supplied to the AI and outputs returned

| Supplied by the Backend (through the database and job messages) | Returned by the AI (through the database) |
|---|---|
| Berthing requests with per-call particulars (ETA, drafts, Cb, GT, DWT), vessel identity, requested terminal | `part1_run` outcome; `entry_window_proposal`, `feasible_window`, `blocked_period`; `demand_forecast_slot` |
| Master data: port profile, channel, berths and `berth_terminal`, tugs, pilots, rank limits, shifts and schedules, tug masters, terminals, productivity profiles, safety constraints, yard structure, equipment | `solver_run`, `recommendation`, `recommendation_option`, `recommendation_option_tug`, `recommendation_rationale_factor`, `solver_run_model_version` |
| BAPLIE files on the volume and `baplie_file` rows | `vessel` (fill-empty), `container`, `container_manifest_entry`, `baplie_parse_issue`, `baplie_file` status fields |
| Confirmed window and plan state (`plan_component` reservations) | `terminal_recommendation` (yard) and `yard_placement_item` |
| Manual environmental data | `forecast_run`, `environment_forecast_point` (scheduled and on-demand), tide runs |
| Execution outcomes, deviations, overrides | `evaluation_metric`, `drift_measurement`; data-gated: `ml_model_version`, `retraining_run` |
| Seeds: constraint types, objective terms, rationale templates, model keys | Validation verdicts (OD-03) and completion signals (OD-01) |

---

## 2. Dependency Inventory

### 2.1 Summary

| ID | Provider → Consumer | Purpose | Required | Spec status | AI tasks |
|---|---|---|---|---|---|
| AIBD-001 | Backend → Part 1 | Enqueue Part 1 run on Customs Approval, recompute, manual data, reopen | Required | Interaction DOCUMENTED; payload PROPOSED | AI-003, AI-048 |
| AIBD-002 | Backend → Part 2 | Enqueue initial solve (`NEW_REQUEST`) after window confirmation or plan cancellation with call continuing | Required | DOCUMENTED; payload PROPOSED | AI-003, AI-055, AI-058 |
| AIBD-003 | Backend → Part 2 | Enqueue regeneration (`MANUAL`) before confirmation | Required | DOCUMENTED; payload PROPOSED | AI-059 |
| AIBD-004 | Backend → Part 2 | Enqueue regeneration on resource-state change (`RESOURCE_CHANGE`), including resources released by cancellation | Required | DOCUMENTED; payload PROPOSED | AI-059 |
| AIBD-005 | Backend → Part 2 | Enqueue delay re-plan (`REPLAN`) on significant deviation | Required | DOCUMENTED; payload PROPOSED | AI-059 |
| AIBD-006 | Backend → Part 2 | Enqueue yard placement on plan confirmation (container vessel with ACTIVE BAPLIE) or planner request | Required | DOCUMENTED; payload PROPOSED | AI-066 |
| AIBD-007 | Backend → EDI Parser | Enqueue BAPLIE parse job | Required | DOCUMENTED; payload PROPOSED | AI-030–AI-032 |
| AIBD-008 | Backend ↔ Part 2 / EDI | Validation of overrides, yard adjustments, BAPLIE corrections | Required | OPEN (OD-03) | AI-004, AI-031, AI-060, AI-067 |
| AIBD-009 | Backend → Part 1 | Manual environmental data stored as a forecast run, then recompute | Required | DOCUMENTED | AI-041, AI-048 |
| AIBD-010 | AI → Backend | Completion signal so the Backend can push SSE and notifications | Required | OPEN (OD-01) | AI-002, AI-011 |
| AIBD-011 | Backend | Request status transitions, history rows and run-row creation around AI results | Required | OPEN (OD-02) | AI-002, AI-058 |
| AIBD-012 | Backend → Part 1 | `part1_run` row created QUEUED before enqueue | Required | DOCUMENTED (2.10) | AI-048 |
| AIBD-013 | Backend → Part 2 | `solver_run` row created QUEUED before enqueue | Required | PROPOSED — REQUIRES APPROVAL (OD-02) | AI-058 |
| AIBD-014 | Backend → Part 2 | `terminal_recommendation` row with `YARD_REC_REQUESTED` before enqueue | Required | DOCUMENTED (2.16) | AI-066 |
| AIBD-015 | Backend → EDI | File on BAPLIE volume and `baplie_file` row (STAGED, PENDING) | Required | DOCUMENTED (2.7) | AI-032 |
| AIBD-016 | Backend → AI | Master data maintained through Backend screens and validations | Required | DOCUMENTED | AI-050, AI-044, AI-045 |
| AIBD-017 | Backend → Part 2 | Reservations (`plan_component` RESERVED/RELEASED/CONSUMED) as occupancy truth; booking re-check at confirmation | Required | DOCUMENTED | AI-050 |
| AIBD-018 | Backend → Part 3 | Actual status with device/server times and `clock_skew_suspect`; deviation records | Required | DOCUMENTED | AI-071, AI-074 |
| AIBD-019 | Backend → Part 3 | Override records with reasons | Required | DOCUMENTED | AI-071 |
| AIBD-020 | Backend | Deviation thresholds per operation type and significance rule triggering AIBD-005 | Required | DOCUMENTED rule; storage OPEN (OD-16) | AI-008, AI-059 |
| AIBD-021 | Backend → AI | `port_setting` keys for AI configuration | Required | PROPOSED — REQUIRES APPROVAL | AI-012 |
| AIBD-022 | Backend | Notifications for AI outcomes | Required | DOCUMENTED types | AI-081 |
| AIBD-023 | Backend | Licence-mode gating before enqueue; rules for scheduled AI jobs in `READ_ONLY` | Required | DOCUMENTED for user actions; OPEN for scheduled jobs (OD-18) | AI-002, AI-082 |
| AIBD-024 | Backend | Terminal-scoped exposure of AI outputs (403) | Required | DOCUMENTED | AI-083 |
| AIBD-025 | Backend | Audit entries for state changes caused by AI results | Required | DOCUMENTED rule; mechanism OPEN (OD-02) | AI-058 |
| AIBD-026 | Backend → users | Read endpoints exposing AI results (windows, options, rationale, yard, metrics, conditions) | Required | DOCUMENTED flows; endpoints not documented | AI-081 |
| AIBD-027 | Backend | `retraining_run` review fields and model review screen | Optional (data-gated) | DOCUMENTED | AI-104 |
| AIBD-028 | Backend / Port IT | Database accounts and privileges for each worker | Required | PROPOSED — REQUIRES APPROVAL (OD-21) | AI-013 |
| AIBD-029 | Platform | Redis available with the three documented queues | Required | DOCUMENTED | AI-011 |
| AIBD-030 | Platform | BAPLIE volume readable by the EDI Parser worker | Required | DOCUMENTED | AI-032 |
| AIBD-031 | Port IT | Outbound HTTPS from the port server to weather providers | Required | DOCUMENTED (outbound only) | AI-020 |
| AIBD-032 | Platform | Process supervision and health visibility for workers | Required | PROPOSED — REQUIRES APPROVAL | AI-090, AI-091 |
| AIBD-033 | Backend ↔ Part 1 | Same surge formula in the conditions view | Required | DOCUMENTED | AI-043 |
| AIBD-034 | Backend ↔ Part 1 | Window confirmation re-check ("still passes") | Required | DOCUMENTED rule; mechanism OPEN (OD-03) | AI-004, AI-048 |
| AIBD-035 | Backend | Block Customs Approval while any manifest line is incomplete | Required | DOCUMENTED | AI-048 |
| AIBD-036 | Backend | Seeders for AI reference data | Required | Content PROPOSED in AI-009 | AI-009 |
| AIBD-037 | Backend | UTC storage of datetimes and port timezone in `port_profile` | Required | DOCUMENTED | AI-014 |
| AIBD-038 | Backend | Schema migrations for all tables, including AI-written ones | Required | DERIVED | AI-013, AI-080 |
| AIBD-039 | Backend | Stale-run sweep visibility and staff-facing failure states | Required | PROPOSED — REQUIRES APPROVAL | AI-011, AI-082 |
| AIBD-040 | Backend | Tide constituent set activation with datum check | Required | DOCUMENTED (backend check on activation) | AI-021, AI-042 |

### 2.2 Dependency details

Each entry: provider · consumer · direction · purpose · required · contract · prerequisites · owner · status · failure behaviour · consequence if unavailable.

**AIBD-001 — Part 1 run job.** Backend → AI Part 1, async (`queue:part1`). Purpose: start a Part 1 run. Required. Contract C-01 (§3.2). Prerequisites: request `CUSTOMS_APPROVED`, mode `FULL`, AIBD-012, AIBD-035. Owner: Backend (enqueue), AI (consume). Status: interaction DOCUMENTED (2.10, 2.11), payload PROPOSED. Failure: Redis unavailable → Backend must not commit the status change without the job, or must have a re-enqueue mechanism [PROPOSED: transactional outbox or re-enqueue of QUEUED runs older than a threshold]. If unavailable: no entry windows; the call cannot progress past Gate 1.

**AIBD-002 — Initial solve job.** Backend → AI Part 2, async (`queue:part2`). Purpose: generate the first recommendation. Required. Contract C-02. Prerequisites: `ENTRY_WINDOW_CONFIRMED`, mode `FULL`, AIBD-013. Owner: Backend/AI. Status: DOCUMENTED interaction, PROPOSED payload. Failure: as AIBD-001. If unavailable: no recommendation; Gate 2 impossible.

**AIBD-003 — Regenerate (MANUAL).** Backend → Part 2. Purpose: staff ask for a new recommendation before confirmation. Required. Contract C-02 with `trigger_type = MANUAL`. Prerequisites: no confirmed plan (409 otherwise), mode `FULL`. Failure: previous version stays current (2.13). If unavailable: staff can only override or confirm the current version.

**AIBD-004 — Resource change.** Backend → Part 2. Purpose: regenerate pending recommendations that use a changed berth, tug or pilot (UML rule 11), including resources released by a cancellation (AD-12 note). Required. Contract C-02 with `trigger_type = RESOURCE_CHANGE`. Prerequisites: Backend detects the change and finds affected pending recommendations (AD-12 A1–A2 are Port Server steps). Failure: previous version stays current. If unavailable: staff may confirm stale recommendations; the booking re-check (AIBD-017) still protects against double booking.

**AIBD-005 — Delay re-plan.** Backend → Part 2. Purpose: proposal version `DELAY_REPLAN` after a significant deviation. Required. Contract C-02 with `trigger_type = REPLAN`. Prerequisites: AIBD-020 significance rule; plan `CONFIRMED`; mode not `READ_ONLY` (else staff are notified, no proposal). Failure: no proposal; current plan stays. If unavailable: delays are handled manually by cancellation and new recommendation.

**AIBD-006 — Yard job.** Backend → Part 2. Purpose: yard placement. Required for container terminals. Contract C-03. Prerequisites: plan confirmed, container vessel, ACTIVE BAPLIE, AIBD-014, mode `FULL` (else reason recorded and planner notified, AD-14). Failure: `GENERATION_FAILED` with reason. If unavailable: planner plans the yard manually; plan yard region stays `YARD_PENDING`.

**AIBD-007 — BAPLIE parse job.** Backend → EDI Parser (`queue:edi`). Contract C-04. Prerequisites: AIBD-015. Failure: parse FAILED / QUARANTINED. If unavailable: no manifest; Customs Approval blocked for container calls.

**AIBD-008 — Validation requests.** Backend ↔ AI. Purpose: validate staff override (2.13), yard adjustment (AD-14 A17), BAPLIE correction (2.8). Required. Contract C-05 (if OD-03 option (a)). Status OPEN. Failure: validation unavailable → Backend must refuse the change (fail closed) [DERIVED: hard constraints are never bypassed]. If unavailable: overrides and adjustments cannot be accepted.

**AIBD-009 — Manual environmental data.** Backend → Part 1 through the database. Purpose: allow assessment when providers fail. Required. Backend validates completeness and horizon coverage (422 otherwise), stores `forecast_run` (`pull_type = MANUAL`, `provider_rank = MANUAL`, `submitted_by`, unit columns) and points, audits, then enqueues C-01 with `reason = MANUAL_DATA`. Data passes the same safety constraints — no bypass (AD-10). If unavailable: requests wait until provider data returns.

**AIBD-010 — Completion signal.** AI → Backend. Purpose: Backend learns a job finished, pushes SSE (≤ 5 s after commit, NFR-PERF-4) and creates notifications. Required. Contract C-06. Status OPEN (OD-01). Failure: missed signal → safety-net poller [PROPOSED]. If unavailable: users see results only after refresh.

**AIBD-011 — Status transitions and run rows.** Backend. Purpose: keep every `berthing_request` transition with its history row in one transaction (PortSync `berthing_request` note) and keep SM-4 authoritative. Required. Status OPEN (OD-02). Recommended: Backend performs `ENTRY_WINDOW_CONFIRMED`, `RECOMMENDATION_GENERATED`, `PLAN_*` transitions on receipt of AIBD-010; AI never writes `berthing_request.status`. Consequence if undecided: two writers of the same state.

**AIBD-012 / AIBD-013 / AIBD-014 / AIBD-015 — Run rows created by the Backend.** Purpose: the run row is the correlation id and lets the UI show QUEUED immediately. AIBD-012 and AIBD-014 and AIBD-015 are DOCUMENTED; AIBD-013 (`solver_run` QUEUED by the Backend, with `trigger_type`, `triggered_by` when MANUAL per `chk_sr_manual`, horizon and `solver_version` placeholder) is PROPOSED — REQUIRES APPROVAL because `solver_version` is NOT NULL and is known only by the worker [PROPOSED: Backend writes the configured solver version string from `port_setting`, worker overwrites if different].

**AIBD-016 — Master data.** Backend → AI. Purpose: every solver and Part 1 input. Required. Includes validations already documented: berth `max_vessel_loa ≤ length`, every berth has ≥ 1 `berth_terminal` row, non-overlapping safety constraints of the same type and scope, non-overlapping productivity periods, non-overlapping active shifts, one master per tug, datum equality on tide-set activation (AIBD-040). Failure: invalid master data → AI outcome with configuration reason. If unavailable: nothing can be computed.

**AIBD-017 — Reservations and booking.** Backend → Part 2. Purpose: occupancy = RESERVED `plan_component` rows overlapping a window (D-20 note); plan confirmation re-checks and books in one transaction (2.14). Required. DOCUMENTED. Consequence: wrong occupancy → infeasible or conflicting recommendations.

**AIBD-018 / AIBD-019 — Outcome data.** Backend → Part 3. Actual status with `reported_at`, `client_sent_at`, `received_at`, `clock_skew_suspect` (gap > 5 min), `sync_source`; deviations (`deviation_log`); overrides (`recommendation_override`). Required for C1/C2. DOCUMENTED. If unavailable: Part 3 reports no metrics.

**AIBD-020 — Deviation thresholds.** Backend. Thresholds per operation type are Port Admin settings (AD-16); storage undefined (ClassDiagram 3 OI-09) [OPEN, OD-16; PROPOSED: `port_setting` keys per component type]. The same thresholds define M4 labels later.

**AIBD-021 — Configuration keys.** Backend seeds and exposes `port_setting` keys [PROPOSED — REQUIRES APPROVAL]: `weather_pull_hours` (example key in PortSync), `tide_horizon_days`, `part1_stored_data_max_age_hours` (48, documented value), `part1_assessment_horizon_hours`, `part1_time_step_minutes`, `part1_min_window_minutes`, `demand_slot_minutes`, `solver_time_limit_seconds` (10, NFR-ENG-1), `solver_options_per_request`, `part1_max_retries` (3, documented), `run_stale_after_seconds`, `part3_evaluation_cron`, `retrain_interval_days` (example key in PortSync), `drift_psi_threshold`, `drift_mae_threshold`. Values other than the documented ones are decided in AI-007/AI-008.

**AIBD-022 — Notifications.** Backend creates `notification_log` rows and SSE events of the documented types: `ENTRY_WINDOW_READY`, `RECOMMENDATION_READY`, `YARD_RECOMMENDATION_READY`, `YARD_RECOMMENDATION_FAILED`, `DRIFT_ALERT`, `RETRAINING_REVIEW_NEEDED`. Required. Recipients per UML (staff, CT Operation Planner, Port Admin).

**AIBD-023 — Licence gating.** Backend refuses enqueue for creation/regeneration in `READ_ONLY` (423) and records the reason for yard requests (AD-14). Scheduled pulls and Part 3 in `READ_ONLY`: OPEN (OD-18).

**AIBD-024 — Terminal isolation.** Backend applies terminal-level row security to AI outputs: `terminal_recommendation` visible to its terminal only; manifest data stripped for other terminals (NFR-SEC-7). The AI computes port-wide and writes all rows; exposure filtering is the Backend's.

**AIBD-025 — Audit.** Every state change is audited (UML rule 12); `audit_log.actor_id` NULL = system/AI worker. Recommended (OD-02): Backend writes the audit entry when it applies the AI result; AI does not write `audit_log` except where the decision assigns it.

**AIBD-026 — Exposure endpoints.** Backend read APIs for: latest Part 1 result with windows, blocked periods, data sources and ages (2.11); recommendation options with rationale, service time, delay risk (2.13); yard placements, rationale, confidence (2.16); conditions with surge and stale warning (2.12); deviations; evaluation and drift for Port Admin. Endpoint names are not documented [PROPOSED in §3.8].

**AIBD-027 — Model review.** Port Admin reviews flagged challengers; Backend updates `reviewed_by`, `reviewed_at`, `review_note` (`chk_rr_review`). Optional until P10.

**AIBD-028 — DB privileges.** Per-worker accounts with SELECT on read tables and INSERT/UPDATE on owned tables (§4); `audit_log` INSERT/SELECT only [DOCUMENTED for the application user]. PROPOSED — REQUIRES APPROVAL.

**AIBD-029 — Redis.** Queues `queue:part1`, `queue:part2`, `queue:edi` [DOCUMENTED]. Persistence of Redis (AOF/RDB) not documented [OPEN].

**AIBD-030 — BAPLIE volume.** Dedicated volume separate from code [DOCUMENTED]; EDI worker needs read access to `storage_path`.

**AIBD-031 — Egress.** Provider calls go out from the port; no inbound endpoint [DOCUMENTED, 2.12]. Port IT must allow the two provider hosts (OD-12).

**AIBD-032 — Supervision and health.** Workers restart automatically; health visible to Port IT/Support [PROPOSED]. Restart ≤ 60 s is an NFR-REL figure [DOCUMENTED in UML §10].

**AIBD-033 — Surge in conditions view.** Port API computes surge on the fly (2.12); it must use the same datum handling as A3 [DERIVED].

**AIBD-034 — Window re-check.** At confirmation the server checks that the window belongs to the latest result and "still passes" (2.11). Recommended: Backend checks "latest result" and that the underlying forecast run is still the newest usable; a full re-assessment is a recompute (C-01) [PROPOSED].

**AIBD-035 — Manifest completeness gate.** Backend blocks `CUSTOMS_APPROVED` and yard recommendations while any manifest line is incomplete (`container_manifest_entry.full_empty` note) [DOCUMENTED].

**AIBD-036 — Seeders.** Backend seeds `constraint_type`, `objective_term`, `rationale_template`, `ml_model`, `status_type` from the specification in AI-009 [content PROPOSED].

**AIBD-037 — Time base.** UTC datetimes (column notes), port timezone in `port_profile.timezone`, shift times in local time [DOCUMENTED].

**AIBD-038 — Migrations.** One owner for schema changes (Backend Laravel migrations) [DERIVED]; AI requests changes through the Backend; contract tests detect drift (AI-080).

**AIBD-039 — Stale runs.** If a worker dies, a run may stay RUNNING. Backend shows it as failed once the sweep marks it and lets staff retry [PROPOSED].

**AIBD-040 — Tide set activation.** Backend checks on activation that `datum_reference` equals channel and berth `depth_datum` (PortSync notes) [DOCUMENTED]; only one active set (`active_key`).

---

## 3. API and Service Contracts

All Laravel ↔ Python interactions are asynchronous over Redis, one queue per worker (D9, D18). No HTTP API between Laravel and Python is documented. Every payload in this section is **PROPOSED — REQUIRES APPROVAL** (task AI-003).

### 3.1 Common job envelope — PROPOSED — REQUIRES APPROVAL

Laravel's default Redis queue format serialises PHP job classes, which a Python consumer should not execute. **Proposal:** the Backend pushes a plain JSON envelope with `RPUSH` onto the documented list name; the worker consumes with a blocking pop.

| Field | Type | Required | Rules |
|---|---|---|---|
| `contract_version` | string | yes | `"1.0"`; worker rejects unknown major versions |
| `job_type` | enum | yes | `PART1_RUN`, `PART2_SOLVE`, `YARD_PLACEMENT`, `BAPLIE_PARSE`, `VALIDATE` |
| `job_id` | string (UUID) | yes | Unique per enqueue; used for de-duplication in logs |
| `run_id` | integer | yes except `VALIDATE` | Id of the run row (correlation id) |
| `enqueued_at` | string (ISO 8601 UTC) | yes | — |
| `triggered_by_user_id` | integer or null | no | `user.id` of the staff member, if any |
| `payload` | object | yes | Per job type below |

Error categories reported in run rows and completion signals: `INVALID_ENVELOPE`, `PRECONDITION_FAILED` (state changed since enqueue), `DATA_UNAVAILABLE`, `CONFIGURATION_ERROR` (e.g. datum mismatch, missing profile), `INFEASIBLE`, `SOLVER_ERROR`, `TIMEOUT`, `INTERNAL_ERROR`.

Idempotency: the worker claims the run with a conditional update (`QUEUED` → `RUNNING`); a second delivery of the same `run_id` is ignored. Retries: Part 1 up to 3 times (DOCUMENTED); others per AI-003. Authentication: none at message level — Redis is reachable only inside the port server [PROPOSED; relies on existing network isolation].

### 3.2 C-01 Part 1 run — PROPOSED — REQUIRES APPROVAL

- **Operation:** compute entry windows for one request. **Initiator:** Backend. **Mechanism:** `queue:part1`. **Existing identifier:** none documented.
- **Payload:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `berthing_request_id` | integer | yes | Must be `CUSTOMS_APPROVED` when the worker starts, else discarded (2.10) |
| `reason` | enum | yes | `CUSTOMS_APPROVAL`, `RECOMPUTE`, `MANUAL_DATA`, `REOPEN` |
| `manual_forecast_run_id` | integer | when `MANUAL_DATA` | The `forecast_run` with `pull_type = MANUAL` |

- **Results (database):** `part1_run` (`status`, `outcome`, `retry_count`, `started_at`, `finished_at`, `entry_window_proposal_id`), proposal rows; earlier run REPLACED.
- **Timeout:** none documented [OPEN]; retries 3 then FAILED [DOCUMENTED].
- **Example:**

```json
{
  "contract_version": "1.0",
  "job_type": "PART1_RUN",
  "job_id": "3f6b2c1e-8a1d-4a51-9f1e-2b0c7d9e4a10",
  "run_id": 812,
  "enqueued_at": "2026-10-22T09:15:04Z",
  "triggered_by_user_id": 41,
  "payload": { "berthing_request_id": 1550, "reason": "CUSTOMS_APPROVAL" }
}
```

### 3.3 C-02 Part 2 solve — PROPOSED — REQUIRES APPROVAL

- **Operation:** solve, regenerate, resource-change re-solve or delay re-plan. **Initiator:** Backend. **Mechanism:** `queue:part2`.
- **Payload:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `trigger_type` | enum | yes | `NEW_REQUEST`, `MANUAL`, `RESOURCE_CHANGE`, `REPLAN` (= `solver_run.trigger_type`) |
| `berthing_request_ids` | integer[] | yes | Requests the trigger concerns; final scope per OD-08 |
| `purpose` | enum | yes | `INITIAL` or `DELAY_REPLAN` (= `recommendation.purpose`) |
| `changed_resource` | object | for `RESOURCE_CHANGE` | `{ "type": "BERTH" | "TUG" | "PILOT", "id": integer }` |
| `operational_plan_id` | integer | for `REPLAN` | Plan whose STARTED components stay fixed |
| `context_note` | string | no | Staff context for `MANUAL` (2.13 "regenerate with context changes"); stored in logs only [PROPOSED] |

- **Results:** `solver_run` (`status` OPTIMAL / FEASIBLE / INFEASIBLE / ERROR, `objective_value`, `solve_time_ms`, `solver_version`, `result_detail`, timestamps); for solved or FIFO: `recommendation` (PENDING_REVIEW, `source`, `purpose`, `entry_window_proposal_id`), options, tugs, rationale factors, `solver_run_model_version`.
- **`result_detail` JSON [PROPOSED]:** `{ "outcome": "SOLVED|FIFO_FALLBACK|NO_FEASIBLE_PLAN", "request_ids": [..], "failure_reason": "...", "binding_constraints": [{ "request_id": 1550, "constraint_type_code": "...", "detail": "..." }] }`.
- **Timeout:** solver 10 s then FIFO, total ≤ 12 s [DOCUMENTED, NFR-ENG-1].
- **Units:** times UTC; `service_time_*_hours` in hours; `delay_risk` 0–1; `objective_score` decimal.
- **Example:**

```json
{
  "contract_version": "1.0",
  "job_type": "PART2_SOLVE",
  "job_id": "a2c9d1f0-1b77-4c2e-8f44-5d6e7f809a1b",
  "run_id": 2301,
  "enqueued_at": "2026-10-22T10:02:11Z",
  "triggered_by_user_id": 41,
  "payload": {
    "trigger_type": "NEW_REQUEST",
    "berthing_request_ids": [1550],
    "purpose": "INITIAL"
  }
}
```

### 3.4 C-03 Yard placement — PROPOSED — REQUIRES APPROVAL

- **Mechanism:** `queue:part2`. **Payload:** `terminal_recommendation_id` (integer, row in `YARD_REC_REQUESTED`), `operational_plan_id` (integer).
- **Results:** `terminal_recommendation.lifecycle_status` `YARD_REC_GENERATED` (+ `rationale_template_id`, `confidence`) or `GENERATION_FAILED` (+ `failure_reason`); `yard_placement_item` rows (block required, slot optional).
- **Rule:** the worker never changes `yard_slot.container_id` or reserves capacity [DOCUMENTED, 2.16].

### 3.5 C-04 BAPLIE parse — PROPOSED — REQUIRES APPROVAL

- **Mechanism:** `queue:edi`. **Payload:** `baplie_file_id` (integer). The worker reads `storage_path`, `berthing_request_id`, `vessel_id` from the row.
- **Results:** `parse_status` PARSED / QUARANTINED / FAILED, `status`, `staged_payload`, issues, vessel/containers/manifest on success.
- **Timeout:** parse targets 3 / 10 / 25 s by size class [DOCUMENTED NFR-PERF-2].

### 3.6 C-05 Validation — PROPOSED — REQUIRES APPROVAL (depends on OD-03)

If OD-03 selects asynchronous validation:

| Field | Type | Required | Notes |
|---|---|---|---|
| `validation_type` | enum | yes | `OVERRIDE`, `YARD_ADJUSTMENT`, `BAPLIE_CORRECTION` |
| `subject_id` | integer | yes | `recommendation_option.id`, `terminal_recommendation.id` or `baplie_file.id` |
| `proposed_change` | object | yes | Override: `{ "component_type": "BERTH|PILOT|TUG|START_TIME", "new_value": ... }`; yard: list of `{ manifest_entry_id, yard_block_id, yard_slot_id? }`; BAPLIE: corrected staged payload |
| `request_token` | string | yes | Backend key to resume the pending user action |

Result (through C-06): `{ "valid": true }` or `{ "valid": false, "violations": [{ "constraint_type_code": "...", "message_key": "..." }] }`. The Backend answers the user 422 with the violation (2.13) and, if valid, stores the override as a new MODIFIED_BY_STAFF version. Pending action storage: Redis key with TTL keyed by `request_token` [PROPOSED; avoids a new table].

### 3.7 C-06 Completion signal — PROPOSED — REQUIRES APPROVAL (depends on OD-01)

Recommended option: after committing results, the worker pushes onto a Backend-consumed list (name to agree, e.g. `queue:ai-results`) a JSON message:

```json
{
  "contract_version": "1.0",
  "job_type": "PART2_SOLVE",
  "run_id": 2301,
  "status": "DONE",
  "outcome": "SOLVED",
  "error_category": null,
  "finished_at": "2026-10-22T10:02:14Z"
}
```

The Backend then performs the request transition (OD-02), audit (AIBD-025), notification (AIBD-022) and SSE push. A Backend poller over run tables is the safety net. Part 3 uses the same message for `DRIFT_ALERT` (`job_type = "PART3_EVALUATION"`, `outcome = "DRIFT_DETECTED"`, `drift_measurement_id`). This option must be checked against D18 ("no other pub/sub channel"): it uses a queue, not pub/sub.

### 3.8 Read endpoints for AI results — PROPOSED — REQUIRES APPROVAL

Documented behaviour, undocumented paths. Names follow the documented style (`/api/v1/...` appears in AD-15). Authorization per RBAC and terminal scope.

| Proposed endpoint | Returns | Roles (from UML actors) |
|---|---|---|
| `GET /api/v1/berthing-requests/{id}/part1-result` | Latest run outcome, windows, blocked periods, data sources and ages | Port Authority Staff |
| `GET /api/v1/berthing-requests/{id}/recommendation` | Current version, options, tugs, rationale, service time, delay risk; records a review (`recommendation_review`) | Port Authority Staff |
| `GET /api/v1/terminal-recommendations/{id}` | Yard placements, rationale, confidence | CT Operation Planner (own terminal) |
| `GET /api/v1/environment/conditions` | Latest weather, sea level, tide, surge, source, age, stale flag | Office users |
| `GET /api/v1/ai/evaluation` and `/ai/drift` | Metrics and drift measurements | Port Admin |

---

## 4. Database and Persistence Dependencies

Sources of truth: `PortSync.md` (port DB, 90 tables) and `SaaS.md` (central DB, 8 tables). **No AI table, model or record belongs in the SaaS database** [DOCUMENTED, D8, Master_Context §5.2].

### 4.1 Tables the AI reads (Backend-owned data)

| Domain | Tables | Key identifiers and fields |
|---|---|---|
| Configuration | `port_profile`, `port_setting`, `navigation_channel`, `channel_waypoint`, `license_state` (mode, if OD-18 requires) | lat/lon, timezone, depth, width, `speed_limit_knots`, `depth_datum` |
| Resources | `terminal`, `berth`, `berth_terminal`, `tugboat`, `pilot_profile`, `pilot_rank_limit`, `pilot_schedule`, `port_shift`, `tug_master_profile`, `tug_master_schedule` | `berth.max_vessel_loa`, `water_depth`; `tugboat.status`, `tug_power(_unit)`; `pilot_rank` |
| Vessel and request | `vessel`, `vessel_type`, `berthing_request`, `berthing_request_cargo`, `berthing_request_status_history` | `imo_number`, `loa`, `beam`, `max_draft`; `requested_eta`, `arrival_draft`, `block_coefficient`, `gross_tonnage`, `deadweight_tonnes`, `status` |
| Safety and productivity | `constraint_type`, `safety_constraint`, `terminal_productivity_profile` | `category`, `unit`, `comparator`, `is_hard_safety_limit`; scope columns; `productivity_rate`, `rate_unit`, `fixed_overhead_minutes` |
| Yard and equipment | `storage_facility`, `yard_zone`, `yard_zone_type`, `yard_block`, `yard_slot`, `reefer_zone_detail`, `dg_zone_allowed_class`, `oog_zone_detail`, `equipment`, `equipment_capability`, `container_size_type`, `imdg_class` | Capacity derived from slots |
| Plan and execution | `operational_plan`, `plan_component`, `recommendation_override`, `actual_status_update`, `deviation_log`, `status_type` | `reservation_state`, `planned_start/end`, `clock_skew_suspect` |
| Tide master data | `tide_constituent_set`, `tide_constituent` | `datum_reference`, `is_active` |
| Seeds | `objective_term`, `rationale_template`, `ml_model` | codes, versions |

### 4.2 Tables the AI writes

| Table | Writer | Notes |
|---|---|---|
| `forecast_run`, `environment_forecast_point` | Part 1 (SCHEDULED, ON_DEMAND); **Backend** for MANUAL | `chk_fr_on_demand`, `chk_fr_manual` |
| `tide_prediction_run`, `tide_prediction_point` | Part 1 | — |
| `demand_forecast_slot` | Part 1 | `method` AGGREGATION until P10 |
| `part1_run` | **Backend** creates; Part 1 updates status, outcome, retries, proposal link | — |
| `entry_window_proposal`, `feasible_window`, `blocked_period` | Part 1 creates (PROPOSED status) | **Backend** writes confirm/reject fields and statuses CONFIRMED, REJECTED; REPLACED by Part 1 on recompute [DERIVED] |
| `solver_run`, `solver_run_model_version` | Part 2 (creation by Backend per AIBD-013 if approved) | — |
| `recommendation`, `recommendation_option`, `recommendation_option_tug`, `recommendation_rationale_factor` | Part 2 | **Backend** sets CONFIRMED, MODIFIED_BY_STAFF versions (override), REPLACED on delay re-plan confirmation |
| `terminal_recommendation`, `yard_placement_item` | **Backend** creates the request row; Part 2 writes generation result | Backend writes decisions, export fields |
| `baplie_file` (status fields), `baplie_parse_issue`, `container`, `container_manifest_entry`, `vessel` (fill-empty) | EDI Parser | **Backend** creates the file row; corrections committed per OD-03 |
| `evaluation_metric`, `drift_measurement` | Part 3 | — |
| `ml_model_version`, `retraining_run` | Part 3 (data-gated) | **Backend** writes review fields |

### 4.3 Relation to operational requests

Every AI result is tied to the operational request by existing foreign keys: `part1_run.berthing_request_id`; `entry_window_proposal.berthing_request_id`; `recommendation.berthing_request_id` + `entry_window_proposal_id` (composite FK guarantees the same request); `operational_plan.recommendation_option_id`; `terminal_recommendation.recommendation_id`; `baplie_file.berthing_request_id`. `solver_run` has no request column; the request list of a run is recoverable from its `recommendation` rows and, for infeasible runs, from `result_detail.request_ids` [PROPOSED].

### 4.4 Potential schema changes (all PROPOSED — REQUIRES APPROVAL)

| ID | Change | Justification | Alternative without change |
|---|---|---|---|
| PS-01 | Amend the note on `part1_run.entry_window_proposal_id` to allow linking a proposal created for `NO_FEASIBLE_WINDOW` | Blocked periods need a proposal (`blocked_period.proposal_id` NOT NULL) — OD-10 | Keep the proposal unlinked and find it by `berthing_request_id` + `generated_at` |
| PS-02 | `port_setting` rows listed in AIBD-021 | Configuration without code changes | Environment variables (not changeable at runtime) |
| PS-03 | None for completion signalling | Recommended option uses Redis | — |
| PS-04 | None for validation | Pending actions kept in Redis with TTL | A small table if Redis persistence is not acceptable |
| PS-05 | Storage for deviation thresholds per component type | ClassDiagram 3 OI-09 | `port_setting` keys (no schema change) |

No new entity duplicates an existing one; every AI output already has a table in `PortSync.md`.

---

## 5. Backend Workflow Responsibilities

| Workflow | Before AI invocation | After AI result | Partial results, timeouts, invalid input, duplicates, retries |
|---|---|---|---|
| WF-1 BAPLIE | Write chain; match request (IMO + ETA ± 24 h, 422 if 0 or > 1); duplicate check (409); store file; create row; enqueue C-04 | SSE parse result; diagnostics view; correction and withdraw flows (withdraw refused 409 if a confirmed plan used the file) | Parser commits all or nothing; duplicate job ignored by claim; correction validated with the same rule set (AIBD-008) |
| WF-2 Environment | Configuration keys; egress; tide-set activation with datum check | Conditions view with surge and stale warnings | No Backend action on provider failure (Part 1 handles); decide `READ_ONLY` behaviour (OD-18) |
| WF-3 Part 1 | Customs Approval (`FULL`, from `PRELIMINARY_APPROVED`, 409 otherwise; manifest complete); status + history; create `part1_run`; enqueue C-01; reply 202 | On completion: SSE + `ENTRY_WINDOW_READY`; Gate 1 actions: confirm (re-check AIBD-034, status + history, enqueue C-02), reject all (allowed in `READ_ONLY`), recompute, reopen (no confirmed plan), manual data (AIBD-009) | Run discarded if request moved on; FAILED after 3 retries → show failure, allow recompute; `ENV_DATA_UNAVAILABLE` → offer manual entry |
| WF-4 Part 2 | Mode check (423); create `solver_run`; enqueue C-02 | `RECOMMENDATION_GENERATED` + history (OD-02); `RECOMMENDATION_READY`; review recording; confirm plan in one transaction (lock, re-check, book, plan CONFIRMED, version CONFIRMED, request `PLAN_CONFIRMED`; rollback + 409 on conflict) | `NO_FEASIBLE_PLAN` → request stays `ENTRY_WINDOW_CONFIRMED`, show binding constraints; FIFO → show "fallback, sub-optimal" flag (NFR-ENG-1); confirmation refused 409 if version outdated or not reviewed |
| WF-5 Regenerate / resource change / override | Detect resource changes; find pending recommendations using the resource; regenerate only before confirmation (409 after); override with reason (423/409/422) | New version current, previous REPLACED; override → MODIFIED_BY_STAFF version | Failure keeps previous version current; override validated (fail closed) |
| WF-6 Delay re-plan | Apply status update idempotently; classify deviation; significance per thresholds; enqueue C-02 `REPLAN` (not in `READ_ONLY`; notify instead) | Show proposal; on confirm, one transaction: reserve new, release unused, keep STARTED, old version REPLACED, proposal INITIAL, changed components to assignment | Rollback on failure, old version stays confirmed |
| WF-7 Yard | On plan confirmation for a container vessel with ACTIVE BAPLIE: create `terminal_recommendation` (`YARD_REC_REQUESTED`); enqueue C-03 (or record 423 reason) | `YARD_RECOMMENDATION_READY` / `_FAILED`; accept (no reservation), adjust (re-validated), reject (allowed in `READ_ONLY`); TOS-ready payload; `EXPORTED_TO_TOS` | Failure → planner may request again or reject |
| WF-8 Part 3 | Outcome data quality (trust flag); thresholds | `DRIFT_ALERT` notification and SSE to Port Admin; (P10) review of flagged candidates, `RETRAINING_REVIEW_NEEDED` | None; Part 3 runs on schedule |

**Never assigned to the AI:** booking resources, confirming or cancelling plans, assigning pilots and tug masters, sending notifications, entering data into the TOS, recording Customs Approval.

---

## 6. Infrastructure and Runtime Dependencies

### 6.1 Mandatory runtime

| Item | Status | Notes |
|---|---|---|
| Python runtime for four workers (EDI Parser, Part 1, Part 2, Part 3) | DOCUMENTED | Version not documented [PROPOSED in AI-090] |
| Google OR-Tools (CP-SAT) | DOCUMENTED | Part 2 |
| pyTMD | DOCUMENTED | Part 1 tide |
| LightGBM | DOCUMENTED (Phase 2) | Only from P10 |
| Redis with `queue:part1`, `queue:part2`, `queue:edi` | DOCUMENTED | Plus the completion list if OD-01 option is approved |
| MySQL primary (replica in production) | DOCUMENTED | Workers read state and write results directly |
| BAPLIE file volume | DOCUMENTED | Read access for EDI worker |
| Local volume for model artefacts (`artifact_path`) | DOCUMENTED (D8) | Backed up with the server |
| Outbound HTTPS to two weather/marine providers | DOCUMENTED (providers not chosen) | Port firewall rule |
| Environment variables for DB, Redis, provider credentials [PROPOSED names: `AI_DB_HOST`, `AI_DB_USER_<PART>`, `AI_REDIS_URL`, `AI_PROVIDER_PRIMARY_KEY`, `AI_PROVIDER_SECONDARY_KEY`, `AI_ARTIFACT_DIR`, `AI_BAPLIE_VOLUME`] | PROPOSED — REQUIRES APPROVAL | Secrets outside code (NFR-SEC-5) |
| Process supervision with automatic restart | PROPOSED (restart ≤ 60 s is DOCUMENTED NFR-REL) | Tool to choose |

### 6.2 Development and testing

| Item | Status |
|---|---|
| MySQL and Redis services in CI with schema from Backend migrations | DERIVED |
| Contract test suites in both repositories | PROPOSED |
| Synthetic reference-port fixtures | PROPOSED |
| Recorded provider payloads and BAPLIE corpus | DERIVED |

### 6.3 Optional improvements

| Item | Benefit |
|---|---|
| Metrics endpoint per worker (duration, queue wait, outcomes) | Faster diagnosis |
| Central log viewer on the port server | Operations |
| Replay tool for any run | Reproducibility and audit |

---

## 7. Backend Acceptance Checklist (ready for end-to-end testing)

| # | Check | AI tasks | Evidence |
|---|---|---|---|
| 1 | OD-01, OD-02, OD-03, OD-22 decided and contract v1 approved | AI-002, AI-003, AI-004 | Decision log, schema files |
| 2 | Envelopes C-01–C-05 pushed with the agreed format to the documented queues | AI-080 | Contract tests green in Backend CI |
| 3 | Run rows created before enqueue (`part1_run`, `solver_run`, `terminal_recommendation`, `baplie_file`) | AI-048, AI-058, AI-066, AI-032 | Integration test logs |
| 4 | Completion signal consumed; SSE pushed ≤ 5 s after commit (NFR-PERF-4) | AI-081 | E2E timing report |
| 5 | Request transitions and history rows written by the agreed owner, in one transaction | AI-058 | DB inspection in E2E |
| 6 | `READ_ONLY` gating: no enqueue for creation/regeneration; allowed actions still work | AI-082 | Failure-path report |
| 7 | Manual environmental data stored with MANUAL fields and recompute enqueued | AI-048 | E2E WF-3 |
| 8 | Override and yard-adjustment validation fail closed | AI-060, AI-067 | Contract + E2E |
| 9 | Plan confirmation re-checks and books in one transaction; conflict → 409 and rollback | AI-081 | E2E WF-4 |
| 10 | Resource changes trigger `RESOURCE_CHANGE` for pending recommendations only | AI-059 | E2E WF-5 |
| 11 | Deviation significance triggers `REPLAN`; confirmation transaction keeps STARTED components | AI-059 | E2E WF-6 |
| 12 | Yard request created automatically; accept reserves nothing | AI-066 | E2E WF-7 |
| 13 | Seeds loaded: constraint types, objective terms, rationale templates, model keys | AI-009 | Seeder run log |
| 14 | `port_setting` keys present with agreed values | AI-012 | Settings export |
| 15 | Terminal isolation applied to AI outputs (403) | AI-083 | Security report |
| 16 | Worker DB accounts with agreed privileges; `audit_log` INSERT/SELECT only | AI-013, AI-083 | Privilege matrix |
| 17 | Notifications of the documented types created for AI outcomes | AI-081 | `notification_log` rows in E2E |
| 18 | Clock-skew flag and deviation records populated for Part 3 | AI-071, AI-074 | Data inspection |
| 19 | Stale-run handling visible to staff with retry | AI-082 | Failure-path report |
| 20 | Egress to the two providers allowed; nothing else outbound from workers | AI-020, AI-083 | Network test |

---

## 8. Open Integration Decisions (prioritised)

| Priority | ID | Evidence | Question | Recommended option | Alternatives | AI components | Backend capabilities | Tasks | Blocking |
|---|---|---|---|---|---|---|---|---|---|
| 1 | OD-01 | D18 says Python writes MySQL and Laravel pushes SSE; no mechanism documented | How does Laravel learn a job finished? | Completion message on a Backend-consumed Redis list (§3.7) + poller safety net | Poller only; MySQL change capture | All | Queue consumer, SSE, notifications | AI-002 | Yes |
| 2 | OD-02 | AD-11 (Port Server sets `RECOMMENDATION_GENERATED`) vs AD-14 (AI lane writes `YARD_REC_GENERATED`); history row in same transaction | Who writes status transitions, history, audit and run rows? | Backend writes all request transitions, history, audit and run rows; AI writes run progress and result tables | AI writes everything in its result transaction | Parts 1, 2, B8 | Transition service, audit | AI-002, AI-058 | Yes |
| 3 | OD-03 | AD-11 A32, AD-14 A17 in AI lane; 2.13 / 2.8 synchronous 422; D9 async only | Where do override, adjustment and correction validations run? | Async validation job (C-05) with fail-closed handling | Duplicate rules in Laravel with shared test vectors; local HTTP endpoint on workers | B1, B2, B8 | Override and adjustment flows, UX for 202 | AI-004, AI-060, AI-067 | Yes |
| 4 | OD-22 | No message format documented; Laravel Redis payloads are PHP-specific | Envelope, versioning, retries, stale-run timeout | JSON envelope via `RPUSH` (§3.1) | Laravel job classes with a PHP-compatible Python decoder | All | Enqueue code | AI-003 | Yes |
| 5 | OD-08 | `solver_run` has no request column; D14 joint solve | Which requests are re-optimised per trigger? | Confirmed plans fixed; pending + trigger requests variable | Only trigger request variable | B5, B7 | Which pending versions may become REPLACED | AI-006 | Yes |
| 6 | OD-10 | `blocked_period.proposal_id` NOT NULL vs `part1_run` note | How is `NO_FEASIBLE_WINDOW` stored and shown? | Proposal without windows, linked from the run (PS-01) | Unlinked proposal | Part 1 | Part 1 result endpoint | AI-007, AI-048 | Yes |
| 7 | OD-16 | ClassDiagram 3 OI-09 | Where are deviation thresholds stored? | `port_setting` keys per component type | New table | B7, M4 labels | Deviation service | AI-008 | Partly |
| 8 | OD-18 | Not documented | Do weather pulls and Part 3 run in `READ_ONLY`? | Yes: system data continuity, no user-visible writes | Stop all AI jobs | A1, A2, Part 3 | Licence-mode service | AI-002 | No |
| 9 | OD-21 | Prototype has no replica; production has one | Read source and privileges | Primary for state reads; per-worker accounts | Replica reads with lag tolerance | All | DB administration | AI-013 | No |
| 10 | OD-17 | No `queue:part3`; F-01 | Who schedules Part 3? | Scheduler inside the Part 3 worker | Laravel scheduler enqueues a Part 3 job (needs a new queue) | Part 3 | None, or a scheduler entry | AI-073 | No |
