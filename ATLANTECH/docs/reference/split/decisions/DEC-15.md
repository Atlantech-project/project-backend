<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 477-500 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-15 · C-02 · Part 2 solve

| | |
| --- | --- |
| Source | AI contract v1 · AIBD-002 to 005 |
| Owner | AI Team |
| Status | Closed |
| Closing task | DEC-15 · Sprint 1 |
| Affects | P4-J3 · P4-K3 · P4-K5 · P5-O7 · AI-058 · CT-10 |
| Decision | Approved (2026-10-26), full text in CT-10 v1.0. Payload-only schema referenced from the envelope. Only one `PART2_SOLVE` QUEUED or RUNNING at a time; triggers arriving meanwhile are merged, so the payload carries a `triggers` list (each entry: `trigger_type`, `berthing_request_ids` min 1, plus `changed_resources` / `operational_plan_id` / `requested_by_user_id` per type, optional `context_note` ≤ 500 for logs only). `payload.trigger_type` = highest precedence (REPLAN > RESOURCE_CHANGE > MANUAL > NEW_REQUEST). `purpose` removed from the payload and derived per request by the AI. `result_detail` is per request, with run outcomes SOLVED / PARTIALLY_SOLVED / NO_FEASIBLE_PLAN / FIFO_FALLBACK / GENERATION_FAILED, a closed STRUCTURAL constraint list, and `failure_reason` only when status = ERROR. |

<div dir="rtl">

**القصة.** نفس الـ worker بيتطلب منه 4 حاجات مختلفة: توصية جديدة، إعادة توليد، رصيف اتعطل، أو سفينة اتأخرت. كل حالة ليها بيانات مختلفة، ولو الرسالة مش واضحة الـ AI مش هيعرف هو بيحل مشكلة إيه بالظبط.

</div>

**Topic.** The message for solve, regenerate, resource change and delay re-plan on `queue:part2`.

**Options:**

1. Payload: `trigger_type` (NEW_REQUEST / MANUAL / RESOURCE_CHANGE / REPLAN), `berthing_request_ids`, `purpose` (INITIAL / DELAY_REPLAN), `changed_resource`, `operational_plan_id`, `context_note`; plus the `result_detail` shape with the outcome and binding constraints.

**Recommendation.** Approve as proposed. `context_note` is stored in logs only.
