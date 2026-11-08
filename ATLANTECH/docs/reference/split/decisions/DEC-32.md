<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1206-1275 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-32 · Availability semantics for pilots and tug masters

| | |
| --- | --- |
| Source | OD-19 |
| Owner | AI Team |
| Status | Closed |
| Closing task | DEC-32 · Sprint 3 |
| Affects | AI-050 · P5-M1 |
| Decision | Closed (2026-11-05): Option 1, made precise. One definition of "available" for pilots and tugs, applied by both the AI (AI-050) and the Backend (P5-M1). A pilot or tug is busy when it is assigned in a confirmed plan, or proposed in option 1 of a `PENDING_REVIEW` or `MODIFIED_BY_STAFF` recommendation, over an overlapping operation interval. Only option 1 holds resources; confirming option 2 or 3 triggers a Backend re-check that refuses the confirmation on a conflict. Until the schema stores an operation interval per pilot and tug assignment, the option or plan window is used (it over-reserves). Full rule in "Decision detail" below. |
| Agreed with | Mostafa Mohamed Rashwan, Leads Council, 2026-11-05. Decided by the Leads Council; the AI Team (owner) is informed. |
| Implementation | `project-ai` AI-050 (port-state loader, done). Backend P4-L1 builds the re-check (Sprint 5) and P5-M1 uses it for the pilot or tug replacement (amended 2026-11-06). |

<div dir="rtl">

**القصة.** المرشد ده "متاح"؟ هو في ورديته، بس اتقترح لسفينة تانية في نفس الوقت ولسه محدش أكد. لو الـ AI والـ Backend عرّفوا "متاح" بشكلين مختلفين، هنحجز نفس المرشد لسفينتين.

</div>

**Topic.** B2 and B5 need a definition of "available".

**Options:**

1. A pilot is on shift and not proposed or assigned in an overlapping window. A tug is available when its status is available and its master is on shift.

**Recommendation.** This option.

**Decision detail.** Option 1, made precise so the AI and the Backend apply the same test.

**Rule (for an operation interval [start, end) of a pilotage or towage job):**

1. **Pilot available** when all hold:
   - (a) the user is active (`users.is_active`) and the active role is Pilot (`user_role_assignments.role` = `MARINE_PILOT`, `active_to` NULL; DEC-36);
   - (b) a shift (`pilot_schedules`, with the times of its `port_shifts` row) covers the whole interval; a job that crosses a shift change is not available;
   - (c) the pilot is not busy (rule 3).
2. **Tug available** when all hold:
   - (a) its status (`tugboats.status`) is not `MAINTENANCE` or `INACTIVE`;
   - (b) its tug master (`tug_master_profiles.assigned_tugboat_id`) is available by rule 1 for the same interval, using `tug_master_schedules` for the shift and the active role `TUG_MASTER`;
   - (c) the tug is not busy (rule 3).

   `BUSY` is a current-state flag set by the Tug Master; it is not used here. Busyness comes only from overlap (rule 3).
3. **Busy.** The pilot or tug appears, with an overlapping operation interval, in:
   - (a) a confirmed operational plan (`operational_plans.status` = `CONFIRMED`); or
   - (b) option 1 of a recommendation whose status is `PENDING_REVIEW` or `MODIFIED_BY_STAFF` (for `MODIFIED_BY_STAFF`, the assignment as modified).

   `INVALIDATED`, `REPLACED` and `REJECTED` recommendations, and options 2 and 3, hold nothing.
4. **Overlap** is measured on the operation interval of the assignment, not on the vessel's berth stay. Intervals are half-open, so back-to-back jobs do not overlap. **Interim source:** the schema does not yet store a separate operation interval per assignment, so the interval used is:
   - for the pilot and tugs of an option, `recommendation_options.planned_start` and `planned_end`;
   - for a confirmed plan, `plan_components.planned_start` and `planned_end` of its `PILOT` or `TUG` row (pilot: `assigned_user_id`; tug: `tugboat_id`).

   This interim interval is longer than the real job, so it over-reserves: it never double-books, but fewer pilots and tugs look free.

   For a confirmed plan's PILOT row, the pilot is `assigned_user_id` when set; before dispatch it is the selected option's pilot (`operational_plans.recommendation_option_id` -> `recommendation_options.pilot_user_id`). Confirmed by the Backend, 2026-11-06 (G-16; amended 2026-11-06).
5. **Same run.** Requests solved in the same run do not hold resources against each other: they are variables of the same solve (DEC-06). The previous `PENDING_REVIEW` or `MODIFIED_BY_STAFF` recommendation of a request in the run holds nothing either, since it becomes `REPLACED`.
6. **Confirming option 2 or 3.** The Backend re-checks the option's pilot and tugs with this rule, excluding the same recommendation's option 1, in the same step as the window re-check (DEC-03). On a conflict the confirmation is refused and names the busy resource; the user picks another option or requests regeneration (MANUAL). There is no override: a double-booked pilot or tug cannot be executed.
7. **Replacing a pilot or tug** (P5-M1) uses the same rule.
8. **One definition.** AI-050 and P5-M1 implement this text and run the same test vectors.

**Open items (follow-ups; they don't reopen the decision):**

1. Backend (G-15): one availability service implementing rules 1 to 4, built in P4-L1 (Sprint 5) and reused by P5-M1 and P5-M2; refusal code `RESOURCE_UNAVAILABLE`, HTTP 409, naming the resource type and id, not the other vessel (`error-codes.md` v1.4). Agreed 2026-11-06 (amended 2026-11-06).
2. Backend (G-14): nullable `pilot_start` / `pilot_end` on `recommendation_options` and `planned_start` / `planned_end` on `recommendation_option_tugs`; when NULL the option window applies, so rule 4 stays valid. P4-L1 copies the job interval into the PILOT and TUG `plan_components` rows. Agreed 2026-11-06 (amended 2026-11-06).
3. AI Team: until item 2 is done, watch for solves that fail on pilot or tug shortage caused by the over-reservation in rule 4.
4. AI Team: write the shared test vectors (overlap edges, shift change, same-run requests, option 2/3 conflict); the Backend runs them in P5-M1.
5. CT-10: add that a pending recommendation holds capacity through its option 1 only (rule 3). Today it says only that "every other pending recommendation is fixed and treated as occupied capacity".

**References:**

- DEC-06 (fixed and variable requests per solve) · DEC-03 (re-check in Laravel) · DEC-36 (one active role) · CT-10 (PortHub `contracts/ATLANTECH_Contracts.md`: line 481, tug master shift change sent as `TUG` (DEC-32); line 497, solve scope and REPLACED).
- Schema: `contracts/db/schema.sql` in PortHub and `docs/ref/db/schema.sql` in project-ai (same lines). `users.is_active` 1816 · `user_role_assignments.role` 1791, `active_to` 1795 · `pilot_schedules` 1011–1025 · `tug_master_schedules` 1733–1747 · `port_shifts.start_time` and `end_time` 1109–1110 · `tug_master_profiles.assigned_tugboat_id` 1722 · `tugboats.status` 1758 · `recommendations.status` 1265 · `recommendation_options.pilot_user_id` 1157, `planned_start` and `planned_end` 1155–1156 · `recommendation_option_tugs` 1137–1144 (no interval) · `operational_plans.status` 923 · `plan_components.planned_start` and `planned_end` 1040–1041.
