<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1299-1322 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-47 · Backend environment for development and testing

| | |
| --- | --- |
| Source | FE-OQ-29 |
| Owner | Backend Team |
| Status | Open |
| Closing task | DEC-47 · Sprint 2 |
| Affects | FE-101 · MB-F4B · QA-01 |
| Decision | ______ |

<div dir="rtl">

**القصة.** الـ Frontend والـ Mobile عايزين يجربوا على API حقيقي، بس الـ Backend شغال على جهازه بس. من غير سيرفر مشترك بيتحدث أول بأول، كل تيم هيختبر على حاجة مختلفة.

</div>

**Topic.** Frontend, Mobile and QA need a server with the latest develop and seed data.

**Options:**

1. A dev server updated on every merge to develop, and a separate demo updated on every release.

**Recommendation.** This option.
