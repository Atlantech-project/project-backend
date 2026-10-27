<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 449-472 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-14 · C-01 · Part 1 run

| | |
| --- | --- |
| Source | AI contract v1 · AIBD-001 |
| Owner | AI Team |
| Status | Closed |
| Closing task | DEC-14 · Sprint 1 |
| Affects | P3-I7 · P4-J3 · AI-048 · CT-09 |
| Decision | Approved (2026-10-26). Payload schema as proposed by the AI Team: `berthing_request_id` (integer ≥ 1), `reason` (CUSTOMS_APPROVAL / RECOMPUTE / MANUAL_DATA / REOPEN), `manual_forecast_run_id` required only when MANUAL_DATA and forbidden otherwise; `additionalProperties: false`. Backend validates every message against the schema before RPUSH and sends `triggered_by_user_id = null` for CUSTOMS_APPROVAL, the acting user otherwise. Open question to the AI Team: confirm the schema covers `payload` only and is referenced from `envelope.schema.json` (CT-08) when `job_type = PART1_RUN`. |

<div dir="rtl">

**القصة.** بعد موافقة الجمارك، الـ Backend بيبعت للـ AI رسالة "احسب نوافذ الدخول للطلب ده". لو الحقول وأسباب الإعادة مش متفق عليها، الـ AI ممكن يرفض الرسالة أو يحسب غلط. ومفيش حد محدد للـ run ياخد قد إيه قبل ما نعتبره فشل.

</div>

**Topic.** The message that starts the entry-window computation for one request on `queue:part1`.

**Options:**

1. Payload: `berthing_request_id`, `reason` (CUSTOMS_APPROVAL / RECOMPUTE / MANUAL_DATA / REOPEN), `manual_forecast_run_id` when MANUAL_DATA.

**Recommendation.** Approve the proposed payload. The timeout is not documented: proposed `part1_run_timeout_seconds = 120` in `port_settings`.
