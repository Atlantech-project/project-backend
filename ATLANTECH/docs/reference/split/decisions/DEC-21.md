<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 715-806 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-21 · Phase 1 delay-risk rule

| | |
| --- | --- |
| Source | OD-05 |
| Owner | AI Team |
| Status | Closed |
| Closing task | DEC-21 · Sprint 3 |
| Affects | AI-053 · P-38 (Frontend Team) |
| Decision | Closed (2026-11-05), **interim rule**: Option 2 as a level, not a probability. B4 writes LOW / MEDIUM / HIGH with its driving factors, from a weather factor and a congestion factor, or NULL when the forecast is missing or stale. Thresholds are calibrated on IMF PortWatch and Open-Meteo history for Damietta (2019-01-01 to 2026-10-30) and are interim until our own data allows M4 (data-gated, AI-001). Interim storage: the level is written to the existing column `recommendation_options.delay_risk` as a fixed code (LOW = 0.100, MEDIUM = 0.500, HIGH = 0.900), with no schema change. Full rule in "Decision detail" below. |
| Agreed with | Mostafa Mohamed Rashwan, Leads Council, 2026-11-05. Decided by the Leads Council; the AI Team (owner) is informed. |
| Implementation | `project-ai` AI-053 (not yet implemented). |

<div dir="rtl">

**القصة.** الشاشة المفروض تقول للموظف "الخطة دي احتمال تتأخر 30%". بس إحنا لسه مافيش عندنا تاريخ تأخيرات نتعلم منه. لازم نتفق على قاعدة بسيطة مؤقتة، أو نعرض "مش متقيّم".

</div>

**Topic.** B4 needs a rule to estimate delay probability before any history exists.

**Options:**

1. NULL until a rule is approved.
2. A simple rule from weather margin and port congestion.

**Recommendation.** A simple rule approved in Sprint 1 so B4 is fully delivered. If not approved, it writes NULL and P-38 shows "Not assessed".

**Decision detail.** Option 2, as a level and not a probability. There is no delay history to learn from, so the rule is calibrated on public data for Damietta and is interim until our own operational data allows M4 (data-gated, AI-001).

**Rule (per recommendation, at planning time, over the vessel's window):**

1. **Weather factor W**, from the Open-Meteo forecast for the port:
   - HIGH: max gust > 35 kn, or max wind > 20 kn, or max wave height > 2.0 m
   - MEDIUM: max gust 30–35 kn, or max wave height 1.5–2.0 m
   - LOW: otherwise

   These are forecast-model screening levels, NOT safety limits. When the `safety_constraints` defaults exist (DEC-30 TR-10), headroom to those limits is added as an extra HIGH/MEDIUM condition.
2. **Congestion factor C** = expected arrivals in the 24 h around the ETA ÷ the median daily arrivals of the last 28 days:
   - MEDIUM: ratio ≥ 1.3
   - HIGH: ratio ≥ 1.6

   A ratio is used so the rule is scale-free (PortWatch undercounts versus the port authority, and the live system uses its own counts).
3. **Post-storm backlog.** If the window starts 1–3 days after a day where W was HIGH, C goes up one level (capped at HIGH).
4. **Result** = max(W, C). The rationale names the factor(s) that set the level.
5. **NULL** when the forecast is missing or older than `part1_stored_data_max_age_hours` (48).
6. Every result carries flag `rules_unvalidated` until the rule is validated.
7. **Storage (interim).** The level is written to the existing column `recommendation_options.delay_risk` as a fixed code: LOW = 0.100, MEDIUM = 0.500, HIGH = 0.900. These are level codes, NOT probabilities. Conditions:
   - (a) P-38 and every other screen show the level only, never the number (< 0.34 LOW, < 0.67 MEDIUM, otherwise HIGH);
   - (b) each recommendation records the rule version in `solver_run_model_versions`, so Phase 1 codes can be told apart from M4's calibrated probabilities later;
   - (c) Part 3 evaluates Phase 1 values as levels only, with no probability metrics (for example Brier score) on them;
   - (d) when M4 is promoted, the same column carries its probability and the codes stop.

Weekday and month are not inputs: the weekday effect is small (Sunday ≈ 7 % lower) and seasonality is already captured by the forecast.

**Evidence (Damietta, PortWatch port274 / EG DAM, 2,860 days, no gaps):**

- Daily calls rose from ≈5 (2019–2022) to ≈7 (2023–2026). 2023+ percentiles: median 7, P75 9, P90 11, P95 12 → ratio cut-offs 9/7 ≈ 1.3 and 11/7 ≈ 1.6.
- Busiest months May–June (≈8/day), quietest December (5.7).
- Calls relative to the surrounding 29-day median, by weather:

  | Weather | Calls vs normal | Days with 0–1 calls |
  | --- | --- | --- |
  | gust ≤ 30 kn and wave ≤ 1.5 m | normal | 2–3 % |
  | gust 30–35 kn or wave 1.5–2 m | −15 % | 7–10 % |
  | gust 35–40 kn | −37 % | 18 % |
  | gust > 40 kn, wind > 20 kn or wave > 2 m | −41 % to −49 % | 25–40 % |

- Rough days (W = HIGH) are 3.9 % of days, ≈3 a month Jan–Mar, ≈0 Jun–Oct; 80 episodes, 57 of them one day long.
- Day 2 and day 3 after an episode: +11 % and +17 % above normal (backlog).
- Same rough days at neighbouring ports: Alexandria 0.56, El Dekheila 0.47, Port Said 0.80 of normal → regional weather effect.

**Limitations:**

- PortWatch is an AIS estimate and reads lower than the port authority bulletin (4 checked days: bulletin 9–15 arrivals, PortWatch 3–12); only relative patterns are used.
- ERA5 reanalysis smooths peaks (max wind never above 25 kn in 8 years); thresholds are in model units and must be applied to Open-Meteo forecast values, not anemometer readings.
- Fewer calls is a proxy for slow-down or closure, not a measured delay.

**Open items (follow-ups; they don't reopen the decision):**

1. Frontend: P-38 shows the level, never the `delay_risk` number (condition a). Column: `recommendation_options.delay_risk` `decimal(4,3)`, CHECK `chk_ro_values` 0–1 (`schema.sql` lines 1149–1178), unchanged.
2. AI-053 implements the rule with the thresholds in a data file (like `part2/tug_rules.json`, status `PROPOSED`), not in code.
3. Recalibrate the cut-offs from our own operational data once available; M4 replaces the rule only after beating it (AI-001, DEC-30 TR-05).
4. P-38 shows "Not assessed" when the level is NULL (Frontend).
5. Open-Meteo free tier is non-commercial only; the commercial licence is DEC-26.
6. Record every input (W values, C ratio, post-storm flag) with each recommendation so M4 has features from day one.

**References:**

- IMF PortWatch Daily_Ports_Data (ArcGIS FeatureServer), port274.
- Open-Meteo Historical (ERA5) and Marine archive APIs, Damietta (31.46, 31.76), offshore wave point (31.53, 31.76). Weather data by Open-Meteo.com (CC BY 4.0).
- Data script: `research/fetch_damietta_history.py` (copy with the retry fix).
