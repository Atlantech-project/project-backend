<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 73-98 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-01 · AI completion signal

| | |
| --- | --- |
| Source | D-01 · OD-01 · AIBD-010 |
| Owner | Backend Team |
| Status | Closed |
| Closing task | DEC-01 · Sprint 1 |
| Affects | P3-I3 · AI-011 · CT-14 |
| Decision | Closed (2026-10-27), full text in CT-14 v1.0. Option 1: after commit the worker RPUSHes C-06 to `queue:ai-results`; a supervised consumer (`php artisan ai:consume-results`, BLPOP) handles it; a poller every 30 s is the safety net (runs DONE/FAILED with `result_handled_at IS NULL`, and `drift_measurements` with `is_drifted = 1` and `notified_at IS NULL`). De-duplication: `result_handled_at` claim on run tables, Redis key on `job_id` for VALIDATION and PART3_EVALUATION. |

<div dir="rtl">

**القصة.** الموظف داس "موافقة الجمارك"، والـ AI حسب نوافذ الدخول وكتبها في الـ database. بس الـ Laravel مايعرفش إن الـ AI خلص، فالشاشة فاضلة واقفة على "جاري الحساب" لحد ما حد يعمل refresh. محتاجين طريقة الـ AI يقول بيها للـ Backend "أنا خلصت".

</div>

**Topic.** Laravel must learn that a worker finished so it can send SSE and notifications within 5 seconds of the commit. No mechanism is documented.

**Options:**

1. After committing, the worker pushes a JSON message (RPUSH) to a list named `queue:ai-results`; the Backend runs a consumer as a supervised artisan command.
2. A Backend poller reads the run tables at intervals (slower).
3. MySQL change capture (new infrastructure).

**Recommendation.** Option 1 with a poller as a safety net. Both teams agree, and it needs no new infrastructure because Redis is already there.
