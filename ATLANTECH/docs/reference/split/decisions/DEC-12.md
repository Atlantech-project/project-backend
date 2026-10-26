<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 362-386 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-12 · AI `port_setting` keys

| | |
| --- | --- |
| Source | AIBD-021 · PS-02 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-12 · Sprint 1 |
| Affects | AI-012 · P2-F9 |
| Decision | ______ |

<div dir="rtl">

**القصة.** لو وقت الـ solver أو عدد مرات إعادة المحاولة مكتوبين جوه الكود، أي تغيير محتاج developer وdeploy جديد. محتاجين قايمة متفق عليها بالإعدادات اللي الـ Backend يحطها في الـ database والـ AI يقراها.

</div>

**Topic.** AI settings must change without code changes.

**Options:**

1. Proposed list: `weather_pull_hours`, `tide_horizon_days`, `part1_stored_data_max_age_hours` (48), `part1_assessment_horizon_hours`, `part1_time_step_minutes`, `part1_min_window_minutes`, `demand_slot_minutes`, `solver_time_limit_seconds` (10), `solver_options_per_request`, `part1_max_retries` (3), `run_stale_after_seconds`, `part3_evaluation_cron`, `drift_psi_threshold`, `drift_mae_threshold`.
2. Environment variables (cannot change at runtime).

**Recommendation.** Option 1. Undocumented values are set in DEC-24 and DEC-30.
