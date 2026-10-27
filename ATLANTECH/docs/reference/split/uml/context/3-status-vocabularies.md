<!-- Generated from docs/reference/uml/UML_Context.md lines 49-69 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## 3. Status vocabularies

All statuses are written in capitals exactly as stored.

| Object | Values |
|---|---|
| License (central) | `PENDING`, `ACTIVE`, `SUSPENDED`, `EXPIRED`, `REVOKED`. At most one `ACTIVE` license per port. A `PENDING` renewal can be `on_hold` while the current license is suspended. |
| Port lifecycle (central) | `ONBOARDING`, `ACTIVE`, `SUSPENDED`, `OFFBOARDED` (final). |
| Port-side license mode | `SETUP` (port `ONBOARDING`), `FULL` (port `ACTIVE`, license `ACTIVE`, token valid), `READ_ONLY` (any other case, always with a reason). |
| Signing key | `PREPARED`, active, retired. |
| Billing transaction | `PENDING`, `FAILED`, `PAID`, `REFUNDED`, `CANCELLED`. `PENDING`, `FAILED` and `PAID` are "live" for a license period. |
| Berthing request | `SUBMITTED`, `NEEDS_REVISION`, `PRELIMINARY_APPROVED`, `CUSTOMS_APPROVED`, `ENTRY_WINDOW_CONFIRMED`, `RECOMMENDATION_GENERATED`, `PLAN_CONFIRMED`, `PLAN_CANCELLED`, `COMPLETED`, `REJECTED`, `CANCELLED`. |
| Outcomes (not states) | Part 1 run: `WINDOWS_FOUND`, `NO_FEASIBLE_WINDOW` (with the limiting factor), `ENV_DATA_UNAVAILABLE` (no usable environmental data). Part 2: `NO_FEASIBLE_PLAN` (nothing passes the hard constraints). |
| Recommendation version | `PENDING_REVIEW` (source `AI` or `FIFO`), `MODIFIED_BY_STAFF` (override), `CONFIRMED` (its plan is confirmed), `REPLACED` (replaced by a newer version). A version has purpose `INITIAL` or `DELAY_REPLAN`. |
| Operational plan | `CONFIRMED`, `COMPLETED`, `CANCELLED`; inside `CONFIRMED` three regions: pilot and tug (`UNASSIGNED` → `ASSIGNED` → `STARTED` → `DONE`) and yard (`YARD_PENDING` → `YARD_ACCEPTED` → `YARD_DONE`, or `YARD_NOT_APPLICABLE`). Acknowledged is read from the notification, not a state. |
| Yard recommendation | `YARD_REC_REQUESTED`, `YARD_REC_GENERATED`, `GENERATION_FAILED`, `YARD_REC_ACCEPTED`, `YARD_REC_REJECTED`, `EXPORTED_TO_TOS`. |
| BAPLIE file | file `STAGED`, `ACTIVE`, `REPLACED`, `WITHDRAWN`; `parse_status` `PENDING`, `PARSED`, `QUARANTINED` (payload kept as staged data) or `FAILED` (file cannot be read, no staged data). |
| Outbox event (phone) | `PENDING`, `SENDING`, `DONE`, `REJECTED`; `DONE` and `REJECTED` are purged after 30 days. |
| Data quality flags | environmental data `data_stale`, `pull_type` `MANUAL`; field event `clock_skew_suspect`; a record is trusted when `clock_skew_suspect` = 0. |
| Device | enrolled, revoked, `WIPE_PENDING`. |
| Audit entry types | `ADMIN` (human admin action), `SYSTEM` (scheduled job), `SAFETY_EXCEPTION`. |
