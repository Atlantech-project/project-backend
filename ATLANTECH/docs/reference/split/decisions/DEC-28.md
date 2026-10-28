<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 809-832 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-28 · Definition of moves

| | |
| --- | --- |
| Source | OD-14 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-28 · Sprint 3 |
| Affects | AI-052 |
| Decision | ______ |

<div dir="rtl">

**القصة.** زمن خدمة السفينة بيتحسب من عدد حركات الونش. ملف الـ BAPLIE فيه الحاويات اللي هتنزل بس، ومش فيه اللي هتطلع. لازم نتفق هنعرف العدد الكلي إزاي.

</div>

**Topic.** Service time (B3) needs the number of moves.

**Options:**

1. Discharge from the BAPLIE + loading count entered or estimated.

**Recommendation.** This option. Loading count is an optional request field; if empty it is estimated equal to discharge.
