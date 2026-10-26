<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 467-490 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-16 · C-03 · Yard placement

| | |
| --- | --- |
| Source | AI contract v1 · AIBD-006 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-16 · Sprint 1 |
| Affects | P6-P1 · AI-066 · CT-11 |
| Decision | ______ |

<div dir="rtl">

**القصة.** الخطة اتأكدت لسفينة حاويات، والـ Backend لازم يطلب من الـ AI توصية أماكن الحاويات في الساحة. محتاجين نتفق على شكل الطلب، ونتفق كمان إن الـ AI بيقترح بس ومش بيحجز أماكن بنفسه.

</div>

**Topic.** The yard recommendation message on `queue:part2`.

**Options:**

1. Payload: `terminal_recommendation_id` (in YARD_REC_REQUESTED) and `operational_plan_id`. The worker never reserves a slot.

**Recommendation.** Approve as proposed.
