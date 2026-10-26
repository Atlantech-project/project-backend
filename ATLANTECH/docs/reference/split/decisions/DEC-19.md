<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 543-566 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-19 · C-06 · Completion message

| | |
| --- | --- |
| Source | AI contract v1 · AIBD-010 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-19 · Sprint 1 |
| Affects | P3-I3 · AI-011 · CT-14 |
| Decision | ______ |

<div dir="rtl">

**القصة.** لما الـ AI يخلص، رسالة "خلصت" لازم تقول خلص إيه، ونجح ولا فشل، وليه. من غير كده الـ Backend مش هيعرف يغيّر حالة الطلب صح ولا يبعت الإشعار المناسب.

</div>

**Topic.** The shape of the completion message the worker sends to the Backend.

**Options:**

1. `{contract_version, job_type, run_id, status, outcome, error_category, finished_at}`; Part 3 uses it for DRIFT_ALERT.

**Recommendation.** Approve; the list name is `queue:ai-results`.
