<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 205-229 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-06 · Joint solve scope: which requests may change per trigger

| | |
| --- | --- |
| Source | D-07 · OD-08 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-06 · Sprint 1 |
| Affects | AI-050 · AI-055 · P4-K1 · P4-K5 |
| Decision | ______ |

<div dir="rtl">

**القصة.** سفينة جديدة وصلت والـ AI هيحسب لها مكان. هل مسموح يحرّك السفن التانية اللي لسه توصياتها مستنية موافقة؟ ولا يحطها بس في الأماكن الفاضية؟ ولو حرّك سفينة، التوصية القديمة بتاعتها تتلغي؟

</div>

**Topic.** CP-SAT solves for the whole port, so we must define what is fixed and what is variable.

**Options:**

1. Confirmed plans are fixed; pending requests and the trigger request are variable.
2. Only the trigger request is variable.

**Recommendation.** Option 1 (agreed). It defines which pending versions become REPLACED.
