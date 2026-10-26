# Coding notes and mapping

Things to read while coding. Each point gives its source so you can check it. Where a decision is still open, it says so.

## 1. Vocabulary (mapping retired)

**Decision (20 October 2026):** the UML documents are being changed to use the SQL and class-diagram names, so the code uses the database names everywhere and needs no translation layer. The edits for the UML are in `ATLANTECH_UML_Alignment_Report.md`.

Only this short legend stays, because these are terminology, not naming errors:

| Term in the documents | Value in the database |
|---|---|
| Port Admin | role `PORT_GM` |
| Port Authority Staff | `PORT_STAFF` |
| CT or NCT Administrator | `TERMINAL_ADMIN` (CT or NCT from the unit's terminal type) |
| CT Operation Planner | `TERMINAL_OFFICER` |
| On-site Field User (terminal) | `TERMINAL_SUPERVISOR` |
| CT, NCT | `CONTAINER`, `NON_CONTAINER` |
| Outbox `idempotency_key` (phone) | `actual_status_update.client_event_id` (server) |
| Component state DONE | reached by the field status code `COMPLETED` |

Port IT Administrator, Support Engineer and SaaS Admin have no port role. "Department manager" in escalations means `PILOTAGE_MANAGER` or `TUG_MANAGER`.

**SQL values no flow writes yet (decision needed):** `recommendation.status` REJECTED and INVALIDATED, `license.status` SUPERSEDED. Define when they are written, or drop them from the SQL later.

## 2. Values stored nowhere (derive them)

| Concept | How to derive | Source |
|---|---|---|
| Pilot or tug component state UNASSIGNED / ASSIGNED / STARTED / DONE | UNASSIGNED when `assigned_user_id` is NULL; ASSIGNED when it is set; STARTED and DONE from the latest `actual_status_update` of the component | SM-5, FR-PL-03 |
| "Acknowledged" | `notification_log.status = 'ACKNOWLEDGED'` for the assignment's notification | FR-DP-06 |
| Yard component state | from the latest `terminal_recommendation.lifecycle_status` (Report A, Tier 2 item 7): none or REQUESTED to YARD_PENDING, ACCEPTED to YARD_ACCEPTED, EXPORTED_TO_TOS or the terminal status report to YARD_DONE; no container call to YARD_NOT_APPLICABLE | SM-5, AD-14 |
| "No feasible window" | a Part 1 run with `outcome = 'NO_FEASIBLE_WINDOW'` (Report A, Tier 1 item 3) and blocked periods | FR-EW-07 |
| Berth and tug occupancy | `plan_component` rows with `reservation_state = 'RESERVED'` overlapping the window. Do not use `tugboat.status` for planning | Master Context 7.3 |
| Tug status BUSY | stored for operations only: set BUSY when the tug's component starts, back to AVAILABLE when it completes. Notify the Tug Officer (type `TUG_STILL_BUSY`) if still BUSY after `planned_end`. Agree this rule with your team: no state machine defines it | `tugboat.status` comment |
| Confirmed recommendation | the version whose option an `operational_plan` references | `recommendation_option` comment |
| Free reefer plugs, yard zone capacity and occupancy | `total_power_plugs` minus reefer containers in the zone; counts from `yard_slot` | table comments |
| Trusted record (decided: no `is_trusted` column) | `clock_skew_suspect = 0` on the source `actual_status_update`, reached from a deviation through `status_update_id`. Part 3 filters on this. Manual corrections are not marked untrusted in the MVP | FR-LM-03, Report A section 3 |
| Significant deviation | compare against thresholds in `port_setting` per operation type, in code | FR-MD-18, FR-EX-04, AD-16 N1 |

## 3. Rules the database does not enforce (enforce them in code)

| Rule | Source |
|---|---|
| Every berth has at least one `berth_terminal` row | `berth_terminal` comment |
| A user's role matches the unit type, and a terminal role has a terminal (a CHECK covers the first part) | FR-AC-11, `user_role_assignment` |
| Pilot user must hold MARINE_PILOT, tug master user must hold TUG_MASTER (in MARINE_SERVICES) | D-29 |
| One tug master per tug (unique index) and clearing the tug link when a tug master is deactivated, in one transaction | FR-MD-08 |
| Active shifts must not overlap | `port_shift` comment |
| Safety constraints of the same type and scope must not overlap; when several scopes apply, the strictest value wins | `safety_constraint` comment, FR-MD-14 |
| Productivity profile periods must not overlap | `terminal_productivity_profile` comment |
| Terminal boundary lies inside the port boundary | `terminal` comment (no v3 clause, so treat as a soft rule) |
| Datum rule: `depth_datum` of channel and berths equals the active tide set's `datum_reference` | `navigation_channel`, `tide_constituent_set` comments |
| `arrival_draft` ≤ `vessel.max_draft`; IMO check digit; ISO 6346 check digit (warning) | table comments |
| Net tonnage and moulded depth are required at Preliminary Approval | `vessel` comment |
| Customs Approval and yard recommendation are blocked while a manifest line is incomplete (`full_empty` NULL) | `container_manifest_entry` comment |
| Plan limits: `max_terminals`, `max_berths`, `max_users` (all registered terminals count; active users count; pilots and tug masters count as users) → 422 `PLAN_LIMIT_EXCEEDED` | FR-LIC-04, AD-18 S8 |
| Accepting a storage allocation adds its quantity to `reserved_capacity` (NCT, future work) | D-46 |
| Terminal isolation: a terminal user sees only their terminal's cargo, stowage, yard plans and costs (403) | FR-AC-15 |

## 4. Row version (optimistic lock): done in code

You decided to do this in code (OI-24). Suggested approach, so every team member does it the same way:

- Use `updated_at` as the version token (ETag). Return it with every editable record, and require it on update; compare before saving; return **409** if it changed (FR-GL-05, AD-18 D7).
- Tables that already have `updated_at`: `navigation_channel`, `port_profile`, `port_setting`, `license_state`.
- Tables that need `updated_at` added by a Laravel migration: `user`, `terminal`, `berth`, `tugboat`, `safety_constraint`, `port_shift`, `equipment`, `storage_facility`, and the other master-data tables the screens edit.
- Where a double-click can write twice in the same second, compare with a precision of microseconds (`datetime(6)`) or add an integer `version`. Decide once for all tables.

## 5. Transactions and locks

| Operation | Rule | Source |
|---|---|---|
| Confirm plan | One transaction: lock and re-check berth, tugs, pilot; book; create plan CONFIRMED; set request PLAN_CONFIRMED. Anything taken meanwhile: roll back, 409 "regenerate" | FR-PL-02, AD-11 |
| Cancel plan | One transaction: plan CANCELLED, release berth, tug, pilot, request PLAN_CANCELLED. After any component STARTED: 409, only the Port Admin safety exception (audit action `SAFETY_EXCEPTION`, allowed in READ_ONLY) | FR-PL-05 to FR-PL-07 |
| Delay re-plan confirm | One transaction: reserve new, release unused, keep STARTED components, old version REPLACED, changed components back to assignment. **Order matters for the unique key:** first mark the old recommendation version REPLACED, then set the new version's `purpose` to `INITIAL` | FR-PL-09, Report A Tier 1 item 4 |
| Regenerate before confirmation | New version, previous REPLACED. Failure keeps the previous version current. After confirmation: 409 | FR-RC-15 |
| BAPLIE commit | One transaction: find or create vessel by IMO (fill empty fields only), previous ACTIVE file REPLACED, containers saved, file ACTIVE, parse status COMPLETED. Withdraw ACTIVE file: latest REPLACED file becomes ACTIVE; 409 if a PLAN_CONFIRMED plan used it | FR-BQ-07, FR-BQ-12 |
| Last Port Admin | Count other active `PORT_GM` roles under a lock so two admins cannot remove each other | FR-GL-04 |
| Every write | Saved together with its audit entry in the same transaction. Every refused attempt is also audited (`REJECTED_ATTEMPT`, with route, code and reason in `after_state`) | FR-GL-02 |
| Notifications | Sent only after the commit | FR-DP-04 |

Request check order (do not change it): authentication 401 → role and terminal scope 403 → mobile minimum version 426 → licence mode and port status 423 (or setup restriction) → record version 409 → business rules 409 or 422 → plan limits 422 (FR-GL-01).

## 6. Things to watch

1. **Forced password change (decided: column `user.must_change_password`).** Set it to 1 when a user is created or a password is reset with a one-time password. While it is 1, allow only "change password" and "log out"; return 403 with a clear code (for example `PASSWORD_CHANGE_REQUIRED`) for anything else, and check this right after authentication in the request order. Set it back to 0 in the same transaction as the password change, with an audit entry (FR-AC-03, AD-17, AD-18).
2. **SETUP mode (decided: in the MVP).** Three modes: SETUP, FULL, READ_ONLY (FR-LIC-01, FR-LIC-03, SM-3). In SETUP the read-only reason is NONE. Correct FRD v3 section 2.2, which still says "simplified FULL and READ_ONLY".
3. **Tokens.** Sanctum deletes tokens by default. FR-GL-03 says tokens get `revoked_at` and are never deleted: add the column in a migration and name each token with the device id (AD-17 S4). Token lifetime is 30 days sliding.
4. **Audit entries for refusals.** Put the route, the answer code (401, 403, 409, 422, 423) and the reason in `after_state`. `entity_id` may be NULL (for example a failed login with an unknown user).
5. **Solver result JSON.** Suggested shapes for `solver_run.result_detail`: `{"binding_constraints":[{"constraint_type":"<code>","value":<number>}]}` when INFEASIBLE, and `{"error":"<message>","fallback":"FIFO"}` when the solver failed technically. A timeout is stored as `ERROR`. FIFO is only for technical failure; infeasible is final (FR-RC-06).
6. **Recommendation purpose.** `INITIAL` for normal generation, `DELAY_REPLAN` for a proposal built while the plan stays confirmed. The unique key only guards `INITIAL` rows, so also keep at most one pending delay re-plan proposal per request in code.
7. **Part 1 runs.** Write a `part1_run` row when the job is enqueued; retries up to 3, then FAILED (FR-EW-03). A run whose request is no longer CUSTOMS_APPROVED is discarded. A new run marks the earlier one REPLACED (`part1_run.status`).
8. **Duplicate BAPLIE.** The same hash for the same request is refused (409) unless the earlier copy is WITHDRAWN. The same file for another request is allowed.
9. **Notifications.** Re-send at T+10 and T+20 minutes after the first send; at T+30 escalate to the assigning officer and the department manager. Each retry and the escalation is its own `notification_log` row. The pre-execution reminder (default 2 hours) is separate and only sent while the task is still active. Delivery is never acknowledgement.
10. **Mobile events.** Client idempotency key is unique; duplicates return the previous result. In READ_ONLY, status updates and acknowledgements are applied and only creation events are rejected (there are none in the field app today). Clock skew: the mobile app sends `client_sent_at`; if `received_at - client_sent_at` is more than 5 minutes, set `clock_skew_suspect = 1`. The event is still applied; the flag only excludes it from evaluation. Staff resolution notes go to `audit_log.after_state`.
11. **No push service.** Web uses SSE; mobile uses a WebSocket event followed by a REST fetch, and catches up with `GET /api/v1/sync/changes?since=lastSyncToken`. Android keeps the WebSocket alive with a foreground service during an active task; iOS cannot.
12. **Queues.** One Redis queue per worker: `queue:part1`, `queue:part2`, `queue:edi`. Workers write results to MySQL, then Laravel pushes the update over SSE.
13. **Character set.** Save the SQL files as UTF-8; the comments in the current file contain broken characters.
14. **Yard occupancy.** In the MVP nothing writes `yard_slot.container_id` (FR-YD-04: accepting a placement reserves no slot; FR-YD-05: nothing comes back from the TOS). Decide whether the CT Administrator updates occupancy by hand, or whether it waits for the TOS integration (FW-08).
15. **Tug master shifts.** `tug_master_schedule` exists but no flow sets it. Mirror the pilot schedule screen when you build it.

## 7. Seed data to load

| Table | Rows |
|---|---|
| `status_type` | at least STARTED and COMPLETED for BERTH, PILOT, TUG, TERMINAL_OPS. **Without them every status update fails the foreign key.** COMPLETED is the UML state DONE |
| `constraint_type` | the limit types: wind, gust, visibility, wave, tide and under-keel clearance (exact codes: agree with the AI team) |
| `objective_term` | CP-SAT objective term codes (agree with the AI team) |
| `rationale_template` | the fixed, versioned rationale templates (no LLM text) |
| `pilot_rank_limit` | one row per rank: FIRST, SECOND, THIRD |
| `port_shift` | the fixed shifts |
| `port_setting` | reminder lead time, weather pull hours, rollback tolerance, delay thresholds per operation type, retraining interval |
| `imdg_class`, `container_size_type`, `vessel_type`, `cargo_type`, `storage_type`, `yard_zone_type`, `equipment_category`, `capability_type` | reference lists |
| `ml_model` | M2_SERVICE_TIME, M3_DEMAND_FORECAST, M4_DELAY_RISK, FORECAST_BIAS_CORRECTION |
| `external_system` | optional: the EGMSW row; it is reserved and unused (manual entry only) |
| SaaS `governorate` | the 11 seaport governorates (already seeded per the table comment) |

## 8. Where the diagrams and the SQL agree or differ

After the accepted changes, the class diagrams need these updates (I will write the patch prompt for the Pro account when you confirm Report B):

- New classes: `UserDevice`, `Part1Run`, `RecommendationReview`.
- New attributes: `active_from` and `active_to` on the role assignment; `purpose`, `source` on `Recommendation`; `result_detail` on `SolverRun`; `client_sent_at` and `clock_skew_suspect` on `ActualStatusUpdate`; reject-all fields on `EntryWindowProposal`; stale, provider rank, manual fields on `ForecastRun`; lifecycle, rationale, confidence on `TerminalRecommendation`; licence-state statuses; `on_hold` on `License`; `roles` on `SaasAdmin`; `live_period_key` and `active_port_key` helpers described as notes.
- Class changes with no SQL change: the PlanComponent derived states. Enumerations in the diagrams use the final SQL values (see the list at the top of section 1): parse status `PENDING, PARSED, FAILED, QUARANTINED`; recommendation status `PENDING_REVIEW, MODIFIED_BY_STAFF, CONFIRMED, REJECTED, INVALIDATED, REPLACED`; `ActualStatusUpdate` keeps `client_event_id` and `reported_at`.
- Fix F-01 (`TerminalRecommendation` multiplicity 0..3), F-02 (DataSource wording), F-04 (8 SaaS plus 87 Port counts, now 8 plus 90).
