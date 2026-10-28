<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 317-341 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-09 · Where deviation thresholds live

| | |
| --- | --- |
| Source | D-10 · OD-16 (part) · AIBD-020 |
| Owner | Backend Team |
| Status | Open |
| Closing task | DEC-09 · Sprint 6 |
| Affects | P5-O6 · P5-O7 |
| Decision | ______ |

<div dir="rtl">

**القصة.** القاطرة اتأخرت 10 دقايق. هل ده تأخير مهم يستاهل إعادة تخطيط، ولا عادي؟ الحد ده لازم الـ Port Admin يغيّره، بس مفيش مكان في الـ database نحفظه فيه.

</div>

**Topic.** The thresholds that make a delay "significant" per operation type have no place in the schema.

**Options:**

1. `port_settings` keys per component type.
2. A new table.

**Recommendation.** Option 1 (agreed). Edited from P-62.
