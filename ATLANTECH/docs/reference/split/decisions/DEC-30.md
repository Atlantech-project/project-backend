<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1114-1178 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-30 · Numeric thresholds register

| | |
| --- | --- |
| Source | OD-16 |
| Owner | AI Team |
| Status | Open |
| Closing task | DEC-30 · Sprint 2 |
| Affects | AI-008 · AI-072 |
| Decision | ______ |
| Proposed change | 2026-10-31 · AI-008 · Adopt the thresholds register below as the DEC-30 decision: 6 `FILLED`, 13 `DEFERRED` (each with an owner and the task that needs it), 1 `OUT OF MVP SCOPE` and 7 `REFERENCE` rows. A value becomes `AGREED` only when this change is approved · Reason: there is no single register yet. CT-18 waits on DEC-30 for `drift_psi_threshold`, `drift_mae_threshold` and the `safety_constraints` defaults, and AI-016 waits on it for the coverage gate · Evidence: the register below; `docs/tasks/status/AI-008.md`. Every `FILLED` value is traced to a non-NFR source; NFR figures and IDs are not used as sources (user ruling, 2026-10-31) · Impact: AI-016 · AI-021 · AI-024 · AI-044 · AI-061 · AI-071 · AI-072 · AI-090 · AI-101 to AI-103 · AI-104; CT-18 seed (Backend Team); CT-25 (QA Team) · Awaiting: AI Team owner, with approval by the whole team (option 1) |

<div dir="rtl">

**القصة.** أرقام كتير الشغل محتاجها ومحدش حددها. إمتى نقول إن الموديل بدأ يبوظ (drift)؟ وكام سم خطأ مسموح في حساب المد؟ من غيرها مش هنعرف نقول إن أي حاجة نجحت أو فشلت.

</div>

**Topic.** Drift, deviation significance, data sufficiency, tide tolerance, BAPLIE size classes, coverage.

**Options:**

1. One register owned by the AI Team and approved by the whole team.

**Recommendation.** This option: inputs prepared (QA-AI1), approved in Sprint 1, final write-up in AI-008.

**Proposed thresholds register (AI-008, 2026-10-31; not approved).** This register is part of the Proposed change above.
- **Labels:** `TR-xx` labels are local to this register. They are not official IDs.
- **Sources:** NFR figures and IDs are not used as sources.
- **Statuses:**
  - `FILLED`: a non-NFR document states the value.
  - `DEFERRED`: no agreed value yet; the owner and the task that needs it are named.
  - `OUT OF MVP SCOPE`: excluded by a decision.
  - `REFERENCE`: defined by another decision or contract, not by this register.
- **Approval:** values become `AGREED` only when DEC-30 is approved.

| TR | Threshold | Status | Value | Source | Owner | Needed by |
| --- | --- | --- | --- | --- | --- | --- |
| TR-01 | Drift PSI threshold (`drift_psi_threshold`) | DEFERRED | — | CT-18 §4 (⟨AI⟩ value) | AI Team | AI-072 · CT-18 seed |
| TR-02 | Drift rolling-MAE threshold (`drift_mae_threshold`) | DEFERRED | — | CT-18 §4 (⟨AI⟩ value); one key covers M2, M3 and M4, which have different units | AI Team | AI-072 · CT-18 seed |
| TR-03 | Rolling-MAE window length | DEFERRED | — | Arch §9.1 (C2) | AI Team | AI-072 |
| TR-04 | Data sufficiency per model (M2, M3, M4, C4) | DEFERRED | — | Arch §9.5 gives an estimate (about 9,500 calls a year, "several months"), not a threshold | AI Team | AI-024 (records the criteria here) |
| TR-05 | Phase 2 promotion margin over the Phase 1 baseline (M2, M3, M4) | DEFERRED | — | Arch §10; the meaning of "MAE" for the M4 classifier is open (OD-16) | AI Team | AI-101 · AI-102 · AI-103 |
| TR-06 | C3 Champion/Challenger guard | FILLED | Challenger promoted only if not worse on both MAE and override rate, on held-out time-based data; no numeric margin | Arch §9.3 · UML AD-16 P6/D3 | AI Team | AI-104 |
| TR-07 | Tide prediction tolerance and test period | DEFERRED | — | DEC-27 (tolerance set in DEC-30) | AI Team, with the DEC-27 source | AI-021 |
| TR-08 | BAPLIE size classes and parse-time targets | OUT OF MVP SCOPE | — | Out of MVP scope – per DEC-17 Decision row and CT-12 v1.0 | — | — |
| TR-09 | Test coverage gate | DEFERRED | — | AI-016 acceptance criterion "Coverage gate enforced at the agreed value" · AI-016 task decision item (8) | AI Team | AI-016 (`fail_under`) |
| TR-10 | Port-wide `safety_constraints` defaults (wind, gust, visibility, wave, minimum UKC and others) | DEFERRED | — | CT-18 §1 and §7 | AI Team (seed); input from the Marine Safety Officer through CT-25 (QA Team) | AI-044 · CT-18 seed |
| TR-11 | Window-accuracy definition (C1) | DEFERRED | — | AI-071 (OD-16) | AI Team | AI-071 |
| TR-12 | Stored environmental data freshness | FILLED | 48 h, and the data must cover the assessment horizon | CT-18 v1.0 `part1_stored_data_max_age_hours` · DEC-12 (Closed) · Arch §7.2 · UML 2.10 | AI Team | AI-025 · AI-041 |
| TR-13a | CP-SAT solver time limit | FILLED | 10 s (configurable default; min 1, max 120) | CT-18 v1.0 `solver_time_limit_seconds` · CT-10 | AI Team | AI-055 |
| TR-13b | Total Part 2 pipeline time | DEFERRED | — | No non-NFR source | AI Team | AI-061 |
| TR-14a | Recommendation latency percentiles under concurrency | DEFERRED | — | No non-NFR source | AI Team | AI-061 |
| TR-14b | Parallel solves | DEFERRED | — | No non-NFR source | AI Team | AI-061 |
| TR-15 | Option equivalence | FILLED | 10 % of option 1 | DEC-23 (Closed) · CT-18 `solver_option_equivalence_pct` · CT-10 | AI Team | AI-055 |
| TR-16 | Validation timeout and result TTL | FILLED | 15 s / 600 s | CT-13 · CT-18 `validation_timeout_seconds`, `validation_result_ttl_seconds` | AI Team | AI-060 · AI-067 |
| TR-17 | Part 1 retries | FILLED | 3 | DEC-04 (Closed) · CT-08 v1.0 · CT-18 `part1_max_retries` | AI Team | AI-048 |
| TR-18 | Worker restart time and availability | DEFERRED | — | No non-NFR source; CT-16 raises an alert when a worker is DOWN for more than 2 minutes | AI Team; consulted: Backend Team | AI-090 |
| TR-19 | Evaluation key question (not a threshold) | REFERENCE | — | Master_Context §4 (Port Authority daily bulletin): whether the port can handle more than about 26 vessels a day, assessed experimentally, not a pass/fail limit | AI Team | AI-084 |
| TR-20 | Deviation significance per operation type (UML AD-16 D1) | REFERENCE | — | DEC-09 (Open, Sprint 6); a Port Admin setting | Backend Team | — |
| TR-21 | Part 1 horizon, time step, minimum window, demand slot, gap tolerance | REFERENCE | — | DEC-24 (Open) | AI Team | — |
| TR-22 | Uncertainty margin and window confidence | REFERENCE | — | DEC-25 (Closed 2026-11-05; the MVP uses zero margin and `confidence` NULL) | AI Team | — |
| TR-23 | Tug count per vessel | REFERENCE | — | DEC-20 (Closed, interim tables) | AI Team | — |
| TR-24 | `run_stale_after_seconds` | REFERENCE | — | CT-18 §4 and §7 (the AI Team supplies it with its calculation) | AI Team | — |
| TR-25 | Clock-skew suspect rule | REFERENCE | — | `ClassDiagramContext.md` OI-06 | Backend Team | — |
