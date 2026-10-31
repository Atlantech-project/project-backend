<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 761-784 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-26 · Weather and marine providers

| | |
| --- | --- |
| Source | OD-12 · RSK-04 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-26 · Sprint 4 |
| Affects | AI-020 · AI-040 |
| Decision | ______ |

<div dir="rtl">

**القصة.** الـ Part 1 محتاج توقعات رياح وأمواج ورؤية لميناء دمياط. لسه ماختارناش هنجيبها منين، ولا اتأكدنا إن الرخصة تسمح نستخدمها، ولا لو المصدر الأساسي وقع هنعمل إيه.

</div>

**Topic.** A primary and a fallback are needed covering every A4 variable (wind, visibility, waves, current, sea level).

**Options:**

1. Open-Meteo (Forecast + Marine) as primary, and a second provider as fallback after comparing licence and coverage.

**Recommendation.** Compare 2 or 3 providers on variables, licence and coverage for Damietta, and pick two in Sprint 1.
