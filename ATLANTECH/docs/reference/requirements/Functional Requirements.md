# ATLANTECH — Functional Requirements Document v3

*Intelligent Port Operations · Aligned with UML\_Context, PortSync.sql (Port DB) and SaaS.sql (Central DB)*

## 1. Document Control

| Item | Value |
| --- | --- |
| Version | 3.0 (supersedes FRD v2 and the FR numbering FR-0.x to FR-11.x) |
| Date | 17 October 2026 |
| Source of truth | UML diagrams (UC, sequence 1.1–3.4, AD-1–AD-20, SM-1–SM-6) and the final SQL schemas. Where an older document disagrees, the UML and the SQL win. |
| Scope baseline | **MVP-M** (see section 2) |
| Reference port | Damietta Port (8 terminals, 36 berths, about 26 vessels/day). The product stays port-agnostic. |

### 1.1 Core principle

ATLANTECH recommends and coordinates. Existing terminal systems and authorised people execute. Every recommendation needs human approval before it becomes a plan.

### 1.2 How to read a requirement

Each requirement has the form:

`FR-<AREA>-<NN>` · **Priority** · **MVP flag** · statement · *references (UML, DB tables)*

- **P1 (Primary)**: the system does not work without it.
- **P2 (Secondary)**: adds real value, not a blocker for the core loop.
- **FW (Future work)**: designed in the UML or DB but not built in this release. Listed in section 14 with the reason.
- **MVP**: ✔ means it is inside MVP-M, ✘ means it is built after the MVP.

## 2. Scope Definition

### 2.1 MVP-M, the baseline

MVP-M delivers the full core loop, from berthing request to a confirmed plan to field execution and deviation tracking, plus the environmental entry window (Part 1) and the container yard recommendation.

```
Berthing Request → Preliminary Approval → Customs Approval
→ Part 1 Entry Window → Staff confirms window
→ Part 2 Recommendation (CP-SAT) → Review / Override → Confirm Plan
→ Dispatch (Pilot, Tug) → Field status (mobile) → Deviation → Re-plan
→ Yard recommendation → TOS-ready export
```

### 2.2 Inside the MVP

- Access control, RBAC, terminal scope, audit.
- Master data (berths, terminals, tugs, tug masters, pilots, CT equipment, yard, safety constraints).
- BAPLIE upload, parsing, diagnostics and correction.
- Berthing request lifecycle with customs approval.
- Part 1: weather, tide, entry windows.
- Part 2: CP-SAT allocation, service-time by formula, FIFO fallback, template rationale.
- Plan confirmation, cancellation, delay re-plan, safety exception.
- Dispatch, notifications, acknowledgement, escalation.
- Container yard placement and TOS-ready export.
- Mobile app with a basic Outbox.
- Licence modes SETUP, FULL and READ\_ONLY and plan limits.
- Part 3 limited to evaluation and drift monitoring.
- Central SaaS: registry, licence, billing records, daily check-in.

### 2.3 Outside the MVP

See section 14. In short: Part 3 automatic retraining, Non-Container Terminal modules, signing-key rotation, manual token delivery, MDM and VPN automation, external adapters (TOS, Nafeza), login lockout and billing reminders.

## 3. Conflicts Resolved Against FRD v2

| Topic | FRD v2 / older documents | FRD v3 decision (from UML and SQL) |
| --- | --- | --- |
| Berthing request intake | External API, CSV, Excel adapters (FR-3.2, FR-3.3, NFR-INT-3) | **Manual entry by Port Authority Staff only.** There is no external request source. Adapters are FW. |
| Request statuses | Received, needs revision, rejected, preliminary approved, recommendation generated, plan confirmed, completed, cancelled | `SUBMITTED`, `NEEDS_REVISION`, `PRELIMINARY_APPROVED`, `CUSTOMS_APPROVED`, `ENTRY_WINDOW_CONFIRMED`, `RECOMMENDATION_GENERATED`, `PLAN_CONFIRMED`, `PLAN_CANCELLED`, `COMPLETED`, `REJECTED`, `CANCELLED` |
| Solver failure | Master context D15: no fallback | **FIFO fallback on technical failure** (error or timeout), passing the same hard safety filter. Infeasible still ends as `NO_FEASIBLE_PLAN` with no FIFO plan. |
| Billing | Price per month | **Annual** licence and yearly billing records. |
| TOS link | Outbound adapter with buffering | **No automatic link.** The planner enters the TOS-ready plan by hand and records `EXPORTED_TO_TOS`. |
| Notifications | Push with backoff | **No push service.** SSE (web) and WebSocket event plus REST fetch (mobile). Re-send at T+10 and T+20, escalation at T+30. |
| Field task states | Assigned, started, completed | `UNASSIGNED`, `ASSIGNED`, `STARTED`, `DONE`. Acknowledged is read from the notification. |
| Login lockout | Lock after 5 failures | **Not in this release.** Failures are audited. |
| Marine Safety Officer | Gas carrier constraints for NCT | Sets port-wide safety constraints, environmental limits, chart datum and UKC parameters. |
| Environmental windows and AI feedback | Not in FRD v2 | Now specified (areas EW, ENV, LM). |
| Licence modes and tokens | Not in FRD v2 | Now specified (areas LIC, SAAS). |
| Window decision | Not defined | Staff can **confirm one window, reject all, or reopen**. They cannot edit a window manually. |

## 4. Global Rules (apply to every feature)

**FR-GL-01** · P1 · ✔ Every request passes this chain in order: authentication (401), role and terminal scope (403), minimum app version for mobile (426), licence mode and port status (423 or setup restriction), record version (`updated_at`, 409), business rules (409 or 422), plan limits (422). *Section 4 rules of UML\_Context.*

**FR-GL-02** · P1 · ✔ Every saved write is stored together with its audit entry in the same transaction. Every refused attempt is also audited. *audit\_log.*

**FR-GL-03** · P1 · ✔ No hard delete of users, admins, tokens, ports or master data. Users are deactivated, role assignments end with `active_to`, tokens get `revoked_at`, ports become `OFFBOARDED`.

**FR-GL-04** · P1 · ✔ Nobody can deactivate themselves or the last active admin (SaaS Admin or Port Admin). The count is read under a lock so two admins cannot remove each other.

**FR-GL-05** · P1 · ✔ Mutable records use `updated_at` as the version token. A stale write returns 409.

**FR-GL-06** · P1 · ✔ Multi-entity changes (plan confirmation, delay re-plan, plan cancellation, BAPLIE commit) run in one ACID transaction with full rollback on failure.

**FR-GL-07** · P1 · ✔ Hard safety and feasibility constraints are never relaxed by any model, fallback or automatic process.

**FR-GL-08** · P1 · ✔ Error answers use the codes: 401, 403, 409, 422, 423, 426, 503, with a specific and actionable message.

**FR-GL-09** · P2 · ✘ Error messages are available in Arabic and English.

## 5. Area AC — Access, Users and Roles

**Actors:** Port Admin, Office User, Mobile User. *Sequence 2.1–2.3, AD-18, AD-17. DB: `user`, `user_role_assignment`, `organization_unit`.*

### F-AC-1 Web sign-in

**FR-AC-01** · P1 · ✔ A user signs in on the web dashboard with an identifier and password after a CSRF cookie exchange. Missing fields return 422, wrong credentials 401, a deactivated account 403 `ACCOUNT_DISABLED`.

**FR-AC-02** · P1 · ✔ A successful sign-in returns the profile with roles and terminal scope, and the dashboard shows only the menus for those roles. The server still checks every request.

**FR-AC-03** · P1 · ✔ A user with `must_change_password` set must change the password before anything else.

### F-AC-2 Mobile sign-in

**FR-AC-04** · P1 · ✔ On first launch the app reads `/system/info` and learns the port id, port name, licence mode, token warning days and minimum app version.

**FR-AC-05** · P1 · ✔ Mobile sign-in sends device id, platform and app version. An app below the minimum version gets 426 and no token.

**FR-AC-06** · P1 · ✔ A valid mobile sign-in creates a device token that slides over 30 days, registers the device and caches the user's tasks.

**FR-AC-07** · P1 · ✔ After a 401 on sync, the app keeps all pending events and asks the user to sign in again. Pending events are sent only if the same user signs back in.

**FR-AC-08** · P1 · ✔ Logout ends the web session. Online mobile logout sets `revoked_at` on the device token. Offline logout deletes the local token and the server copy expires later.

### F-AC-3 User and role administration (Port Admin)

**FR-AC-09** · P1 · ✔ The Port Admin creates users with name, username, phone and a one-time password. Creating a user is blocked over the plan limit (422 `PLAN_LIMIT_EXCEEDED`). Tug masters and pilots count as users.

**FR-AC-10** · P1 · ✔ The Port Admin deactivates a user. The change applies on the user's next request. It stays allowed in READ\_ONLY mode.

**FR-AC-11** · P1 · ✔ The Port Admin assigns a role, with a terminal for terminal-scoped roles. A missing or unexpected terminal returns 422.

**FR-AC-12** · P1 · ✔ Removing a role sets `active_to`. The last active Port Admin role cannot be removed (409).

**FR-AC-13** · P1 · ✔ The Port Admin lists a user's devices and revokes one device or all sessions. This does not close the VPN, which Port IT handles in the MDM.

### F-AC-4 Authorisation

**FR-AC-14** · P1 · ✔ Roles follow one hierarchy in every domain: Administrator or Manager, then Officer or Planner, then Supervisor or Field Executor.

**FR-AC-15** · P1 · ✔ Terminal-scoped users see only their own terminal's cargo, stowage, yard plans and costs. Other terminals' data returns 403.

**FR-AC-16** · P1 · ✔ Field users see only the tasks assigned to them.

**FR-AC-17** · P1 · ✔ The SaaS Admin has no access to any port operational data.

## 6. Area MD — Port Master Data

**Actors:** Port Admin, Tug Dept. Manager, Pilotage Dept. Manager, CT Administrator, Marine Safety Officer. *Sequence 2.4–2.6, AD-18. DB: `port_profile`, `navigation_channel`, `channel_waypoint`, `terminal`, `berth`, `berth_terminal`, `tugboat`, `tug_master_profile`, `tug_master_schedule`, `pilot_profile`, `pilot_rank_limit`, `pilot_schedule`, `port_shift`, `equipment`, `equipment_category`, `equipment_capability`, `capability_type`, `yard_zone`, `yard_block`, `yard_slot`, `safety_constraint`, `constraint_type`, `terminal_productivity_profile`, `port_setting`.*

### F-MD-1 Port profile and channel

**FR-MD-01** · P1 · ✔ The Port Admin maintains the port profile and boundary.

**FR-MD-02** · P1 · ✔ The Port Admin maintains the navigation channel (depth stated on the chart datum, width) and its ordered waypoints.

### F-MD-2 Terminals and berths

**FR-MD-03** · P1 · ✔ The Port Admin creates terminals, typed as Container Terminal (CT) or Non-Container Terminal (NCT). Creation checks `max_terminals` from the licence.

**FR-MD-04** · P1 · ✔ The Port Admin creates berths with code, length, depth and maximum LOA. Creation checks `max_berths`. A berth can have a primary terminal or be shared.

**FR-MD-05** · P1 · ✔ Terminals and berths are updated or deactivated, never deleted. Inactive resources disappear from selection lists and engine candidates.

**FR-MD-06** · P1 · ✔ Changing a berth regenerates only pending recommendations that use it. A confirmed plan is never changed by a master-data edit.

### F-MD-3 Tugs, tug masters and pilots

**FR-MD-07** · P1 · ✔ The Tug Dept. Manager creates, updates and deactivates tugs with capability and status.

**FR-MD-08** · P1 · ✔ The Tug Dept. Manager creates tug masters (a user, a profile and the Tug Master role). A tug has at most one tug master (422 otherwise). Deactivating a tug master clears the tug link in the same transaction. The Tug Dept. Manager sets tug master shifts (`tug_master_schedule`), like pilot shifts.

**FR-MD-09** · P1 · ✔ The Pilotage Dept. Manager creates, updates, promotes and deactivates pilots and sets their shift availability. Rank limits come from `pilot_rank_limit`.

**FR-MD-10** · P1 · ✔ A change to a tug, tug master or pilot that affects planning regenerates only the pending recommendations that use it.

### F-MD-4 CT equipment, cranes and yard

**FR-MD-11** · P1 · ✔ The CT Administrator, limited to their own terminal, maintains equipment, cranes, quantities, maintenance and blocked quantities and capabilities. The available quantity is computed, never typed in.

**FR-MD-12** · P1 · ✔ The CT Administrator maintains the container yard structure: zones (general, reefer, dangerous goods, empty, OOG), blocks and slots with bay, row and tier.

**FR-MD-13** · P2 · ✔ The CT Administrator maintains the terminal productivity profile used by the service-time formula.

### F-MD-5 Safety constraints and environmental limits

**FR-MD-14** · P1 · ✔ The Marine Safety Officer defines safety constraints and environmental limits for wind, gust, visibility, wave, tide and under-keel clearance, plus chart datum and UKC parameters. Port-wide limits have no terminal.

**FR-MD-15** · P1 · ✔ These limits feed the hard safety filter before every recommendation and the entry-window assessment. The AI learning loop never changes them.

### F-MD-6 History and audit

**FR-MD-16** · P1 · ✔ The Port Admin browses the full port audit log, filtered by entity, actor and dates, with before and after values. Master-data history is the audit log filtered by entity.

### F-MD-7 NCT master data

**FR-MD-17** · P2 · ✘ The NCT Administrator maintains storage facilities, warehouses, tanks and cargo-handling equipment (master data only, no recommendations). *DB: `storage_facility`, `warehouse_detail`, `tank_detail`.*

### F-MD-8 Port configuration

**FR-MD-18** · P2 · ✔ Port settings (reminder lead time, rollback tolerance, delay thresholds) are stored in `port_setting` and changeable at runtime without code changes.

## 7. Area BQ — BAPLIE Cargo Manifest

**Actors:** Port Authority Staff, EDI Parser. *Sequence 2.7, 2.8, AD-8. DB: `baplie_file`, `baplie_parse_issue`, `container_manifest_entry`, `container`, `vessel`.*

### F-BQ-1 Upload and linking

**FR-BQ-01** · P1 · ✔ Port Authority Staff upload a BAPLIE file against a berthing request. The file is stored on the dedicated volume with its SHA-256 hash.

**FR-BQ-02** · P1 · ✔ If no request is selected, the system auto-matches by the file's IMO and an ETA within 24 hours. Exactly one match links it. Zero or several matches return 422 and staff select the request.

**FR-BQ-03** · P1 · ✔ The same file (same hash) for the same request returns 409, unless the earlier copy is `WITHDRAWN`. The same file for another request is allowed.

**FR-BQ-04** · P1 · ✔ Upload is blocked in READ\_ONLY mode (423).

### F-BQ-2 Parsing

**FR-BQ-05** · P1 · ✔ The parse job goes to its own Redis queue. The EDI Parser reads the file per UN/EDIFACT BAPLIE D.17A and the SMDG 2019 manual. Unknown segments produce warnings while valid slots still load.

**FR-BQ-06** · P1 · ✔ If the file's IMO differs from the request's vessel, the file is `QUARANTINED` and blocked.

**FR-BQ-07** · P1 · ✔ A valid file is committed in one transaction: the vessel is found or created by IMO (only empty fields are filled, form data is never overwritten), the previous `ACTIVE` file of the request becomes `REPLACED`, containers and stowage are saved, the file is `ACTIVE` and `parse_status` is `PARSED`.

**FR-BQ-08** · P1 · ✔ An invalid file keeps its extracted payload and diagnostics as staged data with `parse_status` `QUARANTINED`. Nothing reaches operational tables. A file that cannot be read at all gets `parse_status` `FAILED` and no staged data.

**FR-BQ-09** · P1 · ✔ The API reads the result from the database and pushes it to the dashboard over SSE.

### F-BQ-3 Diagnostics, correction and withdraw

**FR-BQ-10** · P1 · ✔ Staff see diagnostics field by field on the staged data.

**FR-BQ-11** · P1 · ✔ Staff correct the staged data. The same rule set re-validates it. If still invalid, nothing is committed. If valid, the commit is the same single transaction and records `corrected_by`.

**FR-BQ-12** · P1 · ✔ Staff withdraw a file uploaded by mistake. If a `PLAN_CONFIRMED` plan used it, the answer is 409 (cancel the plan first). Withdrawing deletes nothing. If the withdrawn file was `ACTIVE`, the latest `REPLACED` file of the request becomes `ACTIVE` again.

**FR-BQ-13** · P1 · ✔ Staff edit vessel particulars at any time outside READ\_ONLY mode.

**FR-BQ-14** · P1 · ✔ Correction, withdraw and vessel edit are blocked in READ\_ONLY mode (423).

**FR-BQ-15** · P2 · ✔ Parse times target: 3 s small, 10 s medium, 25 s large vessel call.

## 8. Area BR — Berthing Request Lifecycle

**Actors:** Port Authority Staff. *Sequence 2.9, AD-9, SM-4. DB: `berthing_request`, `berthing_request_cargo`, `berthing_request_status_history`, `vessel`, `vessel_type`, `cargo_type`.*

### F-BR-1 Creation

**FR-BR-01** · P1 · ✔ Port Authority Staff create a berthing request manually. The form requires: IMO, vessel name, LOA, beam, maximum draft, arrival draft, block coefficient, DWT, GT, ETA, cargo and target terminal.

**FR-BR-02** · P1 · ✔ The vessel is found by IMO or created from the form. The request starts as `SUBMITTED` and its status history starts.

**FR-BR-03** · P1 · ✔ Invalid values return 422. Creation is blocked in READ\_ONLY mode (423).

**FR-BR-04** · P1 · ✔ There is no external request source. All requests are entered by staff.

### F-BR-2 Edit and cancel

**FR-BR-05** · P1 · ✔ While the request is `SUBMITTED` or `NEEDS_REVISION`, staff can edit it or cancel it with a mandatory reason.

**FR-BR-06** · P1 · ✔ After preliminary approval, edit and cancel return 409.

### F-BR-3 Review decisions

**FR-BR-07** · P1 · ✔ A reviewer records Preliminary Approval. The request becomes `PRELIMINARY_APPROVED` and the dashboard is told it is ready for customs approval. This needs FULL mode.

**FR-BR-08** · P1 · ✔ A reviewer returns the request for revision with a reason (`NEEDS_REVISION`). The creator edits and resubmits, and it returns to `SUBMITTED`. Allowed in READ\_ONLY.

**FR-BR-09** · P1 · ✔ A reviewer rejects the request with a reason (`REJECTED`, final). Allowed in READ\_ONLY.

### F-BR-4 Status control

**FR-BR-10** · P1 · ✔ Every status change writes a row in `berthing_request_status_history` (from, to, who, when, reason) and an audit entry.

**FR-BR-11** · P1 · ✔ Status transitions follow SM-4 exactly:

| From | To | Trigger |
| --- | --- | --- |
| (new) | `SUBMITTED` | Staff create the request |
| `SUBMITTED` / `NEEDS_REVISION` | `PRELIMINARY_APPROVED` | Preliminary approval |
| `SUBMITTED` | `NEEDS_REVISION` | Return with reason |
| `NEEDS_REVISION` | `SUBMITTED` | Creator resubmits |
| `SUBMITTED` / `NEEDS_REVISION` | `REJECTED` / `CANCELLED` | Reject / cancel with reason |
| `PRELIMINARY_APPROVED` | `CUSTOMS_APPROVED` | Customs approval recorded |
| `CUSTOMS_APPROVED` | `ENTRY_WINDOW_CONFIRMED` | Staff confirm one window |
| `ENTRY_WINDOW_CONFIRMED` | `CUSTOMS_APPROVED` | Staff reopen the window (no confirmed plan) |
| `ENTRY_WINDOW_CONFIRMED` | `RECOMMENDATION_GENERATED` | Part 2 produces a version |
| `RECOMMENDATION_GENERATED` | `PLAN_CONFIRMED` | Staff confirm the plan |
| `PLAN_CONFIRMED` | `PLAN_CANCELLED` | Plan cancelled |
| `PLAN_CANCELLED` | `ENTRY_WINDOW_CONFIRMED` | The vessel's call continues, a new plan is needed |
| `PLAN_CONFIRMED` | `COMPLETED` | All components `DONE` |

**FR-BR-12** · P2 · ✔ A list and search screen shows requests filtered by status, vessel, terminal, ETA and date range.

## 9. Area EW — Customs Approval and Entry-Time Window (AI Part 1)

**Actors:** Port Authority Staff, AI Part 1 worker. *Sequence 2.10, 2.11, AD-10. DB: `entry_window_proposal`, `feasible_window`, `blocked_period`, `demand_forecast_slot`, `forecast_run`.*

Part 1 never allocates a berth, tug or pilot. It only proposes windows and constraints.

### F-EW-1 Customs approval and the Part 1 run

**FR-EW-01** · P2 · ✔ Staff record customs approval on a `PRELIMINARY_APPROVED` request. This is a manual entry, with no customs system integration. It needs FULL mode.

**FR-EW-02** · P2 · ✔ Recording customs approval sets `CUSTOMS_APPROVED`, enqueues a run on the dedicated Part 1 queue and answers 202. The result arrives by SSE.

**FR-EW-03** · P2 · ✔ A run that fails technically is retried up to 3 times, then marked `FAILED`. A run whose request is no longer `CUSTOMS_APPROVED` is discarded.

### F-EW-2 Assessment

**FR-EW-04** · P2 · ✔ The worker loads the request, the vessel particulars, the channel and the safety limits.

**FR-EW-05** · P2 · ✔ The worker obtains the astronomical tide (computing any missing hours), derives surge as sea level minus tide, and checks wind, gust, visibility, waves, tide and surge, under-keel clearance and squat against the approved limits.

**FR-EW-06** · P2 · ✔ Aggregate demand and peak congestion from open requests are computed and stored (Phase 1 is aggregation, not machine learning).

**FR-EW-07** · P2 · ✔ The outcome is one of: `WINDOWS_FOUND` (with blocked periods), `NO_FEASIBLE_WINDOW` (with the limiting factor), or `ENV_DATA_UNAVAILABLE`, stored on the Part 1 run. Missing data is not the same as unsafe conditions and is shown as such.

**FR-EW-08** · P2 · ✔ A new run replaces the earlier run for the same request (`REPLACED`). Each request uses its own data and never another request's result.

### F-EW-3 Staff decision

**FR-EW-09** · P2 · ✔ Staff review windows, blocked periods, conditions and the data timestamp.

**FR-EW-10** · P2 · ✔ Staff confirm one window. The server checks that the window belongs to the latest result and still passes the constraints. Otherwise it answers 409 with the reason and staff recompute. Windows cannot be edited manually and cannot be overridden.

**FR-EW-11** · P2 · ✔ Confirmation sets `ENTRY_WINDOW_CONFIRMED`, stores the window and enqueues the Part 2 recommendation.

**FR-EW-12** · P2 · ✔ Staff reject all windows with a reason (proposal `REJECTED`, `rejection_reason`). This is allowed in READ\_ONLY. The request stays `CUSTOMS_APPROVED`.

**FR-EW-13** · P2 · ✔ Staff press Recompute (blocked in READ\_ONLY) when no window is feasible or conditions changed.

**FR-EW-14** · P2 · ✔ Staff reopen a confirmed window with a reason while no plan is confirmed. The request returns to `CUSTOMS_APPROVED` and Part 1 runs again.

### F-EW-4 Manual environmental data

**FR-EW-15** · P2 · ✔ When the outcome is `ENV_DATA_UNAVAILABLE`, staff enter environmental data by hand. It is checked for completeness and horizon coverage, stored with `pull_type` `MANUAL`, `submitted_by` and the time, and a recompute is queued.

**FR-EW-16** · P2 · ✔ Manual data goes through the same safety constraints. It never bypasses a limit or overrides a window. Blocked in READ\_ONLY.

## 10. Area ENV — Weather and Tide Data (AI Part 1 scheduler)

**Actors:** Part 1 Scheduler, weather providers, Office User. *Sequence 2.12, AD-10. DB: `environment_forecast_point`, `forecast_run`, `tide_constituent`, `tide_constituent_set`, `tide_prediction_run`, `tide_prediction_point`.*

**FR-ENV-01** · P2 · ✔ Every 6 hours the Part 1 scheduler pulls weather, wave and sea-level data from the primary provider. All calls go out from the port. The port exposes no inbound endpoint for providers.

**FR-ENV-02** · P2 · ✔ If the primary provider fails, the failure and an alert are logged in the application log and the secondary provider is used. Data is stored with its `provider_rank` (`PRIMARY` or `SECONDARY`).

**FR-ENV-03** · P2 · ✔ If both providers fail, the last data is kept and marked `data_stale`.

**FR-ENV-04** · P2 · ✔ Once a day the worker computes the astronomical tide for a multi-day horizon in-house from published harmonic constants (pyTMD). No external tide service is used. A failed computation keeps the existing predictions and logs the failure.

**FR-ENV-05** · P2 · ✔ Stored data is usable for an assessment only if it was measured within the last 48 hours and covers the request's time horizon.

**FR-ENV-06** · P2 · ✔ On demand, at customs approval, the worker fetches fresh weather and sea level for that request.

**FR-ENV-07** · P2 · ✔ Any office user can view current conditions with the derived surge, the source and the age of the data. Stale, partial or manual data carries a clear warning.

**FR-ENV-08** · P2 · ✘ Forecast uncertainty margin (statistical or provider ensemble spread) is applied to limits.

## 11. Area RC — Recommendation Engine (AI Part 2)

**Actors:** AI Part 2 worker, Port Authority Staff. *Sequence 2.13, AD-11, AD-12, SM-4. DB: `recommendation`, `recommendation_option`, `recommendation_option_tug`, `solver_run`, `solver_run_model_version`, `objective_term`, `recommendation_override`, `recommendation_rationale_factor`, `rationale_template`, `ml_model`, `ml_model_version`.*

Part 2 is the only authority for allocating berth, tug and pilot.

### F-RC-1 Generation

**FR-RC-01** · P1 · ✔ A confirmed entry window enqueues a job on the dedicated Part 2 queue. No new plan is generated in READ\_ONLY mode.

**FR-RC-02** · P1 · ✔ The worker loads the confirmed window, Part 1 constraints, the BAPLIE data, the live availability of berths, tugs and pilots (actual state, not only planned state), competing requests and safety constraints.

**FR-RC-03** · P1 · ✔ A hard safety and feasibility filter removes every option that violates LOA, draft, terminal type, safety constraints or the Part 1 windows before solving.

**FR-RC-04** · P1 · ✔ Service time is estimated by formula `Moves / ProductivityRate + FixedOverhead` using `terminal_productivity_profile` (result in minutes). Delay risk is estimated by a rule-based score in the MVP.

**FR-RC-05** · P1 · ✔ The scheduling problem (a Berth Allocation Problem with tug and pilot resources) is solved with Google OR-Tools CP-SAT with a 10 s deadline. Port-wide demand is considered, not each vessel alone.

**FR-RC-06** · P1 · ✔ Solver result handling:

| Result | Behaviour |
| --- | --- |
| Solved | Candidates pass the filter, are ranked and written as a `PENDING_REVIEW` version with source `AI`. |
| Technical failure (error or timeout) | A FIFO plan by arrival order is built, passes the same hard filter, gets source `FIFO` and is flagged suboptimal. The failure reason is stored in `solver_run.result_detail`. |
| Infeasible | Outcome `NO_FEASIBLE_PLAN` with the binding constraints in `solver_run.result_detail`. No FIFO plan is made. The request stays `ENTRY_WINDOW_CONFIRMED` and staff may regenerate later. |

**FR-RC-07** · P1 · ✔ Options are ranked by time urgency, then by financial impact when time priority is close or tied.

**FR-RC-08** · P1 · ✔ Every option carries a rationale generated from a fixed predefined template tied to the constraints and objective terms that drove the solve. No LLM-generated text.

**FR-RC-09** · P1 · ✔ A written recommendation sets the request to `RECOMMENDATION_GENERATED` and notifies staff by SSE. The request stays in this status while versions change.

**FR-RC-10** · P2 · ✔ Each option shows berth, start, end, tugs, pilot, service-time estimate, delay risk and rationale with alternatives and confidence.

**FR-RC-11** · P2 · ✘ Service time uses LightGBM quantile regression (P50 and P90) and delay risk uses a LightGBM classifier, once enough real data exists.

### F-RC-2 Review, override and regenerate

**FR-RC-12** · P1 · ✔ Staff review options. The system records who viewed which version.

**FR-RC-13** · P1 · ✔ Staff override one component (berth, tug or pilot) with a mandatory reason. The new value is validated against availability and hard constraints. A valid override creates a new version marked `MODIFIED_BY_STAFF` and a row in `recommendation_override`.

**FR-RC-14** · P1 · ✔ An override on an outdated version or a confirmed plan returns 409. An override that breaks a constraint returns 422 with the violated constraint. READ\_ONLY returns 423.

**FR-RC-15** · P1 · ✔ Staff ask for a regeneration before the plan is confirmed. Success creates a new version and the previous one becomes `REPLACED`. Failure keeps the previous version current. On a confirmed plan the answer is 409.

**FR-RC-16** · P1 · ✔ When a berth, tug, pilot or equipment changes state, only pending recommendations that use it are regenerated. In READ\_ONLY mode nothing is regenerated and the previous version stays current.

**FR-RC-17** · P1 · ✔ The engine exposes a common recommendation concept so terminal-specific modules (container yard) share one output contract (target, time window, status).

### F-RC-3 Performance

**FR-RC-18** · P2 · ✔ Latency target: p50 ≤ 3 s, p95 ≤ 8 s, p99 ≤ 15 s with 15 concurrent pipelines. Solver deadline 10 s, total ≤ 12 s with fallback. At least 5 solver runs in parallel.

## 12. Area PL — Operational Plan

**Actors:** Port Authority Staff, Port Admin, AI Part 2 worker. *Sequence 2.14, AD-11, AD-12, SM-5. DB: `operational_plan`, `plan_component`, `status_type`.*

### F-PL-1 Confirmation

**FR-PL-01** · P1 · ✔ Staff confirm a reviewed and current recommendation version in FULL mode. An outdated or unreviewed version returns 409.

**FR-PL-02** · P1 · ✔ In one transaction the server locks and re-checks the berth, tugs and pilot, books them, creates the operational plan as `CONFIRMED`, sets the version `CONFIRMED` and sets the request `PLAN_CONFIRMED`. If anything was taken meanwhile, everything rolls back and the answer is 409 (regenerate).

**FR-PL-03** · P1 · ✔ The plan is split into components: pilotage, tug and, for a container vessel, yard. Pilot and tug components move `UNASSIGNED` → `ASSIGNED` → `STARTED` → `DONE`. The yard component moves `YARD_PENDING` → `YARD_ACCEPTED` → `YARD_DONE`, or `YARD_NOT_APPLICABLE`.

**FR-PL-04** · P1 · ✔ The plan becomes `COMPLETED` when all its components are done.

### F-PL-2 Cancellation

**FR-PL-05** · P1 · ✔ Staff cancel a confirmed plan with a reason and say whether the vessel's call continues. This is allowed only while no component has started. One transaction cancels the plan, releases berth, tug and pilot and sets the request `PLAN_CANCELLED`. Officers and field users are notified.

**FR-PL-06** · P1 · ✔ If the call continues, the request returns to `ENTRY_WINDOW_CONFIRMED` and a new recommendation is generated. Otherwise `PLAN_CANCELLED` is final. Offline field events for a cancelled plan are rejected later with a reason.

**FR-PL-07** · P1 · ✔ After any component has started, normal cancellation returns 409. Only the Port Admin can cancel the remaining work as a safety exception with a mandatory reason. It is audited as `SAFETY_EXCEPTION` and allowed in READ\_ONLY mode.

### F-PL-3 Delay re-plan

**FR-PL-08** · P2 · ✔ When execution tracking reports a significant delay, Part 2 builds a proposal version with purpose `DELAY_REPLAN` while the current plan stays confirmed. In READ\_ONLY mode no proposal is built and staff are told.

**FR-PL-09** · P2 · ✔ Staff confirm or discard the proposal. Confirming runs one transaction: new resources reserved, unused ones released, `STARTED` components kept, the old version `REPLACED`, changed components back to assignment. A failure rolls back and the old version stays confirmed.

**FR-PL-10** · P2 · ✔ The unified port state view shows berths, tugs, pilots, requests and plan status together.

## 13. Remaining Feature Areas

### 13.1 Area DP — Dispatch, Notifications and Acknowledgement

**Actors:** Pilotage Dept. Officer, Tug Dept. Officer, Marine Pilot, Tug Master, Scheduler, Realtime Server. *Sequence 2.15, AD-13, SM-5. DB: `plan_component`, `notification_log`, `port_setting`.*

**FR-DP-01** · P1 · ✔ After plan confirmation the server proposes a pilot, a tug master and a tug from current availability. The pilotage and tug paths run in parallel and independently.

**FR-DP-02** · P1 · ✔ The Pilotage Dept. Officer and the Tug Dept. Officer confirm the proposal or replace it. A replacement is checked for operation state, READ\_ONLY policy, availability, qualification and conflicts. An invalid choice returns 422 with the reason. An invalid operation state returns 409. READ\_ONLY returns 423, and choosing someone else cannot bypass it.

**FR-DP-03** · P1 · ✔ A valid assignment is saved with an audit entry and, for a replacement, logged against the plan. The component becomes `ASSIGNED`.

**FR-DP-04** · P1 · ✔ The task is sent at T+0: SSE to the web dashboard and a WebSocket event to the mobile app, which then fetches the full task by REST. Notifications are sent only after the database commit.

**FR-DP-05** · P1 · ✔ There is no push service. If delivery fails the failure state is recorded and the task appears on the next app open (REST catch-up).

**FR-DP-06** · P1 · ✔ The pilot or tug master acknowledges from the app. The acknowledgement goes through the phone Outbox. It marks the notification `ACKNOWLEDGED`, cancels remaining retries and does not change the plan state.

**FR-DP-07** · P2 · ✔ An unacknowledged task is re-sent at T+10 and T+20 minutes after the first send. At T+30 it is escalated with an `ACK_ESCALATION` notification to the assigning officer and the department manager.

**FR-DP-08** · P2 · ✔ A pre-execution reminder is sent at the configured lead time (default 2 hours) and only if the task is still active. It is separate from acknowledgement retries.

**FR-DP-09** · P2 · ✔ Delivery state (sent, failed, acknowledged) is recorded in `notification_log`. Delivery is never treated as acknowledgement.

**FR-DP-10** · P2 · ✔ Execution alerts reach users within ± 60 s of schedule. Dashboard updates arrive by SSE within 5 s of the commit.

### 13.2 Area YD — Container Yard Placement and TOS Hand-off

**Actors:** CT Operation Planner, AI Part 2 worker. *Sequence 2.16, AD-14, SM-5. DB: `terminal_recommendation`, `yard_placement_item`, `yard_zone`, `yard_block`, `yard_slot`, `container`, `container_manifest_entry`.*

**FR-YD-01** · P2 · ✔ When a plan is confirmed for a container vessel with an `ACTIVE` BAPLIE, a yard recommendation is requested automatically (`YARD_REC_REQUESTED`). In READ\_ONLY mode nothing is generated and the planner is told why.

**FR-YD-02** · P2 · ✔ Part 2 loads containers, yard structure and capacity, CT equipment, destinations and stowage and proposes a placement for containers and container groups. It validates yard constraints (zone type, capacity, reefer plugs, dangerous goods classes, OOG limits). No LIFO rule is assumed.

**FR-YD-03** · P2 · ✔ A valid result is stored as `YARD_REC_GENERATED` with a template rationale and confidence, and the planner is notified by SSE. A failure or timeout is stored as `GENERATION_FAILED` with the reason. The planner can request again or reject.

**FR-YD-04** · P2 · ✔ The planner accepts, accepts with adjustments (each adjustment is re-validated) or rejects with a reason. Accepting needs FULL mode and is a decision only: no slot is reserved and no occupancy is checked. Rejecting is allowed in READ\_ONLY mode and the yard component stays `YARD_PENDING`.

**FR-YD-05** · P2 · ✔ The system presents a TOS-ready version of the accepted plan in a human-readable format. The planner enters it into the TOS outside ATLANTECH and records `EXPORTED_TO_TOS` (allowed in READ\_ONLY). Nothing comes back from the TOS.

**FR-YD-06** · P2 · ✔ Yard recommendations respect terminal isolation. Only the owning terminal's planner sees them.

### 13.3 Area EX — Execution Tracking and Deviations

**Actors:** Marine Pilot, Tug Master, Terminal supervisor, Port Authority Staff. *Sequence 3.3, AD-15, AD-16, SM-5. DB: `actual_status_update`, `deviation_log`, `plan_component`.*

**FR-EX-01** · P1 · ✔ Pilots and tug masters update the actual status of their tasks from the mobile app (`ASSIGNED` → `STARTED` → `DONE`) in no more than 3 taps. Updates follow a monotonic state machine. Updates stay allowed in READ\_ONLY mode.

**FR-EX-02** · P1 · ✔ Each update stores the reporter, the status, the device time, the server time and the sync source in `actual_status_update`. Resource state (berth, tug, pilot) is updated from actual status.

**FR-EX-03** · P1 · ✔ The system compares planned against actual values and writes a `deviation_log` row when a meaningful difference exists: delay, a different resource actually used, or state differing from expected.

**FR-EX-04** · P1 · ✔ A significant deviation requests a delay re-plan proposal (see FR-PL-08). Staff are notified by SSE within 3 minutes.

**FR-EX-05** · P1 · ✔ Port Authority Staff browse and filter deviations by vessel, terminal, resource type and date range.

**FR-EX-06** · P2 · ✔ An offline event that conflicts with a server-side change (for example for a cancelled plan) is rejected with a reason the user can see and is logged.

**FR-EX-07** · P2 · ✔ The Container Terminal Supervisor or Dispatcher updates terminal actual status from a tablet. It feeds the same deviation mechanism.

**FR-EX-08** · P2 · ✔ A field event whose `received_at` minus `client_sent_at` is more than 5 minutes is applied but flagged `clock_skew_suspect`. Staff can review it. It is never used for training and there is no automatic correction.

### 13.4 Area MB — Mobile App (offline-first)

**Actors:** Marine Pilot, Tug Master, Terminal Supervisor. *Sequence 3.1, 3.2, AD-15, AD-17, SM-6.*

**FR-MB-01** · P1 · ✔ The app shows the user's assigned tasks and works offline from locally cached data.

**FR-MB-02** · P1 · ✔ Every change (status update, acknowledgement) is first written to a local Outbox with an idempotency key. Events are `PENDING`, `SENDING`, `DONE` or `REJECTED`. `DONE` and `REJECTED` events are purged after 30 days.

**FR-MB-03** · P1 · ✔ A sync cycle starts on a connectivity change or a manual sync. It checks real connectivity first (retry with backoff 2 s doubling, maximum 60 s, 5 tries), sends events in order and ends on a send failure.

**FR-MB-04** · P1 · ✔ The server applies each event exactly once using its idempotency key. An invalid event is rejected with a visible reason.

**FR-MB-05** · P1 · ✔ In READ\_ONLY mode creation events are rejected (423) while status updates and acknowledgements are applied. The app hides create buttons and shows a read-only banner.

**FR-MB-06** · P2 · ✔ The realtime connection (WebSocket) catches up on missed events after reconnecting. On Android it stays alive during an active task.

**FR-MB-07** · P2 · ✔ A sync badge updates within 3 s of reconnecting. Text is at least 16 sp.

**FR-MB-08** · P2 · ✔ An app below `min_app_version` is refused (426) and sync pauses until it is updated.

### 13.5 Area LM — Model Evaluation and Learning (AI Part 3)

**Actors:** AI Part 3 worker, Port Authority Staff, Port Admin. *Sequence 3.4, AD-16. DB: `evaluation_metric`, `drift_measurement`, `retraining_run`, `ml_model`, `ml_model_version`, `recommendation_override`, `deviation_log`.*

**FR-LM-01** · P2 · ✔ Part 3 evaluates outcomes on trusted data only: MAE, override rate, wait time and Part 1 window accuracy, stored in `evaluation_metric`. Everything stays on this port's own server.

**FR-LM-02** · P2 · ✔ A drift monitor computes PSI and rolling MAE for the models and stores them in `drift_measurement`.

**FR-LM-03** · P2 · ✔ A record is trusted when its status update has `clock_skew_suspect` = 0. Clock-skew-suspect events are always untrusted.

**FR-LM-04** · P2 · ✔ Staff overrides are treated as a signal to analyse, not as an automatic retraining trigger.

**FR-LM-05** · P2 · ✔ An evaluation and drift dashboard is available to the Port Admin.

**FR-LM-06** · FW · ✘ Guarded automatic retraining: a Challenger replaces the Champion only if not worse on MAE and override rate on time-based held-out data. The previous model is kept for rollback. A worse candidate is flagged to the Port Admin and never deployed.

**FR-LM-07** · FW · ✘ Forecast bias correction feeds corrected inputs back to Part 1.

**FR-LM-08** · P1 · ✔ No model, weight or update ever leaves the port. No learning is shared between ports or with the Central SaaS. Safety limits (wind, wave, visibility, UKC) are never changed automatically.

### 13.6 Area LIC — Port-side Licence and Operating Mode

**Actors:** Port Admin, Port Scheduler, Port User. *Sequence 1.13, 1.14, AD-5, AD-6, SM-3. DB: `license_state`, `port_setting`.*

**FR-LIC-01** · P2 · ✔ The port derives its mode in two stages: first the port status (`OFFBOARDED` or `SUSPENDED` gives `READ_ONLY`, `ONBOARDING` gives `SETUP`), then, for an `ACTIVE` port, the licence (`ACTIVE` with a valid token gives `FULL`, anything else gives `READ_ONLY` with the reason).

**FR-LIC-02** · P2 · ✔ In `READ_ONLY` mode new writes return 423 `LICENSE_READ_ONLY`. A server-side allowlist keeps: reads and reports, login, licence recovery, status updates and acknowledgements, reject and return actions, deactivating users, revoking tokens, uploading a manual token, and the Port Admin safety exception.

**FR-LIC-03** · P2 · ✔ In `SETUP` mode only the first Port Admin, setup users, port configuration, berths and resources can be written. Never operational records. `SETUP` is never re-entered from `READ_ONLY`.

**FR-LIC-04** · P2 · ✔ Adding a user, terminal or berth beyond the plan limits returns 422 `PLAN_LIMIT_EXCEEDED` and nothing is saved.

**FR-LIC-05** · P2 · ✔ The port checks in with the Central SaaS once a day at a random overnight time, or on demand with Check In Now. All calls start from the port. If the SaaS is unreachable the port keeps its token and mode and retries. Losing internet alone never stops work.

**FR-LIC-06** · P2 · ✔ The port verifies a token's signature (Ed25519), key id, port id and dates, ignores a token older than the stored one, rejects any token once `OFFBOARDED`, and refuses a token issued before a known suspension or revocation.

**FR-LIC-07** · P2 · ✔ Clock rollback is detected with a trusted time (later of the clock and the highest time seen), reset at each check-in. Tolerance is configurable (default 10 minutes).

**FR-LIC-08** · P2 · ✔ Both web and mobile show a read-only banner with its reason, or a warning when the token has 14 days or less left. Web uses `GET /api/v1/license/state` and mobile uses `/system/info`.

**FR-LIC-09** · P2 · ✔ The Port Admin views a licence status page (mode, statuses, limits, token and licence expiry, last check-in) with Check In Now.

**FR-LIC-10** · FW · ✘ Manual token file upload for ports without internet.

### 13.7 Area SAAS — Central SaaS Platform

**Actors:** SaaS Admin, Scheduler, Check-in API. *Sequence 1.1–1.12, AD-2, AD-3, AD-4, AD-7, SM-1, SM-2. DB (SaaS.sql): `saas_admin`, `license_plan`, `governorate`, `platform_port_registry`, `license`, `license_token`, `billing_transaction`, `platform_audit_log`.*

The SaaS never stores or reads port operational data and never calls into a port.

**FR-SAAS-01** · P1 · ✔ SaaS Admin signs in with email and password, then a one-time code sent by email. A failure is denied with a generic message and audited. No lockout in this release.

**FR-SAAS-02** · P1 · ✔ The SaaS Admin creates, edits and disables admin accounts with a logical role (Platform, License or Billing). Email is unique (422). Disabling ends that admin's sessions. Self and last active admin cannot be disabled (409).

**FR-SAAS-03** · P1 · ✔ The SaaS Admin keeps licence plans (limits and annual price) edited in place. Each licence keeps its own snapshot of limits, price and currency.

**FR-SAAS-04** · P1 · ✔ The SaaS Admin keeps the governorate list (Egyptian governorates with a seaport).

**FR-SAAS-05** · P1 · ✔ The SaaS Admin registers a port (name, UN/LOCODE, governorate, server endpoint). A `public_id` and a strong API secret are generated. Only the hash is stored. The secret is shown once. The port starts `ONBOARDING`. The SaaS Admin can update the record (the public id never changes) and rotate the API credential.

**FR-SAAS-06** · P1 · ✔ Port lifecycle: `ONBOARDING` → `ACTIVE` (activation checklist: valid token installed, essential settings, berths and resources, a Port Admin account; the Port Admin readiness confirmation is recorded in the audit), `ACTIVE` ↔ `SUSPENDED`, and any state → `OFFBOARDED` (final, revokes licences and tokens, the record is kept).

**FR-SAAS-07** · P1 · ✔ The SaaS Admin issues one annual licence per port. A licence starts `ACTIVE` if valid from the past and there is no other active one, otherwise `PENDING`. Renewals start when the current licence ends and are always `PENDING`. At most one `ACTIVE` licence per port.

**FR-SAAS-08** · P1 · ✔ The SaaS Admin suspends, reinstates and revokes a licence. Suspend sets `on_hold` on the PENDING renewal. Revoke also revokes its tokens.

**FR-SAAS-09** · P1 · ✔ A daily scheduler first expires `ACTIVE`, `SUSPENDED` and `PENDING` licences past their end date, then activates `PENDING` licences whose start date arrived (unless `on_hold` is set or another is active). Changes are audited as `SYSTEM`. Rerunning is harmless.

**FR-SAAS-10** · P1 · ✔ The Check-in API authenticates the port credential (401 and a `SYSTEM` audit entry on failure), updates `last_check_in_at` and returns port status, licence status and server time. A suspended or offboarded port, or a non-active licence, gets no token.

**FR-SAAS-11** · P1 · ✔ For an `ACTIVE` licence the API reuses the latest token while more than 14 days remain, otherwise signs a new one (lazy signing). The signer being down returns the existing token or 503. Token expiry is never after licence expiry. Claims: port id, port status, limits, `kid`, `nbf`, `iat`, `exp`, `jti`.

**FR-SAAS-12** · P1 · ✔ Billing: the SaaS Admin creates one transaction per licence and subscription year (only one live `PENDING`, `FAILED` or `PAID` per period, otherwise 422), records the outcome (`PAID` needs an external reference), retries a `FAILED` one, refunds a paid one or cancels an unpaid one. Billing never changes the licence.

**FR-SAAS-13** · P1 · ✔ Every SaaS write is stored with an append-only audit entry in the same transaction (`ADMIN` for admin actions, `SYSTEM` for scheduled jobs). A refused action is also audited.

**FR-SAAS-14** · P2 · ✔ A fleet monitor lists ports with status, last check-in and licence status and flags ports that stopped checking in, near expiry, suspended or revoked.

**FR-SAAS-15** · P2 · ✔ The SaaS Admin browses the platform audit log with filters (actor, action, port, dates) and billing history by port, licence, status and period.

**FR-SAAS-16** · FW · ✘ Manual token generation and out-of-band delivery with `delivered_at`.

**FR-SAAS-17** · FW · ✘ Signing-key rotation in three steps (prepare, activate, retire) through the KMS.

## 14. Future Work

| ID | Item | Why it is deferred |
| --- | --- | --- |
| FW-01 | Guarded automatic retraining and champion/challenger deployment (FR-LM-06) | Needs months of real operational data. Evaluation and drift monitoring is in the MVP. |
| FW-02 | Forecast bias correction (FR-LM-07) | Depends on stored forecast-versus-actual history. |
| FW-03 | LightGBM service-time and delay-risk models (FR-RC-11) | Formula and rules are used until data volume exists. |
| FW-04 | Forecast uncertainty margin (FR-ENV-08) | Refinement of Part 1 after the first deployment. |
| FW-05 | Non-Container Terminal recommendations (storage allocation, tanks, pipelines, gas carrier constraints) | Container Terminal is the committed scope. NCT keeps master data only. |
| FW-06 | Manual licence token and signing-key rotation (FR-LIC-10, FR-SAAS-16, FR-SAAS-17) | Operational procedures for production. Not needed to prove the core loop. |
| FW-07 | MDM, VPN, firewall, split DNS, TLS provisioning and support-session automation | Deployment work for the port IT team. Documented, not built as software. |
| FW-08 | External adapters: berthing request API, CSV and Excel, TOS, PMIS, Nafeza national single window | Explicitly out of the current design. Requests are manual and TOS hand-off is manual. |
| FW-09 | Login lockout after repeated failures | Not in this release. |
| FW-10 | Automatic billing reminders, payment gateway | Payment happens outside ATLANTECH. |
| FW-11 | Bilingual (Arabic and English) error messages | Delivered after the MVP if time allows (FR-GL-09). |
| FW-12 | Vision Module | Designed earlier, excluded from scope. |
| FW-13 | Cross-port or federated learning | Rejected: no AI artifact ever leaves a port. |

## 15. Traceability Summary (FRD v2 to FRD v3)

| FRD v2 group | FRD v3 area | Note |
| --- | --- | --- |
| FR-0.1–0.6 Central SaaS | SAAS, LIC | Annual billing, lazy token signing, check-in |
| FR-1.1–1.9 Master data | MD | Adds safety constraints, productivity profile, port settings |
| FR-2.1–2.6 BAPLIE | BQ | Staged data, auto-match, withdraw |
| FR-3.1–3.11 Berthing request | BR, EW | New statuses, customs approval, entry window |
| FR-4.1–4.9 Recommendation | RC, PL | Part 2, FIFO fallback, delay re-plan |
| FR-5.1–5.4 Dispatch | DP, MB | T+10, T+20, T+30 escalation, Outbox |
| FR-6.1–6.4 State tracking | EX | Clock-skew flag, offline conflicts |
| FR-7.1–7.3 Notifications | DP | No push service |
| FR-8.1–8.10 Container Terminal | MD, YD | Decision-only acceptance, manual TOS export |
| FR-9.1–9.6 Non-Container Terminal | MD (master data), FW-05 | Recommendations deferred |
| FR-10.1–10.8 Extensibility | MD, RC, EW | Common recommendation concept, pre-recommendation safety point |
| FR-11.1–11.6 Access control | AC, GL | Write chain, terminal scope |
| (new) Part 1 environmental | EW, ENV | Not in FRD v2 |
| (new) Part 3 learning | LM | Not in FRD v2 |
| (new) Licence modes | LIC | Not in FRD v2 |

*A requirement-level mapping (each old FR to each new FR) can be added as an appendix on request.*
