<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 629-653 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-21 · Phase 1 delay-risk rule

| | |
| --- | --- |
| Source | OD-05 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-21 · Sprint 3 |
| Affects | AI-053 |
| Decision | ______ |

<div dir="rtl">

**القصة.** الشاشة المفروض تقول للموظف "الخطة دي احتمال تتأخر 30%". بس إحنا لسه مافيش عندنا تاريخ تأخيرات نتعلم منه. لازم نتفق على قاعدة بسيطة مؤقتة، أو نعرض "مش متقيّم".

</div>

**Topic.** B4 needs a rule to estimate delay probability before any history exists.

**Options:**

1. NULL until a rule is approved.
2. A simple rule from weather margin and port congestion.

**Recommendation.** A simple rule approved in Sprint 1 so B4 is fully delivered. If not approved, it writes NULL and P-38 shows "Not assessed".
