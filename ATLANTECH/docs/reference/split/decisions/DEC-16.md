<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 504-527 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-16 · C-03 · Yard placement

| | |
| --- | --- |
| Source | AI contract v1 · AIBD-006 |
| Owner | AI Team |
| Status | Closed |
| Closing task | DEC-16 · Sprint 1 |
| Affects | P6-P1 · AI-066 · CT-11 |
| Decision | Approved (2026-10-27), full text in CT-11 v1.0. Payload-only schema with `terminal_recommendation_id` and `operational_plan_id` (both required). Runs on a new `queue:yard` with its own worker. Run tracking in a new `yard_runs` table (Backend adds it through CT-17; `run_id = yard_runs.id`). Regeneration reuses the same `terminal_recommendations` row and marks older active runs REPLACED. Partial placement → `YARD_REC_GENERATED` with unplaced containers and reason codes in `yard_runs.result_detail`; none placed → `GENERATION_FAILED` / `INFEASIBLE`. `yard_slot_id` always NULL in v1; nothing is a reservation. `confidence` = preferred-zone share (DEC-29 Option A). |

<div dir="rtl">

**القصة.** الخطة اتأكدت لسفينة حاويات، والـ Backend لازم يطلب من الـ AI توصية أماكن الحاويات في الساحة. محتاجين نتفق على شكل الطلب، ونتفق كمان إن الـ AI بيقترح بس ومش بيحجز أماكن بنفسه.

</div>

**Topic.** The yard recommendation message on `queue:part2`.

**Options:**

1. Payload: `terminal_recommendation_id` (in YARD_REC_REQUESTED) and `operational_plan_id`. The worker never reserves a slot.

**Recommendation.** Approve as proposed.
