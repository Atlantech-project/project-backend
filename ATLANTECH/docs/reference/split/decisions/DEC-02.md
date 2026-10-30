<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 101-125 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-02 · Who writes status transitions, history, audit and run rows

| | |
| --- | --- |
| Source | D-02 · OD-02 · AIBD-011 · AIBD-013 · AIBD-025 |
| Owner | Backend Team |
| Status | Closed |
| Closing task | DEC-02 · Sprint 1 |
| Affects | P3-I2 · P4-K1 · AI-058 |
| Decision | Closed (2026-10-27), full text in CT-15 v1.0. Option 1: the Backend creates run rows, writes every request transition, history row and audit entry, and closes old rows (REPLACED, ACTIVE, CONFIRMED, REJECTED); workers insert new rows and complete only their own run (guarded by `status = 'RUNNING'`). `solver_version` is set by the Backend from `port_settings` and overwritten by the worker if different. The yard worker's `lifecycle_status` write is limited to GENERATED / GENERATION_FAILED. |

<div dir="rtl">

**القصة.** لو الـ AI والـ Backend الاتنين بيغيّروا حالة الطلب، ممكن الاتنين يكتبوا في نفس اللحظة والحالة تبوظ، أو الـ history يتسجل مرتين، أو يتسجل من غير audit. لازم يبقى فيه "صاحب" واحد بس لحالة الطلب.

</div>

**Topic.** Request status (SM-4), its history and its audit need a single writer. `solver_runs.solver_version` is mandatory and only the worker knows it.

**Options:**

1. The Backend writes every request transition, history row and audit entry, and creates the run row as QUEUED before enqueueing. The AI writes run progress and result tables only.
2. The AI writes everything in its result transaction.

**Recommendation.** Option 1 (agreed). The Backend writes `solver_version` from `port_settings`; the worker overwrites it if different.
