<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 787-810 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-27 · Tidal constants source, datum and reference

| | |
| --- | --- |
| Source | OD-13 · RSK-05 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-27 · Sprint 4 |
| Affects | AI-021 · AI-042 · P2-F5 |
| Decision | ______ |

<div dir="rtl">

**القصة.** عشان نحسب المد لازم ثوابت فلكية خاصة بالميناء. ولازم عمق الأرصفة والمد يتقاسوا من نفس المستوى المرجعي. لو المستويين مختلفين بمتر، ممكن نقول إن السفينة تعدّي وهي فعلًا هتلمس القاع.

</div>

**Topic.** A2 needs published constants for the reference port and a single datum shared with the depths.

**Options:**

1. Published constants for Damietta on LAT.

**Recommendation.** This option, with a published tide table as the validation reference and an error tolerance set in DEC-30.
