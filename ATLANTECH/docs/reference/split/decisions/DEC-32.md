<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 914-937 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-32 · Availability semantics for pilots and tug masters

| | |
| --- | --- |
| Source | OD-19 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-32 · Sprint 3 |
| Affects | AI-050 · P5-M1 |
| Decision | ______ |

<div dir="rtl">

**القصة.** المرشد ده "متاح"؟ هو في ورديته، بس اتقترح لسفينة تانية في نفس الوقت ولسه محدش أكد. لو الـ AI والـ Backend عرّفوا "متاح" بشكلين مختلفين، هنحجز نفس المرشد لسفينتين.

</div>

**Topic.** B2 and B5 need a definition of "available".

**Options:**

1. A pilot is on shift and not proposed or assigned in an overlapping window. A tug is available when its status is available and its master is on shift.

**Recommendation.** This option.
