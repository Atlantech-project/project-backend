<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 851-875 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-31 · Part 3 scheduling host

| | |
| --- | --- |
| Source | OD-17 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-31 · Sprint 6 |
| Affects | AI-073 |
| Decision | ______ |

<div dir="rtl">

**القصة.** الـ Part 3 بيقيّم أداء الـ AI بشكل دوري، بس مفيش حد مسؤول يشغّله، ومفيش queue خاصة بيه. لازم نحدد مين اللي بيقول له "يلا اشتغل".

</div>

**Topic.** There is no `queue:part3`.

**Options:**

1. A scheduler inside the Part 3 worker.
2. The Laravel scheduler enqueues (needs a new queue).

**Recommendation.** Option 1.
