<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 575-598 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-19 · C-06 · Completion message

| | |
| --- | --- |
| Source | AI contract v1 · AIBD-010 |
| Owner | AI Team |
| Status | Closed |
| Closing task | DEC-19 · Sprint 1 |
| Affects | P3-I3 · AI-011 · CT-14 |
| Decision | Closed (2026-10-27), full text in CT-14 v1.0. Fields: `contract_version`, `job_type`, `job_id`, `run_id`, `status` (DONE / FAILED), `outcome`, `error_category`, `finished_at`, `worker_version`, plus one extra field per job type (`entry_window_proposal_id`, `terminal_recommendation_id`, `baplie_file_id`, `validation_result`, `drift_measurement_ids`). List name `queue:ai-results`. |

<div dir="rtl">

**القصة.** لما الـ AI يخلص، رسالة "خلصت" لازم تقول خلص إيه، ونجح ولا فشل، وليه. من غير كده الـ Backend مش هيعرف يغيّر حالة الطلب صح ولا يبعت الإشعار المناسب.

</div>

**Topic.** The shape of the completion message the worker sends to the Backend.

**Options:**

1. `{contract_version, job_type, run_id, status, outcome, error_category, finished_at}`; Part 3 uses it for DRIFT_ALERT.

**Recommendation.** Approve; the list name is `queue:ai-results`.
