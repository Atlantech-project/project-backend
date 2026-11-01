<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 686-710 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-23 · Objective terms, weights and number of options

| | |
| --- | --- |
| Source | OD-07 |
| Owner | AI Team |
| Status | Closed |
| Closing task | DEC-23 · Sprint 1 |
| Affects | AI-009 · AI-055 · AI-057 |
| Decision | Closed (2026-10-27): objective terms `WAITING_TIME` and `RESOURCE_COST`, equal weights (1.0 / 1.0) stored as `port_settings` keys `objective_weight_waiting_time` and `objective_weight_resource`; each term normalised to [0, 1] per request before weighting (scaled ×1000 for CP-SAT). 1 to 3 options per request: options 2 and 3 only within `solver_option_equivalence_pct` (10) of option 1. Full text in CT-10 and CT-18. |

<div dir="rtl">

**القصة.** الـ solver محتاج يعرف "أحسن خطة" يعني إيه: أقل انتظار للسفن؟ ولا أعلى استغلال للأرصفة؟ ولا الاتنين؟ وبأي نسبة؟ وكمان يطلع للموظف كام اختيار؟

</div>

**Topic.** CP-SAT needs a clear objective.

**Options:**

1. Codes for waiting time and resource utilisation, and 3 options per request.
2. Lexicographic: time, then utilisation.

**Recommendation.** Option 1 with initial weights in the seed data (AI-009), adjustable later.
