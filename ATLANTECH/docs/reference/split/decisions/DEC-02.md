<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 100-124 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-02 · Who writes status transitions, history, audit and run rows

| | |
| --- | --- |
| Source | D-02 · OD-02 · AIBD-011 · AIBD-013 · AIBD-025 |
| Owner | Backend Team |
| Status | Open |
| Closing task | DEC-02 · Sprint 1 |
| Affects | P3-I2 · P4-K1 · AI-058 |
| Decision | ______ |

<div dir="rtl">

**القصة.** لو الـ AI والـ Backend الاتنين بيغيّروا حالة الطلب، ممكن الاتنين يكتبوا في نفس اللحظة والحالة تبوظ، أو الـ history يتسجل مرتين، أو يتسجل من غير audit. لازم يبقى فيه "صاحب" واحد بس لحالة الطلب.

</div>

**Topic.** Request status (SM-4), its history and its audit need a single writer. `solver_run.solver_version` is mandatory and only the worker knows it.

**Options:**

1. The Backend writes every request transition, history row and audit entry, and creates the run row as QUEUED before enqueueing. The AI writes run progress and result tables only.
2. The AI writes everything in its result transaction.

**Recommendation.** Option 1 (agreed). The Backend writes `solver_version` from `port_setting`; the worker overwrites it if different.
