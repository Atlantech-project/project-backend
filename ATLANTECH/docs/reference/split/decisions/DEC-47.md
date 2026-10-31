<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1301-1324 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-47 · Backend environment for development and testing

| | |
| --- | --- |
| Source | FE-OQ-29 |
| Owner | Backend Team |
| Status | Closed |
| Closing task | DEC-47 · Sprint 2 |
| Affects | FE-101 · MB-F4B · QA-01 |
| Decision | Closed (2026-10-29): Option 1 as the target. A shared dev server is updated on every merge to `develop`, and a separate demo on every release; the host and the deployment come with P6-R6 (or earlier if a host becomes available). Until then Frontend, Mobile and QA run the backend from `develop` on their own machine with `docker compose --profile server up -d --build` (app, nginx, MySQL 8.4, Redis 7.4; no local PHP needed; http://localhost:8000). Seed data arrives with P1-B6. Decided by Marwan (Backend lead). |

<div dir="rtl">

**القصة.** الـ Frontend والـ Mobile عايزين يجربوا على API حقيقي، بس الـ Backend شغال على جهازه بس. من غير سيرفر مشترك بيتحدث أول بأول، كل تيم هيختبر على حاجة مختلفة.

</div>

**Topic.** Frontend, Mobile and QA need a server with the latest develop and seed data.

**Options:**

1. A dev server updated on every merge to develop, and a separate demo updated on every release.

**Recommendation.** This option.
