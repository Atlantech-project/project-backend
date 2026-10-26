<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 492-515 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-17 · C-04 · BAPLIE parse

| | |
| --- | --- |
| Source | AI contract v1 · AIBD-007 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-17 · Sprint 1 |
| Affects | P3-H3 · AI-030 · AI-033 · CT-12 |
| Decision | ______ |

<div dir="rtl">

**القصة.** المطلوب إن تحليل الملف ياخد 3 أو 10 أو 25 ثانية حسب "حجمه"، بس محدش عرّف يعني إيه ملف صغير أو كبير. فمش هنعرف نختبر الأداء ولا نقول إنه ناجح.

</div>

**Topic.** The BAPLIE parsing message on `queue:edi`.

**Options:**

1. Payload: `baplie_file_id` only; the worker reads the rest from the row. The NFR-PERF-2 size classes (3 / 10 / 25 seconds) need size definitions.

**Recommendation.** Approve. Size classes (proposed): small up to 500 containers, medium up to 5,000, large up to 20,000.
