<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 343-367 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-10 · Scope of the AI FastAPI service

| | |
| --- | --- |
| Source | New decision |
| Owner | AI Team |
| Status | Closed |
| Closing task | DEC-10 · Sprint 1 |
| Affects | AI-F01 · AI-F02 · AI-F03 · CT-16 |
| Decision | Closed (2026-10-27): FastAPI is cancelled. Laravel and Python talk only through Redis and MySQL. Validation moves to `queue:validate` (CT-13); health moves to Redis heartbeat keys `worker:{name}:heartbeat` (CT-16). |

<div dir="rtl">

**القصة.** اتقرر إن الـ AI هيبقى ليه APIs بـ FastAPI، بس كل المستندات مبنية على إن الكلام بين الـ Backend والـ AI عن طريق Redis بس. لو ماحددناش الـ FastAPI مسؤول عن إيه، كل تيم هيفترض حاجة. ممكن نلاقي نفس الشغل متعمل مرتين، أو حل CP-SAT واخد ثواني مستني جوه HTTP request.

</div>

**Topic.** The AI will expose APIs with FastAPI. The documents say Laravel and Python talk only through Redis, so the role of FastAPI must be set.

**Options:**

1. FastAPI is an internal API layer on top of the workers: health and readiness of each worker, synchronous validation (DEC-03), and run status for diagnosis. Heavy work (Part 1, Part 2, parsing, yard) stays on the queues.
2. FastAPI replaces the queues and all work becomes HTTP.

**Recommendation.** Option 1. FastAPI is internal to the port server and is not exposed to the web or mobile app. Laravel is its only client, authenticated with a service token header (`X-Service-Token`). FastAPI publishes its own OpenAPI file (CT-16).
