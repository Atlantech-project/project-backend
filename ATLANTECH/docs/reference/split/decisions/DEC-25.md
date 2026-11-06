<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 885-930 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-25 · Uncertainty margin and window confidence

| | |
| --- | --- |
| Source | OD-11 |
| Owner | AI Team |
| Status | Closed |
| Closing task | DEC-25 · Sprint 3 |
| Affects | AI-044 · AI-048 |
| Decision | Closed (2026-11-05): Option 1. The MVP applies no uncertainty margin: A4 checks forecast values against the `safety_constraints` thresholds as they are, and every window's confidence (`feasible_windows.confidence`) is written as NULL. A6 stays out of scope (AI-001). Full text in "Decision detail" below. |
| Agreed with | Mostafa Mohamed Rashwan, Leads Council, 2026-11-05. Decided by the Leads Council; the AI Team (owner) is informed. |
| Implementation | `project-ai` AI-044 (no margin term in the threshold check) and AI-048 (window confidence written as NULL) (not yet implemented). |

<div dir="rtl">

**القصة.** التوقعات الجوية مش مضمونة 100%. المفروض نسيب هامش أمان، ونعرض للموظف "واثقين من النافذة دي بنسبة كذا". بس حساب الهامش ده اتأجل لـ future work، فلازم نقرر نعرض إيه دلوقتي.

</div>

**Topic.** A6 (margin) is future work, so MVP behaviour must be defined.

**Options:**

1. Zero margin and confidence = NULL.

**Recommendation.** This option. P-37 shows no confidence value.

**Decision detail.** Option 1, recorded as already settled by AI-001 (Closed), which lists A6 as out of scope and states that the MVP uses zero margin and `confidence` = NULL.

1. **No margin.** A6 is future work, so there is no model to compute a margin. A4 compares forecast values directly with the `safety_constraints` thresholds.
2. **Safety rests on the thresholds.** With zero margin, the `safety_constraints` defaults must be conservative. A separate fixed margin in `port_settings` is not added: it would duplicate tightening the thresholds, which the Marine Safety Officer owns (DEC-30 TR-10), and give two sources for the same safety limit.
3. **Confidence NULL means "not assessed".** It is never stored or shown as 0 % or 100 %.
4. **Not the DEC-21 headroom.** DEC-21 uses headroom to the safety limits for the delay-risk level only. That is not an A6 margin and does not change any window.

**Open items (follow-ups; they don't reopen the decision):**

1. Frontend: P-37 shows no confidence value; when it is NULL the field is hidden, never shown as "0%".
2. When the Marine Safety Officer provides the TR-10 values (DEC-30, CT-25), he is told that no margin is added on top of them, so the values must already be conservative.
3. Building A6 later needs a new decision; it does not reopen this one.

**References:**

- AI-001 · AI scope and component inventory (this file): A6 out of scope, zero margin, `confidence` NULL.
- DEC-30 TR-10 (`safety_constraints` defaults) and TR-22 (this decision).
- DEC-21 (delay-risk headroom, separate from A6).
- Schema: `contracts/db/schema.sql` in PortHub and `docs/ref/db/schema.sql` in project-ai (same lines), lines 686–697 (`feasible_windows.confidence`, line 691, `decimal(4,3) DEFAULT NULL`, nullable; `chk_fw_values` on line 696 allows NULL).
