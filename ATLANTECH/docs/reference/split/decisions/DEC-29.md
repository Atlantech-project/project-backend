<!-- Generated from docs/reference/decisions/ATLANTECH_Decisions.md lines 1036-1112 by build-index.mjs. Do not edit: edit the source and rebuild. -->
### DEC-29 · Yard recommendation method, objective and confidence

| | |
| --- | --- |
| Source | OD-15 · RSK-09 |
| Owner | AI Team |
| Status | Closed |
| Closing task | DEC-29 · Sprint 3 |
| Affects | AI-065 · AI-066 |
| Decision | Closed (2026-11-07): Option B, CP-SAT, at slot level. Every container in scope gets a yard slot (block, bay, row, tier) as a recommendation, not a reservation: the Backend reserves nothing and only checks the slot at accept and at export. The objective is the fewest expected re-handles: a container is not placed above one that leaves earlier. The solve reads other vessels of the same terminal only. The Leads Council also approved recording container placement and removal from the tablet as an MVP feature (Backend data gap G-02), because the solver needs the yard's current state. DEC-16 is edited to match. Full rule in "Decision detail" below. |
| Agreed with | Mostafa Mohamed Rashwan, Leads Council, 2026-11-07. Decided by the Leads Council; the AI Team (owner) is informed. Backend positions on the data gaps: Marwan (Backend lead), proposal of 2026-11-06; the Leads Council's amendments accepted by the Backend on 2026-11-07. |
| Implementation | `project-ai` AI-065 and AI-066 (not yet implemented). |

<div dir="rtl">

**القصة.** الـ AI المفروض يقترح الحاويات تتحط فين في الساحة، بس محدش حدد يعمل ده إزاي. هل بقواعد بسيطة زي "المبرّد جنب الكهربا"؟ ولا بموديل optimization؟ وإيه اللي يخلّينا "واثقين" في الاقتراح؟

</div>

**Topic.** The B8 method is not defined.

**Options:**

1. Option A: rule-based heuristic (group by movement, type and weight, then compatible blocks).
2. Option B: CP-SAT assignment (needs an objective that is not documented).

**Recommendation.** Option A; confidence = share of containers placed in a zone of their preferred type.

**Decision detail.** Option B. Option B lacked an objective; the Leads Council set it: place each container where it will not have to be moved to reach another one. Travel distance and fuel are not terms of the objective.

**Rule (per yard recommendation, for the containers in scope per CT-11):**

1. **Output.** Every placed container gets `yard_block_id` and `yard_slot_id` in `yard_placement_items`, as a recommendation, not a reservation (DEC-16 as edited). The Backend reserves nothing: it only checks the slot at accept and at export (open item 6). A 40 ft or 45 ft container takes two adjacent slots: bays b and b+1, same block, row and tier (representation: open item 4).
2. **Departure time per container**, first that applies:
   - (a) onward vessel known (open item 1): that vessel's confirmed plan start, else its berthing request's `requested_eta`, else the onward ETA from the BAPLIE. ETA changes therefore flow through;
   - (b) import with no onward vessel: the discharging vessel's planned berth start + the default import dwell time (`port_settings`, open item 5);
   - (c) otherwise unknown. Containers with an unknown departure go in stacks of their own per arrival vessel and are never mixed with dated containers.

   Containers already in the yard and slots held by other recommendations get departure times the same way.
3. **Objective.** Minimise expected re-handles: each container placed above one that leaves earlier counts as one.
4. **Hard constraints:**
   - one container per slot; only `ACTIVE` slots;
   - no tier above the highest tier row of that stack, and no floating container: tier t > 1 needs tier t − 1 filled (in the yard, held, or placed in this run);
   - one length per stack (20 ft, or 40 / 45 ft);
   - a reefer only in a REEFER zone, with reefers in the zone ≤ `reefer_zone_details.total_power_plugs`;
   - a DG container only in a DG zone that allows its IMDG class;
   - an OOG container only in an OOG zone within its limits.
5. **Look-ahead (the solve reads other vessels of the same terminal only):**
   - (a) a slot held by another yard recommendation in `YARD_REC_GENERATED`, `YARD_REC_ACCEPTED` or `EXPORTED_TO_TOS` counts as taken until its container is recorded in the yard (open item 2), or its plan is `CANCELLED` or `COMPLETED` (`operational_plans.status`). Holding a slot is a solver-side rule, not a Backend reservation;
   - (b) berthing requests with an ACTIVE BAPLIE and an ETA but no confirmed plan get no slots; their expected discharge TEU is kept free in compatible zones, in ETA order;
   - (c) a slot occupied now counts as taken, even if its container is expected to leave before this vessel discharges (conservative).
6. **Parallel runs.** Two yard runs can read the same free slots. The Backend checks each slot at accept and at export and refuses a clash with `YARD_SLOT_CONFLICT` (open item 6).
7. **Time limit and fallback.** The solve has its own time limit (`port_settings`, open item 8) and returns the best solution found. With none, a greedy fallback applies the same hard rules. No container is ever placed against a hard rule; an unplaced container gets a reason code (CT-11).
8. **Confidence** = containers placed with zero expected re-handles ÷ all containers in scope. NULL whenever the run is `GENERATION_FAILED` (CT-11): a run that places nothing has no recommendation to be confident about. (Corrected 2026-11-08 on the Backend's proposal; was "0 when none is placed".)
9. **Re-generation.** When a vessel's ETA or plan changes the departure order, the affected yard recommendations are regenerated (open item 9).
10. Each placement records its departure time, the source of that time ((a), (b) or (c)) and its expected re-handles, for evaluation.
11. **Yard baseline.** Existing stock is not imported. The terminal officer sets every slot of a stack (all tiers of a bay and row) that holds existing stock to `BLOCKED`, and unblocks it once that stock has left. The solver treats every unrecorded `ACTIVE` slot as free. If it proposes a slot that is physically taken, the CT Supervisor records the actual slot.

**Open items (follow-ups; they don't reopen the decision):**

1. **G-01, Backend:** onward vessel and its ETA per container (nullable columns on `container_manifest_entries`, CT-12 optional fields), after a real BAPLIE confirms the data; AI-030 parses them. Until then rule 2(a) has no data. The Leads Council provides a real BAPLIE.
2. **G-02, Backend + Mobile:** the CT Supervisor records container placement and removal (slot and time) from the tablet; FR-YD-04 and FR-YD-05 are updated. Approved as an MVP feature by the Leads Council, 2026-11-06. Backend plan (2026-11-07): Sprint 6, in order: CT-07 event type, `yard_slot_events` migration and tablet page design; Backend handler (new CT Supervisor ability, idempotent apply, audit, refusal reasons), slot check at accept and export, occupancy read in AI-066; Mobile tablet screen; QA. The tablet identifies the container by its number (ISO 6346) and the slot by block, bay, row and tier. The placement screen is a field-role page and is not covered by DEC-42 (Leads Council, 2026-11-07). Until Sprint 6 the solver is tested on the G-03 demo yard only, and its results are not fit for production.
3. **G-03, Backend:** creating all slots of a block, and a Damietta demo yard (P2-F4, Sprint 3).
4. **G-04, Backend:** two-slot representation for 40 / 45 ft containers (tentative: `yard_slots.container_part` 1 or 2, UNIQUE (`container_id`, `container_part`), second slot = bay + 1). Terminal operations: Damietta's yard bay numbering.
5. **G-05, AI Team:** a default import dwell time key in `seed-spec.json`; the value comes from Terminal operations.
6. **G-06, Backend:** slot check at accept and export in one locking transaction, `YARD_SLOT_CONFLICT` (409), without naming the other vessel.
7. **G-07, AI Team + Backend:** CT-11 v1.2 (slot output, look-ahead within the terminal, this confidence, when a hold ends, slot-level reason codes) and CT-13 `YARD_ADJUSTMENT` slot validation, through contract-change.
8. **AI Team:** the yard solver time-limit key (DEC-30, CT-18).
9. **Backend + AI Team:** the trigger that regenerates yard recommendations on an ETA or plan change (CT-11 v1.2).
10. **G-08:** reefer plugs stay per zone; a per-slot flag is added only if a terminal provides plug positions.
11. **Risk:** this is larger than the original AI-065 / AI-066 plan and may not fit Sprint 3. Large vessels may need the solve split: blocks first, then stacks.

**References:**

- DEC-16 · DEC-42 · DEC-48 · CT-11 · CT-13 · DEC-08 (`w_yard` INSERT on `yard_placement_items`).
- Backend proposal on the 16 data gaps, Marwan (Backend lead), 2026-11-06, and his reply of 2026-11-07 (not in this repository).
- Schema (PortHub `contracts/db/schema.sql`, main 3f21022): `yard_slots` (lines 1970–1987: `bay` 1973, `row_no` 1974, `tier` 1975, `status` 1976, `container_id` 1977, `uq_ys_position` 1981, `chk_ys_blocked_empty` 1985) · `yard_placement_items` (1918–1936: `yard_block_id` 1923, `yard_slot_id` 1924) · `container_manifest_entries` (367–398: `full_empty` 371, `imdg_class` 373, `reefer_set_temperature` 379, `port_of_discharge` 384) · `container_size_types` (403–: `length_ft` 406) · `reefer_zone_details` (1312–1322: `total_power_plugs` 1315) · `dg_zone_allowed_classes` (474–484: `imdg_class` 477) · `oog_zone_details` (901–913: `max_length` 904 to `max_weight` 907) · `terminal_recommendations.lifecycle_status` (line 1638, in 1626–1665) · `operational_plans.status` (line 923).
