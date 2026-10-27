<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 549-573 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-18 · C-05 · Validation

| | |
| --- | --- |
| Source | AI contract v1 · AIBD-008 |
| Owner | AI Team |
| Status | Closed |
| Closing task | DEC-18 · Sprint 1 |
| Affects | AI-F03 · P4-K4 · P6-P3 · CT-13 |
| Decision | Closed (2026-10-27), full text in CT-13 v1.0. Async variant: `VALIDATION` job with `validation_type` (OVERRIDE / YARD_ADJUSTMENT), `subject_id`, `proposed_change`; `run_id = null`, correlation by `job_id`; result `validation_result` returned in C-06. |

<div dir="rtl">

**القصة.** لما الموظف يعدّل الرصيف، الـ Backend هيسأل الـ AI "التعديل ده سليم؟". لو شكل السؤال والإجابة مش متفق عليهم، رسالة الخطأ اللي هتوصل للموظف مش هتقول هو كسر أنهي قيد.

</div>

**Topic.** The shape of the validation request and its result. Depends on DEC-03 and DEC-10.

**Options:**

1. If DEC-03 = FastAPI: `POST /internal/v1/validate` with `validation_type` (OVERRIDE / YARD_ADJUSTMENT), `subject_id`, `proposed_change`; the answer `{valid, violations[]}` comes back immediately.
2. If async: the same fields plus `request_token`, with the result returned through C-06.

**Recommendation.** Option 1 for override and yard adjustment; BAPLIE correction async on `queue:edi` with the same fields.
