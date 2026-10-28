<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 605-629 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-20 · Tug-count formula

| | |
| --- | --- |
| Source | OD-04 · RSK-01 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-20 · Sprint 2 |
| Affects | AI-054 · AI-055 |
| Decision | ______ |

<div dir="rtl">

**القصة.** سفينة طولها 300 متر داخلة الميناء. محتاجة كام قاطرة؟ 2 ولا 3 ولا 4؟ القاعدة دي بيقررها خبراء القطر مش المبرمجين، ولحد دلوقتي محدش ادّاها لنا. من غيرها الـ solver مش هيعرف يحجز قاطرات.

</div>

**Topic.** The number of tugs per vessel (from GT, DWT and tug power) is not defined, and this blocks CP-SAT.

**Options:**

1. Input from the Tug Manager / Marine Safety Officer.
2. A provisional table by vessel length until the input arrives.

**Recommendation.** Request the formula formally in Sprint 1. If it does not arrive, the provisional table is marked "unvalidated" in the rationale and can be replaced without code changes (pluggable rule).
