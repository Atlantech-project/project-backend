<!-- Generated from docs/reference/uml/UML_Context.md lines 153-203 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## 10. Requirements

Every diagram section below has a **Requirements** line listing the functional (FR) and non-functional (NFR) requirements it shows. FR numbers follow the Functional Requirements Document; NFR numbers follow the Non-Functional Requirements specification (ISO/IEC 25010).

**Functional requirement groups**

| Group | Subject | Main diagrams |
|---|---|---|
| FR-0.1–0.6 | Central SaaS: port registry, license plans, licenses, billing, platform audit, no operational data | Category 1 (UC-1, 1.1–1.14, AD-1–AD-7, SM-1, SM-2) |
| FR-1.1–1.9 | Port and resource master data: berths, tugs and tug masters, pilot roster (shift-aware), channel and waypoints, unified model, change history | 2.4–2.6, AD-18 |
| FR-2.1–2.6 | BAPLIE upload, parsing, vessel record, diagnostics without partial commit, manual correction, file hash and status | 2.7, 2.8, AD-8 |
| FR-3.1–3.11 | Berthing request: creation, review, preliminary approval, status lifecycle with logged transitions, release of resources on cancel | 2.9–2.11, 2.14, AD-9, AD-10, AD-12, SM-4 |
| FR-4.1–4.9 | Recommendation engine: sequencing, berth, tug and pilot recommendations from actual state, review, override with reason, regeneration, metadata and rationale | 2.13, 2.14, AD-11, AD-12 |
| FR-5.1–5.4 | Plan dispatch to pilots and tug masters; field status from the mobile app | 2.15, 3.1–3.3, AD-13, AD-15 |
| FR-6.1–6.4 | State tracking and deviations: plan vs actual, deviation records, browsing | 3.3, AD-16 |
| FR-7.1–7.3 | Notifications at execution time, reminders with lead time, delivery status | 2.15, AD-13 |
| FR-8.1–8.10 | Container terminal: equipment, yard zones, blocks and slots, yard placement, TOS-usable format, planner accept, reject or adjust | 2.6, 2.16, AD-14 |
| FR-9.1–9.6 | Non-container terminal: storage facilities, allocation, gas and petrochemical resources, Marine Safety Officer constraints | 2.6 (master data only) |
| FR-10.1–10.8 | Configuration and extensibility: per-port terminals and resources, terminal types, shared recommendation interface and output contract, pre-recommendation safety point | 2.4, 2.6, 2.10, AD-18 |
| FR-11.1–11.6 | Access control: role hierarchy, SaaS Admin without port data, attributable writes, backend enforcement, terminal scope, commercial confidentiality | 2.1–2.3, AD-18, rule 1 in section 4 |

**Non-functional requirements**

| NFR | Requirement (key figure) | Where it shows in the diagrams |
|---|---|---|
| NFR-PERF-1 | Recommendation latency p50 ≤ 3 s, p95 ≤ 8 s, p99 ≤ 15 s with 15 concurrent pipelines | 2.13, AD-11 |
| NFR-ENG-1 | Solver deadline 10 s, then deterministic FIFO fallback flagged suboptimal, total ≤ 12 s | 2.13, AD-11 (FIFO fallback on technical solver failure) |
| NFR-PERF-2 | BAPLIE parsing ≤ 3 s / 10 s / 25 s for small, medium, large calls | 2.7, AD-8 |
| NFR-PERF-3 | API p95: read ≤ 500 ms, list ≤ 1,200 ms, write ≤ 800 ms, field update ≤ 500 ms | all request flows |
| NFR-PERF-4 | Dashboard update via SSE ≤ 5 s after commit | 2.15, 3.2, AD-13, AD-15 |
| NFR-CAP-1–3 | 1 terminal / 5 berths up to 10 / 50 without code change; ≥ 50 web sessions, ≥ 30 field connections, ≥ 5 parallel solver runs; peak data volumes | 2.4, AD-18 |
| NFR-REL-1–3 | Availability ≥ 99 % in evaluation, restart ≤ 60 s, RTO ≤ 15 min, RPO ≤ 5 min | not drawn (deployment) |
| NFR-REL-4 | Graceful degradation: malformed BAPLIE rejected with no partial commit; work continues if TOS is offline; notification retries | 2.7, AD-8, 2.15, AD-13 |
| NFR-REL-5 | Multi-entity changes in one ACID transaction, full rollback on error | 2.14, AD-11, AD-12 |
| NFR-CONC-1 | Version token on mutable records; stale write answered 409 within 200 ms | rule 1 in section 4 (record version, 409) |
| NFR-SYNC-1 | Field updates follow a monotonic state machine; offline updates that conflict with server changes are rejected, flagged and logged as deviations | 3.2, AD-15, SM-5, SM-6 |
| NFR-SEC-1, SEC-7 | Port-to-port separation; terminal-level isolation, manifest data stripped for other terminals (403) | 2.2, 2.8, section 9 |
| NFR-SEC-2 | RBAC at the API layer; field workers see only their own tasks | 2.2, AD-18 |
| NFR-SEC-3 | Token authentication, short web and mobile sessions, lockout after repeated failures, immediate invalidation | 1.1, 2.1, 2.2, AD-7, AD-17 |
| NFR-SEC-4, SEC-5 | TLS 1.3 in transit; AES-256 at rest, keys outside the code | 1.5, AD-1, AD-20 |
| NFR-SEC-6 | Append-only audit log (no update or delete) | rule 12 in section 4; 1.2, 2.6 |
| NFR-INT-1 | BAPLIE D.17A segments; unknown segments give warnings, valid slots still load | 2.7, AD-8 |
| NFR-INT-2 | REST with RFC 9457 errors, pagination, OpenAPI 3.0 | answer codes, section 5 |
| NFR-INT-3 | Berthing request adapters (REST, CSV, Excel, manual form) | see section 11 |
| NFR-INT-4 | Engine works with TOS or PMIS disconnected; human-readable export for manual entry | 2.16, AD-14 |
| NFR-INT-5 | IMO FAL data elements; national single window via pluggable adapter | not drawn |
| NFR-UX-1 | Inline rationale with constraints, resource logic, alternatives and confidence | 2.13, AD-11 |
| NFR-UX-2 | Execution alerts ± 60 s; reminders with configurable lead time (default 2 h); deviation alerts ≤ 3 min; virtual clock for testing | 2.15, AD-13, 3.3 |
| NFR-UX-3 | Field status in ≤ 3 taps, ≥ 16 sp text, offline task cache, sync badge ≤ 3 s after reconnect | 3.1, 3.2, AD-15, AD-17 |
| NFR-UX-4 | Specific, actionable, safe, bilingual (Arabic and English) error messages | 2.7, AD-8 and all refusals |
| NFR-EXT-1–5 | Port onboarding without code change (≤ 4 h by template, ≤ 24 h by UI); pluggable terminal modules; no circular module dependencies; test coverage gates; business rules changeable at runtime | 2.4, 2.6, AD-1, AD-18 |
