<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 832-856 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-29 · Yard recommendation method, objective and confidence

| | |
| --- | --- |
| Source | OD-15 · RSK-09 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-29 · Sprint 3 |
| Affects | AI-065 · AI-066 |
| Decision | ______ |

<div dir="rtl">

**القصة.** الـ AI المفروض يقترح الحاويات تتحط فين في الساحة، بس محدش حدد يعمل ده إزاي. هل بقواعد بسيطة زي "المبرّد جنب الكهربا"؟ ولا بموديل optimization؟ وإيه اللي يخلّينا "واثقين" في الاقتراح؟

</div>

**Topic.** The B8 method is not defined.

**Options:**

1. Option A: rule-based heuristic (group by movement, type and weight, then compatible blocks).
2. Option B: CP-SAT assignment (needs an objective that is not documented).

**Recommendation.** Option A; confidence = share of containers placed in a zone of their preferred type.
