<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 964-988 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-34 · Building A4 rules on synthetic data

| | |
| --- | --- |
| Source | Plan decision |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-34 · Sprint 3 |
| Affects | AI-044 · AI-051 |
| Decision | ______ |

<div dir="rtl">

**القصة.** الـ Part 2 (أهم جزء) محتاج قواعد الأمان بتاعة الـ Part 1 عشان يبدأ. لو استنينا الـ Part 1 يخلص كله بالداتا الحقيقية، الـ Part 2 هيتأخر كذا sprint. الحل إننا نبني القواعد على داتا تجريبية الأول.

</div>

**Topic.** The documented critical path is AI-040 → 041 → 043 → 044, but Part 2 needs AI-044 early.

**Options:**

1. A4 is built and tested on synthetic series; the real surge feed joins later.
2. Wait for the full Part 1 chain.

**Recommendation.** Option 1, so Part 2 starts CP-SAT in Sprint 4.
