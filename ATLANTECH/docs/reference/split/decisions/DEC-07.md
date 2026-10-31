<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 233-257 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-07 · Storing a NO_FEASIBLE_WINDOW result

| | |
| --- | --- |
| Source | D-08 · OD-10 · PS-01 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-07 · Sprint 5 |
| Affects | P4-J1 · AI-048 |
| Decision | ______ |

<div dir="rtl">

**القصة.** الطقس وحش طول الأيام الجاية، والـ AI مالقاش أي نافذة دخول. لازم يسجل "مفيش نافذة" ومعاها الفترات الممنوعة وسببها. بس الـ schema مايسمحش بفترة ممنوعة من غير proposal.

</div>

**Topic.** `blocked_periods.proposal_id` is mandatory, so a proposal must exist even when no window exists.

**Options:**

1. A proposal with no windows, linked from the run (schema note change only).
2. An unlinked proposal found by request and time.

**Recommendation.** Option 1 (agreed).
