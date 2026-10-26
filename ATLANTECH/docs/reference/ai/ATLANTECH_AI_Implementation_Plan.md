# ATLANTECH AI — Implementation Plan

Phase-based, dependency-ordered. No dates, durations or effort estimates. Task IDs (`T<phase>.<n>`) are defined in `ATLANTECH_AI_Tasks.md`. `OD-n` (open decisions) and `BD-n` (backend dependencies) are defined in `ATLANTECH_AI_Backend_Dependencies.md`.

Sources: Master Project Context, UML Context, AI Architecture v3, System Architecture HLD v2.0, Proposal, original task file. Not available in this session: the FRD and `PortSync.sql`. Statements about columns and MVP flags are therefore marked for verification.

---

## 1. Planning principles (from the documented architecture)

1. **Three AI parts plus the EDI Parser are separate Python satellites.** They read port state and write results directly to MySQL. Parts never import each other.
2. **Runtime path:** Laravel → Redis queue (one queue per worker: `queue:part1`, `queue:part2`, `queue:edi`) → Python worker → MySQL → Laravel pushes SSE. Part 1's periodic pulls run from a scheduler inside Part 1. Part 3 is local and scheduled.
3. **Part 1 never allocates.** Part 2 is the only allocator. Part 3 learns locally and nothing leaves the server.
4. **Formula/rule paths come first; LightGBM is Phase 2 of each model** (M2, M3, M4) and exists only when enough trusted data does. The system must work end to end on formulas and rules alone.
5. **Hard safety limits are never relaxed by any model or automatically.** FIFO is a fallback for a technical solver failure only, and still passes the hard filter. Infeasible is final (`NO_FEASIBLE_PLAN`).
6. **Rationale is template-based, tide is in-house, BAPLIE parsing is deterministic.**
7. **Integration is built from the first phase**: Phase 0 fixes the contracts, and each AI phase ends with an integration check against a real Laravel instance (or a contract-faithful stub until the backend exists, then replaced).
8. **UML vocabulary is the target** where SQL enums differ (Master Context 7.4).

## 2. Phase map and dependencies

```
Phase 0  Foundations and contracts
   ├─► Phase 1  Part 1 data + physics ─► Phase 2  Part 1 windows, demand ─┐
   └─► Phase 3  EDI Parser  (parallel with 1–2)                          │
                              └───────────────────────────────────────────┴─► Phase 4  Part 2 core
Phase 4 ─► Phase 5  Part 2 re-plan + terminal recommendations
Phase 2, 4 ─► Phase 6  Part 3 evaluation, drift, registry, bias correction
Phase 6 + real data ─► Phase 7  LightGBM + guarded retraining (gated)
Phases 1–6 (7 if enabled) ─► Phase 8  End-to-end verification
```

Hard dependencies: Phase 0 before everything; Phase 2 and Phase 3 before Phase 4 (Part 2 needs the confirmed window/constraints and BAPLIE moves); Phase 4 before 5; Phase 6 before 7; the Part 1 bias hook (T2.6) is a no-op until T6.6 delivers a correction.
Parallelizable: Phase 1–2 with Phase 3; T4.4–T4.6 with T4.2–T4.3 once Phase 0 is done; Phase 6 can start once Phase 0 plus real or fixture outcome data exist; Phase 5 yard work (T5.2) is independent of re-planning (T5.1).

## 3. Material conflicts and gating that affect this plan

| # | Issue | Why it matters | Handling |
|---|---|---|---|
| 1 | **Scope gating of learning and NCT.** The Master Context says the MVP delivers Part 3 evaluation and drift only, and that retraining/bias correction and NCT recommendations come later, while decision D17 says everything is implemented for real. The FRD (which holds the MVP flags) was not available. | Determines whether Phases 5 (NCT part) and 7 are in the first release. | Phases are ordered so the MVP cut falls after Phase 6 (and after the CT part of Phase 5). Gated tasks are explicit (T5.3, T5.4, Phase 7). Open decision OD-18. Not blocking. |
| 2 | **Vocabulary in SQL vs UML** (recommendation versions, BAPLIE statuses, no FIFO label column, no marker for a delay re-plan proposal version). | AI writes these values; wrong enums break the contract. | AI uses UML values; change list is T0.5; backend dependency BD-4, OD-13. Blocks the first persistence phase (Phase 2) until agreed. |
| 3 | **Invocation of checks that the UML places inside Part 2 or the parser, while Part 2 and the parser only run from queues.** The override check (AD-11, step by the AI Engine) and BAPLIE correction re-validation (2.8, "shared rule set" run by the Port API) have no documented synchronous mechanism. | Decides whether a new interface is needed. | OD-12 and OD-11. Neither blocks Phases 1–3; both must be decided before T4.12 and T3.5. |
| 4 | **How Laravel learns that a queued run finished.** Python writes MySQL; the only push channel is Laravel's SSE. The mechanism by which Laravel notices completion is not documented. | Core integration contract for every part. | OD-5, decided in Phase 0 (T0.3). Blocks Phase 1 exit. |

No other conflict was found that changes the AI plan. (A stale line in UML Context section 11 says the master context has "no fallback" for the solver; the current Master Context and the UML diagrams both specify the FIFO fallback, and the plan follows them.)

---

## Phase 0 — Foundations and contracts

**Objective.** Fix everything the AI and backend must agree on before any model code: job and result contracts, run lifecycle, DB rules, vocabulary, shared safety-limit loader, test harness and the training-data policy.

**Tasks.** T0.1–T0.9.

**Prerequisites.** Access to `PortSync.sql`; agreement to hold a joint contract review with the backend team.

**Deliverables.** AI repo skeleton and shared library; job envelope and run-lifecycle contract; completion-signalling decision; DB grant list; vocabulary constants and SQL change list; shared safety-limit loader; test harness with Damietta-scale fixture and Laravel-stub producer; training-data policy record.

**Acceptance criteria.**
- A stub job on each of the three queues is consumed, retried on injected failure, and discarded when the request is ineligible.
- A multi-table write rolls back completely on injected failure; the AI DB user cannot update or delete `audit_log`.
- The safety-limit loader returns the strictest value across overlapping scopes in unit tests.
- Contract documents list statuses, field names and semantics with a version number.

**Backend implications and handoffs.** BD-1 (queue payload), BD-2 (completion signalling), BD-3 (DB user and grants), BD-4 (SQL enum changes). The backend cannot finalize its enqueue code, SSE trigger or migrations until these are agreed.

**Exit conditions.** OD-5, OD-6, OD-13 decided and written into the contracts; SQL change list handed to the backend; harness runs a scenario file end to end with stubs; OD-4 decided (or explicitly deferred with Phase 7 gated).

---

## Phase 1 — Part 1: environmental data and physics

**Objective.** Produce trustworthy, stored environmental inputs: raw weather and sea-level data, in-house tide predictions and the derived surge, with the documented stale-data and failure behaviour.

**Tasks.** T1.1–T1.8.

**Prerequisites.** Phase 0 complete; provider choice (OD-7); tide constituent source and datum relationship (OD-8); port coordinates in `port_profile` (BD-5).

**Deliverables.** Part 1 worker; internal scheduler; provider adapter with primary/secondary fallback; ingestion into `forecast_run` / `environment_forecast_point`; tide module writing `tide_prediction_run/point`; surge function; usability rule and `ENV_DATA_UNAVAILABLE` outcome; manual-data path.

**Acceptance criteria.**
- Provider failure matrix (primary ok, primary fails with alert and secondary used, both fail with `data_stale` and last data kept) produces the documented rows.
- Stored raw values equal provider values; converted values are separate.
- Tide output reproduces a published reference series for the port, within a tolerance agreed as part of OD-14; missing hours are filled; a failed computation leaves earlier predictions unchanged.
- Data older than 48 h, or not covering the request horizon, yields `ENV_DATA_UNAVAILABLE`; boundary cases covered.
- Manual data that is incomplete or does not cover the horizon is refused; valid manual data produces a run, with source `MANUAL` recorded.

**Backend implications and handoffs.** BD-5 (port coordinates, constituent sets, chart datum and UKC parameters seeded and editable by the Marine Safety Officer), BD-6 (the conditions view derives surge as sea level minus tide; same formula and units), BD-7 (manual data storage with submitting user and the recompute enqueue), BD-2 (completion signalling).

**Exit conditions.** A Customs-approval stub job runs to DONE or `ENV_DATA_UNAVAILABLE` with correct rows; backend confirms it can read the run status and trigger SSE per the Phase 0 contract; scheduled pulls verified under a controlled clock.

---

## Phase 2 — Part 1: feasibility, windows and demand

**Objective.** Turn environmental data and vessel particulars into feasible entry windows, blocked periods with limiting factors, and a congestion score, and make them consumable by the first human gate.

**Tasks.** T2.1–T2.9.

**Prerequisites.** Phase 1; OD-3 (window confidence meaning), OD-9 (margin method); vessel particulars validated on `berthing_request` (BD-8).

**Deliverables.** Threshold rules (A4); UKC with Barrass squat (A5); uncertainty margin (A6); window builder and writer; recompute/reopen handling; bias-correction read hook (identity until Part 3 provides one); M3 Phase 1 aggregation; Part 1 test suite; integration check 1.

**Acceptance criteria.**
- Each environmental limit has pass, fail and edge tests; hard limits are never loosened by margin or bias correction.
- UKC/squat results match hand-calculated reference cases.
- Output matches the Part 1 contract: `feasible_windows` (start, end, confidence), `blocked_periods` (start, end, limiting factor), `peak_congestion_score`, `data_versions` (weather and tide runs). A no-window outcome carries the limiting factor.
- A new run replaces the earlier proposal in one transaction; a run never reuses another request's data.
- Golden scenarios pass: safe, blocked by each factor, tidal window, no data, stale data, manual data.
- Integration check 1: Customs approval through a real Laravel instance produces results, SSE and a confirmable window.

**Backend implications and handoffs.** BD-8 (vessel particulars including draft and block coefficient), BD-9 (window confirmation: server re-check of "latest result and still passes" — owner decided in OD-10; reopen and recompute enqueue; Part 2 enqueue on confirmation), BD-1/BD-2. This is the first point where backend persistence of Part 1 output is needed; the contract is final here.

**Exit conditions.** Part 1 output contract versioned and accepted by the backend; OD-10 decided; golden scenarios and physics tests pass; integration check 1 passes.

---

## Phase 3 — EDI Parser (parallel with Phases 1–2)

**Objective.** Deliver deterministic BAPLIE ingestion that either commits a complete, valid file or commits nothing, with diagnostics staff can act on.

**Tasks.** T3.1–T3.6.

**Prerequisites.** Phase 0; real or representative BAPLIE sample files (data acquisition — see OD-4 for data policy); the BAPLIE file volume shared between Laravel and the parser (BD-10).

**Deliverables.** Parser worker on `queue:edi`; D.17A / SMDG 2019 parser; validation and diagnostics; atomic commit routine; shared correction rule set (per OD-11); fixture set and timing results.

**Acceptance criteria.**
- Valid files commit vessel (fill empty fields only), previous ACTIVE file to `REPLACED`, containers and manifest entries, new file `ACTIVE`, parse status `COMPLETED`, in one transaction.
- Invalid or incomplete files commit nothing operational; diagnostics are stored per field; the extracted payload is kept as staged data; status `HAS_ERRORS`. IMO mismatch with the request is blocking.
- Unknown segments produce warnings while valid slots still load.
- Same input gives the same verdict in the parser and in the correction path.
- Parse times are measured for small, medium and large calls against NFR-PERF-2 (3 s / 10 s / 25 s per UML Context section 10; confirm against the NFR document).

**Backend implications and handoffs.** BD-10 (upload, hash, duplicate check, file volume mount, request linking and auto-match, parse job enqueue); OD-11 (who executes correction re-validation, and where staged payload lives); BD-4 (BAPLIE status values). Backend can proceed on upload and duplicate handling independently of parser internals once the job contract exists.

**Exit conditions.** Fixture suite passes including malformed files; atomicity proven by injected failure; OD-11 decided; backend upload→parse→SSE flow verified.

---

## Phase 4 — Part 2: core recommendation engine

**Objective.** Produce ranked, explainable berth/tug/pilot recommendations from a confirmed window, with the documented solver outcomes and persistence, and prove the end-to-end human-gated flow.

**Tasks.** T4.1–T4.14.

**Prerequisites.** Phase 2 (confirmed window and constraints) and Phase 3 (BAPLIE moves); OD-1 (tug count), OD-2 (financial impact), OD-3 (confidence and delay-risk semantics), OD-15 (concurrency and solve scope), OD-16 (P90 in Phase 1 service time), OD-12 (override validation); live-state master data (BD-11, BD-12).

**Deliverables.** Part 2 worker and `solver_run` handling; live-state loader; hard constraint filter; M2 Phase 1; M4 rule-based score; tug-count function; CP-SAT model with `objective_term` records; ranker; outcome handling (ranked options, FIFO fallback, `NO_FEASIBLE_PLAN`); transactional persistence; rationale templates; override validation function; test suite; integration check 2.

**Acceptance criteria.**
- Ranked options carry `rank`, `berth_id`, start, end, tugs, `pilot_id`, service-time P50 and P90, `delay_risk`, `rationale_text`, stored under a `GENERATED` recommendation version with its source label (AI or FIFO).
- Solved, technical-failure and infeasible paths each produce the documented rows; FIFO passes the same hard filter and is labelled; infeasible produces no FIFO plan and records the binding constraints.
- Every candidate violating a hard constraint is discarded in tests; Part 1 constraints are never relaxed.
- Competing vessels for the same pilot or tug in overlapping windows are resolved by the joint solve in the contention golden scenario.
- Solver deadline and total response time are measured against NFR-ENG-1 (10 s deadline, total ≤ 12 s) and NFR-PERF-1; the measurement definition is OD-17.
- Rationale is generated from versioned templates for every option, with the template version stored.
- Integration check 2: window confirm → recommendation → SSE → review → override → plan confirm; the 409 path when a resource was taken between review and confirm.

**Backend implications and handoffs.** BD-11 (pilot/tug schedules, shifts, rank limits, `plan_component` reservations), BD-12 (`terminal_productivity_profile`), BD-13 (plan-confirm transaction with re-check and rollback, override persistence in `recommendation_override`, version handling), OD-12, OD-15. The contract for recommendation and option fields is final at this phase exit.

**Exit conditions.** Contract versioned; performance results recorded; golden scenarios and integration check 2 pass; OD-1, OD-2, OD-3, OD-15, OD-16 decided and implemented.

---

## Phase 5 — Part 2: re-planning and terminal recommendations

**Objective.** Support plan change after confirmation and deliver the terminal-level recommendations (CT first).

**Tasks.** T5.1–T5.5.

**Prerequisites.** Phase 4; yard master data entered by the CT Administrator (BD-14); FRD confirmation of FR-8.4 and of the MVP flag for NCT (OD-18).

**Deliverables.** Re-planning logic (`REPLAN`, `RESOURCE_CHANGE`) on a rolling horizon; CT yard placement with TOS-usable output; CT timing recommendation; NCT storage allocation (gated); integration check 3.

**Acceptance criteria.**
- A resource change regenerates only pending recommendations; the engine never alters a confirmed plan.
- A delay re-plan creates a proposal that is distinguishable from a normal version; confirming it (backend transaction) keeps STARTED components and returns changed components to assignment.
- Yard placements respect yard structure, reefer, OOG and DG-class rules, assume no LIFO rule, and every adjustment is re-validated; output includes rationale and confidence.
- Terminal recommendations are visible only to their own terminal.
- Integration check 3 passes: plan confirmed → yard job → accept/adjust/reject → TOS-ready payload → export recorded; delay re-plan confirm transaction.

**Backend implications and handoffs.** BD-14 (yard master data, yard-recommendation endpoints and statuses, TOS-ready payload presentation, export recording), BD-13 (re-plan confirm transaction), OD-13 (proposal-version marker), BD-4. Terminal-level row security applies to `terminal_recommendation`.

**Exit conditions.** CT flow verified; NCT either delivered or formally deferred per OD-18; re-plan contract versioned.

---

## Phase 6 — Part 3: evaluation, drift, registry and bias correction

**Objective.** Close the loop with trusted-data evaluation, drift monitoring, a local model registry and forecast bias correction, without any retraining.

**Tasks.** T6.1–T6.8.

**Prerequisites.** Phase 0; field outcome data (`actual_status_update`, `deviation_log`, `recommendation_override`) from real operation or fixtures; trust flags persisted (BD-15); model volume and registry seeding (BD-16); numeric criteria (OD-14).

**Deliverables.** Part 3 worker and scheduler; trusted-data loader; evaluator writing `evaluation_metric`; drift monitor writing `drift_measurement` with an alert row; registry with the four `ml_model` keys; bias correction module (versioned `FORECAST_BIAS_CORRECTION`); override analysis report; guard tests.

**Acceptance criteria.**
- No clock-skew-suspect or manually corrected record appears in any dataset.
- Metrics (MAE, override rate, wait time, Part 1 window accuracy) are reproducible from stored data.
- Injected drift raises the alert row; a healthy run records a healthy status.
- The bias correction is applied in Part 1 when present, logged with its version, and raw provider data is untouched.
- The override report triggers nothing.
- Guard tests fail if any safety-limit table is written or any model artefact is exported, and show no outbound network use beyond the weather providers.

**Backend implications and handoffs.** BD-15 (trust and clock-skew flags must be stored on training-relevant records), BD-16 (model volume mount and registry location), BD-17 (drift alerts delivered to the Port Admin over SSE from the rows Part 3 writes).

**Exit conditions.** Evaluation and drift verified on fixtures and any available real data; bias correction consumed by Part 1 in an integration test; guards pass. This is the documented MVP boundary for Part 3.

---

## Phase 7 — LightGBM models and guarded retraining (gated)

**Objective.** Replace formula and rule baselines with LightGBM models only where trusted data supports it, with guarded, reversible promotion.

**Tasks.** T7.1–T7.8.

**Prerequisites.** Phase 6; the data-readiness gate (T7.1) defined by OD-4 and OD-14 and passed per model; OD-18 confirms this phase is in scope.

**Deliverables.** Dataset builders (time-ordered, trusted-only); M2 quantile model; M4 classifier; M3 regressor; model serving with fallback to the formula/rule path; retraining pipeline with Champion/Challenger decision and rollback; flagged-candidate output.

**Acceptance criteria.**
- Each model is evaluated against its formula/rule baseline on held-out, time-based data under the evaluation rule agreed in OD-14; a model that does not beat its baseline stays unpromoted.
- A challenger worse than the champion on MAE or override rate is never promoted; the decision (`PROMOTED`, `KEPT_CHAMPION`, `FAILED`) is stored with the model versions; the previous version is kept and rollback restores it.
- Missing, corrupt or invalid-output models fall back to the formula/rule path without failing the run.
- Models and training data stay on the port's server.
- Promoted models are verified inside integration check 2 (recommendation still satisfies the Part 2 contract).

**Backend implications and handoffs.** BD-17 (flagged candidates surfaced to the Port Admin), BD-16 (volume and permissions). No new request/response interface is required: models are loaded by the Parts.

**Exit conditions.** For each model either "promoted and verified end to end" or "baseline retained with recorded reason".

---

## Phase 8 — End-to-end verification and release readiness

**Objective.** Verify the whole AI stack inside the real ATLANTECH workflow and leave the backend team with what they need to operate it.

**Tasks.** T8.1–T8.5.

**Prerequisites.** Phases 1–6 (and 7 if in scope); the backend flows implemented through dispatch and field feedback.

**Deliverables.** End-to-end suite; failure-injection report; capacity and latency measurements; isolation verification; handoff pack.

**Acceptance criteria.**
- The full workflow (request → customs → Part 1 → window confirm → Part 2 → review/override → plan confirm → dispatch → field actuals → deviation → re-plan → Part 3 evaluation) passes.
- Failure injection (worker down, malformed job, DB failure mid-write, provider outage, solver timeout, invalid model output) yields the documented outcome, a log entry and no partial writes.
- Capacity results are recorded against NFR-PERF-1 (15 concurrent pipelines), NFR-ENG-1 and NFR-CAP-2 (≥ 5 parallel solver runs), using the definitions fixed in OD-17.
- No safety limit changed and no artefact left the server.

**Backend implications and handoffs.** Runbook, contract versions, model/version cards and known limitations delivered to the backend team.

**Exit conditions.** All acceptance criteria met or an explicit, recorded exception per item.

---

## 4. Verification layers (kept distinct)

| Layer | What it proves | Where |
|---|---|---|
| Component and model evaluation | Physics, rules, formulas and models are correct on their own | T2.1–T2.3, T4.3–T4.8, T7.3–T7.5 |
| Interface/contract validation | Inputs and outputs match the agreed contracts; statuses use the UML vocabulary | T0.2–T0.5, T2.4, T4.10 |
| Integration checks with a real Laravel instance | Queue → worker → MySQL → SSE and the human gates work | T2.9, T4.14, T5.5 |
| End-to-end and failure testing | The full workflow and failure behaviour hold | T8.1–T8.4 |
