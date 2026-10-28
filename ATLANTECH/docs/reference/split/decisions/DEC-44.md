<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1222-1246 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-44 · Storing the clock-skew review note

| | |
| --- | --- |
| Source | FE-OQ-31 |
| Owner | Backend Team |
| Status | Open |
| Closing task | DEC-44 · Sprint 6 |
| Affects | P5-O5 · FE-065 |
| Decision | ______ |

<div dir="rtl">

**القصة.** ساعة التابلت كانت غلط، فالحدث وصل بوقت مش منطقي واتعلّم "مشكوك فيه". الموظف عايز يكتب ملاحظة إنه راجعه، بس مفيش مكان يتحفظ فيه الكلام ده.

</div>

**Topic.** P-41 shows a Clock-skew suspect flag; where a review note is stored is unclear.

**Options:**

1. Show the flag only, no note.
2. A note field on `actual_status_updates`.

**Recommendation.** Option 1 for the MVP.
