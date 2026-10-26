<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 649-673 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-23 · Objective terms, weights and number of options

| | |
| --- | --- |
| Source | OD-07 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-23 · Sprint 1 |
| Affects | AI-009 · AI-055 · AI-057 |
| Decision | ______ |

<div dir="rtl">

**القصة.** الـ solver محتاج يعرف "أحسن خطة" يعني إيه: أقل انتظار للسفن؟ ولا أعلى استغلال للأرصفة؟ ولا الاتنين؟ وبأي نسبة؟ وكمان يطلع للموظف كام اختيار؟

</div>

**Topic.** CP-SAT needs a clear objective.

**Options:**

1. Codes for waiting time and resource utilisation, and 3 options per request.
2. Lexicographic: time, then utilisation.

**Recommendation.** Option 1 with initial weights in the seed data (AI-009), adjustable later.
