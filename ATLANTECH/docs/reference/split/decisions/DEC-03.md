<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 127-152 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-03 · Location of the shared validation rule sets (override, yard adjustment, BAPLIE correction, window re-check)

| | |
| --- | --- |
| Source | D-03 · OD-03 · AIBD-008 · AIBD-034 |
| Owner | AI Team |
| Status | Closed |
| Closing task | DEC-03 · Sprint 1 |
| Affects | P4-K4 · P6-P3 · P3-H6 · AI-060 · AI-067 · AI-F03 · CT-13 |
| Decision | Closed (2026-10-27), full text in CT-13 v1.0. Option 1 (async on a worker), since FastAPI is cancelled (DEC-10): override and yard adjustment on `queue:validate` (`w_validate`, SELECT only); BAPLIE correction on `queue:edi` with `source = STAGED_PAYLOAD`; window re-check stays in Laravel. Violations are warnings (override with a reason, audited as SAFETY_EXCEPTION); no result within `validation_timeout_seconds` → change refused (fail closed). |

<div dir="rtl">

**القصة.** الـ AI اقترح الرصيف B12 للسفينة. الموظف عمل override وغيّره لـ B7، بس B7 أقل عمق من الغاطس بتاع السفينة. القاعدة اللي بتمنع ده مكتوبة في كود الـ AI بس. لو الـ Backend كتب نسخة تانية من القاعدة، النسختين ممكن يختلفوا مع الوقت. ولو سأل الـ AI في الخلفية، الموظف هيستنى. لازم نحدد القواعد دي هتعيش فين.

</div>

**Topic.** The same hard rules must apply in two places: when the AI generates a result and when a user edits it. The teams disagree: the AI wants a background job, the Backend wants the check inside Laravel.

**Options:**

1. Async job on the worker (C-05): one Python implementation; the user waits for the result over SSE.
2. A second copy of the rules in Laravel with shared test vectors: instant answer, but two codebases that must stay identical.
3. A synchronous endpoint on the AI FastAPI service: Laravel calls it and waits; the rules are written once in Python.

**Recommendation.** Option 3 for override and yard adjustment: an instant answer (422 with the broken constraint) and a single implementation. BAPLIE correction stays async on `queue:edi` because it is heavy. If FastAPI is unreachable the Backend refuses the change (fail closed). The window re-check stays in Laravel: it confirms the proposal is the latest and the forecast run is still the newest.
