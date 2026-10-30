<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 180-204 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-05 · SSE source for the dashboard

| | |
| --- | --- |
| Source | D-06 |
| Owner | Backend Team |
| Status | Closed |
| Closing task | DEC-05 · Sprint 1 |
| Affects | P3-I4 · FE-033 · CT-05 |
| Decision | Closed (2026-10-30): Option 1. A Laravel streamed endpoint, `GET /api/v1/events`, filtered by role and terminal, with `Last-Event-ID` catch-up from a short-lived Redis store. Reverb for mobile only. Risk for P3-I4: each open browser holds a PHP worker, so limit the connection time and size the worker pool. |

<div dir="rtl">

**القصة.** الـ dashboard لازم يتحدث لحظيًا من غير refresh. محدش اتفق الأحداث دي بتطلع منين وبأي شكل. فالـ Frontend مش هيعرف يبني الـ SSE client، والـ Backend مش هيعرف يبني الـ endpoint.

</div>

**Topic.** The AI documents do not say what feeds the SSE.

**Options:**

1. A Laravel streamed endpoint reading from a short-lived events store (Redis); Reverb for mobile only.
2. Reverb for both web and mobile.

**Recommendation.** Option 1 (Backend position): `GET /api/v1/events` filtered by role and terminal, with `Last-Event-ID` catch-up.
