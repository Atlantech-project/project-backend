<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 524-547 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-17 · C-04 · BAPLIE parse

| | |
| --- | --- |
| Source | AI contract v1 · AIBD-007 |
| Owner | AI Team |
| Status | Closed |
| Closing task | DEC-17 · Sprint 1 |
| Affects | P3-H3 · AI-030 · AI-033 · CT-12 |
| Decision | Approved (2026-10-27), full text in CT-12 v1.0: the Backend proposal accepted by the AI Team. Payload `baplie_file_id` + `source` (FILE / STAGED_PAYLOAD); new `baplie_parse_runs` table (CT-17); outcomes PARSED / QUARANTINED / REJECTED plus FAILED runs for technical errors; the worker writes `parse_status` and `staged_payload` only, the Backend sets ACTIVE / REPLACED on C-06; corrections are re-validated and committed by the worker. Supported versions: SMDG BAPLIE 2.2.1 (D.95B) and 3.1.1 (D.13B); anything else is REJECTED. NFR-PERF-2 (BAPLIE parse time by file size) is **removed from the MVP**, requested by the AI Team and approved by the Leads Council on 2026-10-27; QA runs no BAPLIE performance tests (CT-24). |

<div dir="rtl">

**القصة.** المطلوب إن تحليل الملف ياخد 3 أو 10 أو 25 ثانية حسب "حجمه"، بس محدش عرّف يعني إيه ملف صغير أو كبير. فمش هنعرف نختبر الأداء ولا نقول إنه ناجح.

</div>

**Topic.** The BAPLIE parsing message on `queue:edi`.

**Options:**

1. Payload: `baplie_file_id` only; the worker reads the rest from the row. The NFR-PERF-2 size classes (3 / 10 / 25 seconds) need size definitions.

**Recommendation.** Approve. Size classes (proposed): small up to 500 containers, medium up to 5,000, large up to 20,000.
