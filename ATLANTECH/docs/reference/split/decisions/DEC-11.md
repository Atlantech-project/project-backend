<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 371-396 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-11 · Redis persistence

| | |
| --- | --- |
| Source | AIBD-029 |
| Owner | Backend Team |
| Status | Closed |
| Closing task | DEC-11 · Sprint 3 |
| Affects | P3-I2 · P6-R6 |
| Decision | Closed (2026-10-31): Option 1. AOF enabled with `appendfsync everysec`, and the P3-I2 sweeper as the safety net. The SSE catch-up store (CT-05) stays short-lived: a client whose events are lost gets `RESYNC`. |

<div dir="rtl">

**القصة.** السيرفر عمل restart، والـ Redis كان فيه 5 jobs لسه ماتنفذتش. من غير persistence الـ jobs دي هتضيع، والطلبات هتفضل "مستنية" على الشاشة ومحدش هيحسبها.

</div>

**Topic.** If Redis goes down, queued jobs may be lost. Persistence is not documented.

**Options:**

1. AOF enabled (`appendfsync everysec`).
2. RDB snapshots only.
3. No persistence; rely on the sweeper that re-queues QUEUED runs.

**Recommendation.** Option 1, with the sweeper (P3-I2) as a safety net.
