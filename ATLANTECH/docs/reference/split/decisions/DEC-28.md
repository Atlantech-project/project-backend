<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 982-1034 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-28 · Definition of moves

| | |
| --- | --- |
| Source | OD-14 |
| Owner | AI Team |
| Status | Closed |
| Closing task | DEC-28 · Sprint 3 |
| Affects | AI-052 |
| Decision | Closed (2026-11-05): Option 1, made precise. Moves = discharge + loading, one move per container. Discharge = containers in the request's ACTIVE BAPLIE whose port of discharge is this port's UN/LOCODE. Loading = an optional request field; when empty it is set equal to discharge, with flag `loading_estimated`. A container vessel is solved only once its BAPLIE is ACTIVE; if a request still reaches the solver without one, it is left out with reason `MISSING_MOVES` as a safety net, and moves are never guessed. Full rule in "Decision detail" below. |
| Agreed with | Mostafa Mohamed Rashwan, Leads Council, 2026-11-05. Decided by the Leads Council; the AI Team (owner) is informed. |
| Implementation | `project-ai` AI-052 (not yet implemented). |

<div dir="rtl">

**القصة.** زمن خدمة السفينة بيتحسب من عدد حركات الونش. ملف الـ BAPLIE فيه كل الحاويات اللي على المركب، وكل حاوية مكتوب عليها هتنزل في أنهي ميناء، فنقدر نعدّ اللي نازل عندنا. بس الحاويات اللي هتطلع على المركب مش في الملف خالص. لازم نتفق هنعرف العدد الكلي إزاي.

</div>

**Topic.** Service time (B3) needs the number of moves.

**Options:**

1. Discharge from the BAPLIE + loading count entered or estimated.

**Recommendation.** This option. Loading count is an optional request field; if empty it is estimated equal to discharge.

**Decision detail.** Option 1, made precise. The BAPLIE lists every container on board, each with its port of discharge, so discharge is counted from it; loading is not in the BAPLIE.

**Rule (per berthing request of a container vessel, `vessel_types.code` = `CONTAINER`):**

1. **Discharge** = the number of `container_manifest_entries` rows of the request's ACTIVE BAPLIE (`baplie_files.status` = `ACTIVE`) whose `port_of_discharge` equals the port's UN/LOCODE (`port_profiles.port_code`). The code is read from the port's data, never hard-coded. Transshipment containers discharged here count; containers staying on board do not. If the port's UN/LOCODE (`port_profiles.port_code`) is not set, the job fails with `CONFIGURATION_ERROR` (CT-08 §7); discharge is never counted as zero.
2. **Loading** = the request's optional loading count when set. When empty, loading = discharge, with flag `loading_estimated`.
3. **Moves** = discharge + loading. One container is one move. Twin and tandem lifts are not credited, so service time errs long (conservative, as in DEC-20).
4. **Out of the MVP:** restows, hatch-cover moves, and twin or tandem lift credit.
5. **Precondition and safety net.** A container vessel's request is solved only once its BAPLIE is ACTIVE; the Backend does not enqueue the solve before that (planning flow agreed by the Leads Council; CT-10 does not yet state it, see open item 2). If a request still reaches the solver without an ACTIVE BAPLIE (for example, the BAPLIE was withdrawn or quarantined after the solve was enqueued), moves are NULL and the request is left out with reason `MISSING_MOVES`. Moves are never guessed.
6. **Vessels other than container vessels** have no BAPLIE. Rule 5 does not apply to them; their B3 basis is not set by this decision (open item 4).
7. Each result records discharge, loading and `loading_estimated`, so M2 (B3 Phase 2) has these features from day one.

**Open items (follow-ups; they don't reopen the decision):**

1. Backend (CT-17): add the optional loading-count field to the berthing request (`berthing_requests` has none today).
2. Backend (CT-10): today `NEW_REQUEST` is enqueued when Part 1 finishes with `WINDOWS_FOUND`, without checking the BAPLIE. For a container vessel it must wait for the later of `WINDOWS_FOUND` and the BAPLIE becoming ACTIVE. Until this is done, such requests reach the solver early, are left out with `MISSING_MOVES`, and nothing re-triggers them.
3. CT-10: add `MISSING_MOVES` as a new value. CT-10 has no per-request reason list for a request that is left out (per-request `outcome` is `SOLVED`, `FIFO_FALLBACK` or `NO_FEASIBLE_PLAN`), so Backend and AI Team choose where it goes.
4. AI Team: the B3 basis for vessels other than container vessels.
5. Terminal operations: confirm that loading ≈ discharge is a fair default for Damietta.
6. Backend / seed (CT-18): set `port_profiles.port_code` for Damietta (`EGDAM`); it is nullable and empty in the reference seed.
7. AI-030: confirm the port-of-discharge qualifier against the SMDG BAPLIE message implementation guides (2.2.1 and 3.1.1), not the samples. The corpus file `01-valid-221.edi` and the CT-12 example put the discharge port on `LOC+12` and `CNSHA` on `LOC+11`, which may not match the guide; if they are wrong, correct them (CT-12).

**References:**

- DEC-17 and CT-12 (BAPLIE parse, ACTIVE / QUARANTINED / REJECTED) · DEC-50 (correction and withdraw) · CT-10 (PortHub `contracts/ATLANTECH_Contracts.md` lines 489–497 solve rules, 499–537 `result_detail` and per-request `outcome`) · DEC-20 (conservative rounding).
- Schema (PortHub `contracts/db/schema.sql`): `baplie_files` (lines 65–96: `berthing_request_id` line 68, `status` line 74); `container_manifest_entries` (lines 367–398: `port_of_discharge` line 384); `port_profiles` (lines 1066–1083: `port_code` line 1069, nullable, filled from SaaS); `berthing_requests` (lines 195–235, no loading-count field); `vessel_types` (lines 1828–1834; seed `contracts/db/reference-seed.json` line 180, code `CONTAINER`). Parser: no BAPLIE parser code exists yet (`edi/` holds only a README; AI-030 is not implemented); the CT-12 example (`contracts/ATLANTECH_Contracts.md` lines 807–808) and the corpus file `contracts/ai/baplie-corpus/01-valid-221.edi` (lines 10–11) show the load port as `LOC+11` and the discharge port as `LOC+12`, to be confirmed in AI-030. Planning gate: not documented; CT-10 sends `NEW_REQUEST` when Part 1 finishes with `WINDOWS_FOUND` and does not mention the BAPLIE, while the BAPLIE gate in CT-11 (lines 572 and 630) applies to the yard job only.
