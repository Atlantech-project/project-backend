<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 424-448 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-13 · Who keeps the OpenAPI file current

| | |
| --- | --- |
| Source | Backend open inputs |
| Owner | Backend Team |
| Status | Closed |
| Closing task | DEC-13 · Sprint 1 |
| Affects | P1-A4 · CT-02 |
| Decision | Closed (2026-10-29): Option 1. The Backend Team is the single owner of the OpenAPI file (CT-02). Its source is project-backend `ATLANTECH/openapi/port-api.yaml`, published unchanged to PortHub `contracts/openapi/port-api.yaml` with `composer contracts:openapi`. Every backend PR that adds or changes an endpoint updates the file in the same PR, opens the paired PortHub PR, and the backend drift test and the Spectral CI check fail otherwise. The API readiness pack (CT-23) links the OpenAPI diff. Decided by Marwan (Backend lead) in P1-A4. |

<div dir="rtl">

**القصة.** الـ Backend غيّر اسم حقل في الـ API ونسي يحدّث الـ OpenAPI. الـ Frontend بنى الشاشة على الاسم القديم، والشاشة وقعت في الـ demo. لازم يبقى فيه صاحب واحد للملف ده وقاعدة إنه يتحدث مع كل تغيير.

</div>

**Topic.** Frontend and Mobile build against the OpenAPI file, so it must stay up to date.

**Options:**

1. The Backend Team is the single owner; any PR that changes an endpoint updates the file in the same PR.
2. Each team updates its own part.

**Recommendation.** Option 1, with an OpenAPI diff in the API readiness pack (CT-23).
