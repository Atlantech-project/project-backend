<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 616-713 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-20 · Tug requirement formula (N_min, N_cap, BP_req per berthing request)

| | |
| --- | --- |
| Source | OD-04 · RSK-01 |
| Owner | AI Team |
| Status | Closed |
| Closing task | DEC-20 · Sprint 2 |
| Affects | AI-054 · AI-055 |
| Decision | Closed (2026-11-03), **interim tables**: the hybrid in "Decision detail" below. Tug requirements are computed before the solver, outside CP-SAT, and passed to it per berthing request as `N_min`, `N_cap`, `BP_req` (tonnes), `eligible_tugs` and flags. The values are interim until the open items below are settled. |
| Agreed with | Mostafa Mohamed Rashwan, Leads Council, 2026-11-03. AI lead approval: Menna Gamal (AI lead, @MennaGamal-87), 2026-11-03. |
| Implementation | `project-ai` `part2/tugs.py`, `part2/tug_rules.json`, `tests/unit/part2/test_tugs.py` (AI-054). CP-SAT constraints: AI-055. |

<div dir="rtl">

**القصة.** سفينة طولها 300 متر داخلة الميناء. محتاجة كام قاطرة؟ 2 ولا 3 ولا 4؟ القاعدة دي بيقررها خبراء القطر مش المبرمجين، ولحد دلوقتي محدش ادّاها لنا. من غيرها الـ solver مش هيعرف يحجز قاطرات.

</div>

**Topic.** The number of tugs per vessel (from GT, DWT and tug power) is not defined, and this blocks CP-SAT.

**Options:**

1. Input from the Tug Manager / Marine Safety Officer.
2. A provisional table by vessel length until the input arrives.

**Recommendation.** Request the formula formally in Sprint 1. If it does not arrive, the provisional table is marked "unvalidated" in the rationale and can be replaced without code changes (pluggable rule).

**Decision detail.** Option 2, extended to a hybrid: a table by LOA, with a mass-based bollard pull for tankers and bulk carriers. No formula has arrived from the Tug Manager or Marine Safety Officer, and Sprint 2 needs a rule. The rules are from literature and not yet validated by them, and they can be replaced without code changes.

**Options considered:**

| Option | Result |
| --- | --- |
| Fixed table by length only | Rejected: ignores vessel mass for heavy, slow ships |
| Hybrid: table by LOA, plus mass-based bollard pull for tankers and bulk carriers | **Chosen** |
| Physics only | Rejected: needs windage and environmental data not available in v1 |
| Free solver choice | Rejected: the solver could choose too few tugs |

**Calculation (per berthing request):**

1. **Compulsory towage.** If `gross_tonnage` is below `T_COMPULSORY`, `pilot_requested` is `False` and there is no `pilot_override`: `N_min` = `N_cap` = `BP_req` = 0. `T_COMPULSORY` = 1000, interim and conservative. If `pilot_requested` is unknown below `T_COMPULSORY`, the table applies, with flag `pilot_request_unknown`.
2. **Displacement, only for step 3's types.** Δ = LOA × beam × draught × Cb × 1.025 (tonnes).
   - **Draught:** `berthing_requests.arrival_draft`; if missing, `vessels.max_draft` with flag `max_draft_used`.
   - **Cb:** `berthing_requests.block_coefficient`; if missing or outside [0.50, 0.90], bulk carrier 0.80, tanker 0.80, container 0.65, other 0.85, with flag `cb_fallback_used`.
   - **LOA instead of LBP** slightly overestimates Δ, accepted as conservative. GT and NT are never used as a weight: they measure volume.
3. **Mass-based bollard pull, `TANKER` and `BULK_CARRIER` only.** BP_mass = 60 × Δ / 100,000 + 40 (tonnes).
4. **Table: MPA's column for vessels with power (interim).** Upper LOA limits are inclusive, so a decimal LOA between bands takes the higher band. `BP_floor` is N_base × the minimum, derived here; MPA doesn't state it.

   | LOA (m) | N_base | Min BP per tug (t) | BP_floor (t) |
   | --- | --- | --- | --- |
   | ≤ 60 | 1 | 10 | 10 |
   | ≤ 122 | 2 | 10 | 20 |
   | ≤ 200 | 2 | 17 | 34 |
   | ≤ 280 | 2 | 25 | 50 |
   | > 280 or GT ≥ 75,000, draught < 17 m | 4 | 25 | 100 |
   | > 280 or GT ≥ 75,000, draught ≥ 17 m | 4 | 45 | 180 |

5. **Final values.** `BP_req` = ceil(max(BP_mass if it applies, BP_floor)). `N_min` = N_base. `N_cap` = `N_min` + 1. `eligible_tugs`: tugs whose `tug_power` is in bollard pull and at least the minimum per tug. This is by rating only: availability is AI-055's job (DEC-32). A tug rated in `HP` or `KW` is excluded with flag `tug_power_not_bollard_pull`; no conversion is agreed.
6. **Pilot override.** An optional `pilot_override` replaces `N_min`, `BP_req` or both, with flag `pilot_override`. `N_cap` follows the overridden `N_min`, and eligibility stays by the band from step 4.

Every result carries flag `rules_unvalidated` until the rules are validated and `part2/tug_rules.json` has status `APPROVED`. Closing this decision doesn't validate them: the file stays `PROPOSED` (see the open items).

**Worked example:** bulk carrier, LOA 225 m, beam 32 m, draught 13 m, Cb 0.80, GT 40,000: Δ = 76,752 t, BP_mass = 86.05 t, `BP_req` = 87, `N_min` = 2, `N_cap` = 3.

**Thrusters:** credit for bow or stern thrusters is out of scope for v1. The berthing request has no thruster data, so results are conservative.

**CP-SAT interface (AI-055).** For each request r and tug t, with x[r,t] ∈ {0,1}:
- Σ x[r,t] over eligible tugs ≥ `N_min`
- Σ x[r,t] over eligible tugs ≤ `N_cap`
- Σ BP(t) · x[r,t] over eligible tugs ≥ `BP_req`
- x[r,t] = 0 for a tug that isn't eligible

If infeasible, the request is reported as infeasible; the requirement is never silently relaxed.

**Data requirements:** `vessels.loa`, `vessels.beam`, `vessels.max_draft`, `vessel_types.code`, `berthing_requests.arrival_draft`, `gross_tonnage`, `block_coefficient`, `tugboats.tug_power`, `tugboats.tug_power_unit`. Missing from the schema: `pilot_requested` and a tug-count `pilot_override`. A missing or non-positive LOA, beam, GT or draught raises an error.

**Deviation from AI-012:** the values are in `part2/tug_rules.json`, not `port_settings`, whose `setting_value` is `varchar(255)` and can't hold the table or its sources. A change needs a deploy, not a code change.

**Open items (follow-ups; they don't reopen the decision):**

1. Confirm `T_COMPULSORY` (1000 or 2000) with the Alexandria Port Authority.
2. Replace the interim MPA table with the Alexandria Port Authority's own table.
3. Confirm whether the form's draught is the arrival draught or the maximum draught. The schema holds `arrival_draft`, with `vessels.max_draft` as the fallback.
4. v2: wind-based bollard pull for high-windage ships: BP_wind = 1.25 × 0.5·ρ·C·A_L·V²/9810. Needs windage area and gust forecasts.
5. Fitting SWL limit on `eligible_tugs`: not applied. Check whether the schema has the field; `contracts/db/schema.sql` on PortHub `main` at `b4d2deb` has no match for `swl`, `safe_working` or `fitting`.
6. After the validation in item 9, change `part2/tug_rules.json` from `PROPOSED` to `APPROVED`, which removes the `rules_unvalidated` flag, in a separate code task. That task also updates `tests/unit/part2/test_tugs.py`, which asserts `PROPOSED`, and `part2/README.md`, which still says DEC-20 is Open.
7. Vessels without power: MPA has a separate, higher column, which isn't used. No field says which applies.
8. Tug Manager: decide whether `LNG_CARRIER` should use the mass-based formula. It's "other" for now.
9. Tug Manager and Marine Safety Officer: validate the rules (AI-005). `DWT` isn't used.
10. Backend: add `pilot_requested` to the request, and a place for a tug-count override.
11. Tug Manager: enter fleet power in bollard pull, or agree a conversion.

**References:**
- MPA Singapore Tug Assignment Guidelines, rev. 11 Feb 2025: https://www.mpa.gov.sg/api/media/c3d47522-816b-4197-b4f2-8bbaa3f87097/tug_assignment_guidelines.pdf. Checked on 2026-11-03: the column for vessels with power matches the table.
- Formula: https://www.seanews.com.tr/authors/cahit-istikbal/what-is-bollard-pull-and-how-to-calculate-it/162591/. Checked on 2026-11-03: confirms the formula and the example of 10,000 t giving 46 t, as a rough minimum. It names neither Hensen nor ship types.
- Hensen, *Tug Use in Port* (IMO MSC.1/Circ.1101): cited by the AI lead for the attribution and the limit to tankers and bulk carriers; not checked.
- Compulsory towage, cited by the AI lead, not checked: Alexandria Port Authority, https://apa.gov.eg/en/alex-port-navigation-data/ (above 2000); Ministry of Transport, https://www.mts.gov.eg/en/port/%D9%85%D9%8A%D9%86%D8%A7%D8%A1-%D8%A7%D9%84%D8%AF%D8%AE%D9%8A%D9%84%D8%A9/ (1000 and above).
