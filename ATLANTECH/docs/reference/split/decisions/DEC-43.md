<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1199-1223 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-43 · Recommendation confidence field on P-38

| | |
| --- | --- |
| Source | FE-OQ-11 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-43 · Sprint 6 |
| Affects | FE-059 |
| Decision | ______ |

<div dir="rtl">

**القصة.** الـ Frontend عايز يعرض "درجة الثقة" في التوصية، بس مفيش حقل زي ده في الـ database، والـ AI مش بيحسبه. يا نشيله من الشاشة، يا نضيفه في كل الطبقات.

</div>

**Topic.** There is no stored confidence field for a recommendation.

**Options:**

1. Not shown.
2. Add a field.

**Recommendation.** Option 1: P-38 shows the objective score and rationale only.
