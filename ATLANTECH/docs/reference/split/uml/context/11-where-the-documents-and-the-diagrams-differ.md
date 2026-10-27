<!-- Generated from docs/reference/uml/UML_Context.md lines 205-223 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## 11. Where the documents and the diagrams differ

The diagrams follow the decision register (`uml-edits/UML_CONTENT_EDITS.md`), which is newer than the documents below. An AI or reader should treat the diagrams as the current design and these points as known differences, not errors in the diagrams.

| Topic | Document says | Diagrams show |
|---|---|---|
| Solver failure | Master context D15: no fallback. NFR-ENG-1: FIFO fallback after a 10 s deadline. | FIFO fallback on technical solver failure, also passing the hard safety filter (agrees with NFR-ENG-1). |
| Berthing request statuses | FRD FR-3.9: received, needing revision, rejected, preliminary approved, recommendation generated, plan confirmed, completed, cancelled. Architecture: `RECEIVED_FROM_EXTERNAL`. | `SUBMITTED`, `NEEDS_REVISION`, `PRELIMINARY_APPROVED`, `CUSTOMS_APPROVED`, `ENTRY_WINDOW_CONFIRMED`, `RECOMMENDATION_GENERATED`, `PLAN_CONFIRMED`, `PLAN_CANCELLED`, `COMPLETED`, `REJECTED`, `CANCELLED` (SM-4). |
| Request intake | FR-3.2, FR-3.3 and NFR-INT-3: external API and CSV, Excel adapters as well as manual entry. | Requests are entered by Port Authority Staff only; there is no external request source. |
| TOS link | NFR-REL-4 and NFR-INT-4: outbound adapters, buffered when the TOS is offline. | The CT Operation Planner exports the yard plan and enters it into the TOS by hand (the human-readable fallback of NFR-INT-4). |
| Notifications | NFR-REL-4: push notification retries with backoff. | No push service: SSE and WebSocket after commit, re-send at T+10 and T+20 minutes, escalation at T+30. |
| Field task states | NFR-SYNC-1 example: `ASSIGNED` → `STARTED` → `COMPLETED`. | `UNASSIGNED` → `ASSIGNED` → `STARTED` → `DONE` inside the confirmed plan (SM-5). |
| Login lockout | NFR-SEC-3: lock for at least 15 minutes after 5 failed attempts. | SaaS Admin sign-in: a failed attempt is denied and audited, with no lockout in this release. |
| Marine Safety Officer scope | NFR-SEC-2: non-container scope. FR-9.6: gas carrier constraints for NCT. | Sets safety constraints, environmental limits, chart datum and under-keel clearance for the port's entry decisions. |
| License pricing | FR-0.2: price per month. | Annual licenses and yearly billing records, matching the business model (no monthly billing). |
| Environmental windows and AI feedback | Not in the FRD (only in the proposal, architecture and master context). | Drawn in full: entry-window assessment (2.10–2.12, AD-10) and guarded retraining (3.4, AD-16). |
| License modes and tokens | Not in the FRD or NFR. | Drawn in full from the master context decisions (category 1, SM-3). |
| FR numbering in the NFR document | NFR traceability uses another numbering (for example FR-1.4.1). | This file uses the FRD numbering (FR-0.1 … FR-11.6). |
| National single window | NFR-INT-5 and its glossary name EMSW; the corrected business model canvas names Nafeza. | Not drawn (out of scope). |
