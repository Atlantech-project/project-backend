# ATLANTECH — DBML Change Report (BAPLIE hierarchy, voyage data, EGMSW intake)

> **Scope:** Port Operational DB changes (sections 1–5) plus the terminology change **Nafeza → EGMSW** (section 6).
> **Structure:** each section states the problem, the operational scenarios, the required database modifications with the reason for each, and the review notes found while checking it. All tables are consolidated as a summary in **Part B** at the end.
> **Tags:** 🟢 CONFIRMED (your decision) · 🟡 PROPOSAL, needs your confirmation · 🔵 CARRIED from existing docs.
> **Date:** 2026-10-16

## Decisions applied across the whole report

| # | Decision | Status |
|---|---|---|
| E1 | **Baseline implementation: Port Authority Staff is "in the middle."** Staff open the EGMSW portal, read the General Declaration (الإقرار العام), and enter the verified values into the ATLANTECH intake form. | 🟢 |
| E2 | **The automated EGMSW adapter is optional / future scope, described on paper only.** It is not built in the baseline. The schema stays forward-compatible, but nothing in the baseline depends on it. | 🟢 |
| E3 | **Every mention of "Nafeza" is replaced by "EGMSW"** (Egyptian Maritime Single Window). | 🟢 |

---

# Part A — Changes in detail

## 1. BAPLIE Ingestion & Container Hierarchy

### Problem Description
BAPLIE files and container records are linked directly to the physical vessel (`vessel_id`) rather than to the specific port visit (`berthing_request_id`). A vessel calls at the port many times, so this causes data-integrity and security problems:

- **Data mixing:** cargo from a previous visit mixes with cargo from the current visit because both point to the same vessel record.
- **Update chaos:** when a revised BAPLIE arrives for the current voyage, there is no versioning mechanism to replace the old data safely, which risks duplication or partial overwrites.
- **Terminal isolation:** commercially sensitive cargo data must be isolated between terminals. Tying containers globally to the vessel makes per-voyage isolation unreliable.
- **No reversibility:** if staff upload a BAPLIE by mistake, there is no structural way to withdraw that file and its containers without corrupting the vessel's history.

### Operational Scenarios
- **Scenario A (updated BAPLIE for the same Berthing Request):** shipping lines send revised manifests as the vessel approaches. The new file is treated as a revision: it becomes the active manifest, and the old file and its containers are archived for audit.
- **Scenario B (new BAPLIE for a returning vessel):** a vessel visits in March and again in September, each time with its own BAPLIE. March and September containers must stay fully separated, so planners and the optimizer only see cargo for the current Berthing Request.

### Required Database Modifications
- **`container`:** remove `vessel_id`, add `baplie_file_id` (NOT NULL).
  *Why:* the container's parent becomes the exact document that declared it, so every container is scoped to its source file and no cargo history is mixed.
- **`baplie_file`:** add `berthing_request_id`.
  *Why:* anchors the manifest to a specific port call, which isolates distinct visits by the same ship.
- **`baplie_file`:** add a lifecycle `status`.
  *Why:* version control in the database. The newest file is `ACTIVE`, earlier files `SUPERSEDED`, a mistaken upload `WITHDRAWN`, replaced in one transaction without deleting audit history.

### Review Notes
- **R4 (status overlap) 🟡:** the proposed values `ACTIVE / SUPERSEDED / WITHDRAWN / FAILED` overlap the existing `parse_status`, and there is no state for a quarantined file that awaits correction. Proposal: `parse_status` = parser outcome (`PENDING / PARSED / QUARANTINED / FAILED`); `status` = manifest lifecycle (`STAGED / ACTIVE / SUPERSEDED / WITHDRAWN`).
- **R5 (one ACTIVE per request) 🟡:** MySQL has no partial unique index. Proposal: a unique generated column `active_request_key = IF(status='ACTIVE', berthing_request_id, NULL)`, and do the supersede swap inside one transaction.
- **R6 (nullable link) 🟡:** FR-1.2.3 says a BAPLIE is associated with a request "when applicable." I recommend `berthing_request_id` NOT NULL, because the isolation model depends on it. Alternative: nullable until linked.
- `baplie_file.vessel_id` becomes derivable through the request. Keep it as a convenience or drop it (open question).

---

## 2. BAPLIE Diagnostics & Staged Data Correction

### Problem Description
Invalid BAPLIE data must stay out of the live operational tables to protect the unified port state. However, the EDI Parser currently drops invalid data entirely instead of staging it, so the backend has nothing to show for manual correction (UC-077). Validating human corrections inside the backend would also duplicate the EDIFACT validation rules and create divergent logic between components.

### Operational Scenarios
- **Scenario A (failed parse quarantine):** a file with missing or invalid required fields is blocked from the live container and vessel tables. The raw extracted data is quarantined together with the specific parse errors so staff can review them.
- **Scenario B (manual correction and audit):** staff open the quarantined file, fill in the missing fields, and submit. The system validates the corrected data through a single source of truth, unpacks it into the live tables, and permanently records that the manifest was altered by a human.

### Required Database Modifications
- **`baplie_file`:** add `staged_payload` (JSON) and `parse_diagnostics` (JSON).
  *Why:* an isolated quarantine area that keeps live tables clean while preserving the extracted data and error messages for correction in the dashboard.
- **`baplie_file`:** add `is_manually_corrected` (boolean) and `corrected_by` (FK to `user.id`).
  *Why:* every write must be attributable to an authenticated actor; these fields record that a human altered the manifest and who did it.

### Review Notes
- **R7 (correction history) 🟡:** `corrected_by` keeps only the last corrector. Add `corrected_at`, and write each correction (before/after) to `audit_log`.
- **R8 (parser change) 🟡:** "single source of truth" means the EDI Parser must expose a re-validation job (through the existing Redis queue), and the parser must write quarantined data instead of dropping it. This is a parser requirement, not only a DB change.
- **R13 (sensitivity) 🟡:** `staged_payload` holds commercially sensitive cargo data. It needs the same terminal-scoped access as `container`, and a retention rule to clear it once the file is resolved (rule open).

---

## 3. Vessel Dimensions & Voyage Data Acquisition

### Problem Description
The BAPLIE file does not contain structural vessel dimensions (beam, tonnage, and so on) or the current voyage draft. In addition, keeping the dynamic `draft` on the static `vessel` table causes overwriting and wrong history, because draft changes on every voyage with cargo weight and water density.

Under the Egyptian Maritime Single Window (EGMSW), the shipping agent submits the official General Declaration (الإقرار العام), which captures the permanent vessel particulars and the voyage-specific variables. **Baseline model (🟢 E1):** Port Authority Staff access the EGMSW portal, review the declaration, and enter the verified parameters into the intake form. An automated feed is a future option (section 4).

### Operational Scenarios
- **Scenario A (first call of a vessel):** staff create the vessel from the BAPLIE, then supplement the structural fields from the declaration.
- **Scenario B (returning vessel):** the structural fields are reused from the vessel record; staff only enter the new arrival and departure drafts and the ports of call for this voyage.

### Required Database Modifications
- **`vessel` (static master data):** remove `draft`; add `beam` (العرض), `gross_tonnage` (الحمولة الكلية), `net_tonnage` (الحمولة الصافية), `depth` (العمق), `call_sign` (دليل النداء), `flag_state` (دولة العلم).
  *Why:* permanent particulars tied to the IMO number. They stay constant across visits and form the basis for berth assignment, crane-outreach validation, and tariff calculation.
- **`berthing_request` (dynamic voyage data):** add `arrival_draft` (الغاطس), `departure_draft`, `previous_port`, `next_port`.
  *Why:* temporary variables of one voyage. The harbormaster needs the arrival draft to evaluate under-keel clearance against water depth, and port security needs the ports of call.

### Review Notes
- **R9 (readers of `vessel.draft`) 🟡:** Part 1 UKC/squat, the Part 2 hard constraint filter (draft vs berth `draft_depth`), and FR-1.4.2 must now read `berthing_request.arrival_draft`. Customs Approval and Part 1 should refuse to run when `arrival_draft` is empty.
- **R10 (vessel fields) 🟡:** vessels created from BAPLE have no dimensions, so the new `vessel` columns must be nullable and be required at Preliminary Approval. Rename `depth` to `moulded_depth` to avoid confusion with water/channel depth.
- Suggest UN/LOCODE for `previous_port` and `next_port` (suggestion only).

---

## 4. External Gateway Integration & Automated Intake — **Future scope, schema readiness only**

### Problem Description
The baseline relies on staff transcribing EGMSW data, but the schema must be forward-compatible with the planned automated intake adapter. When activated, the adapter must respect the port network boundary: the firewall allows outbound connections only, so external push webhooks are impossible and the adapter must pull. Automated intake also needs idempotency to avoid duplicate voyages on network retries, license-state enforcement in `READ_ONLY` mode, and an identity for audit logs.

### Operational Scenarios
- **Scenario A (baseline, now):** staff record the EGMSW trip/declaration number while entering the request manually. This already prevents duplicate manual requests.
- **Scenario B (future adapter):** the adapter pulls declarations outbound and does a safe upsert by that same identifier, updating an existing draft instead of creating a second request.

### Required Database Modifications
- **`berthing_request`:** add `external_reference_id` (string, unique, nullable). **Baseline: added now.**
  *Why:* it is the idempotency key (the EGMSW trip/declaration number, رقم الرحلة). Staff fill it during manual entry; the future adapter uses it for upserts.
- **`user`:** add `is_system_actor` (or seed an "EGMSW Adapter Service" account). **Future only, not added in the baseline.**
  *Why:* every mutation must link to an authenticated actor, and the adapter needs a valid identity without breaking audit foreign keys.

### Review Notes
- **R11 🟡:** the adapter account must be non-loginable (no usable credential), since `user` carries `password_hash`.
- Assumption: that EGMSW exposes a pull API is **unverified**; it only matters for the future adapter.
- UC-079 and FR-1.3.2 describe external requests arriving by "API/adapter"; they become optional/future (see Part B, impact table).

---

## 5. Data Collision & Precedence Rules

### Problem Description
Both manual entry (baseline) and the optional automated feed (future) can eventually touch the same voyage. Without collision handling, duplicates can be spawned, and automated jobs could silently overwrite human-verified values, compromising under-keel clearance margins.

### Operational Scenarios
- **"Double Booking":** staff log a request from an agent's early notice; later the feed pulls the declaration for the same voyage. Without a shared match key, a duplicate request appears.
- **"Overwrite War":** staff correct `arrival_draft` to 12.5 m after talking to the pilot or Master; a later sync pulls an older declaration with 12.0 m and overwrites it.
- **"Dangerous Late Update":** a window is confirmed on an 11.0 m draft; hours before arrival, the draft rises to 11.5 m. If the change is applied silently, the window stays "safe" despite the greater grounding hazard.
- **"Zombie Request":** staff cancel a request because the vessel diverted; a delayed declaration arrives and reopens or modifies the terminated voyage.

### Required Database Modifications
- **`berthing_request`: provenance columns** `draft_source`, `eta_source`, `ports_source` (`MANUAL`, `EGMSW_FEED`, `REGISTRY`).
  *Why:* distinguishes staff-entered from feed-entered values so precedence can be enforced. **Baseline: always `MANUAL` (default); the columns are cheap now and avoid migrating a populated table later (🟡 add now or defer).**
- **`berthing_request`: match-key indexes**, the unique index on `external_reference_id` plus a composite index for deduplication.
  *Why:* fast matching of incoming or manual entries against existing voyages.

### Core Precedence Rules

**Baseline (manual entry):**
1. **Duplicate guard:** `external_reference_id` is unique, with a warning for a probable duplicate on (`vessel_id`, `eta`).
2. **Safety lockout (extended):** once a request reaches `CUSTOMS_APPROVED` or `ENTRY_WINDOW_CONFIRMED`, **any** change to `arrival_draft` or `eta`, manual or automated, must go through the revision path, re-run Part 1 under-keel clearance, and re-confirm the window. All edits are audited.
3. **Terminal-state guard:** requests in `CANCELLED`, `REJECTED` (and `COMPLETED`) accept no voyage-data edits.
4. **Staff authority:** staff-entered values are authoritative (`MANUAL`).

**Future adapter (paper only):**
- Pull-only outbound HTTPS, idempotent upsert by `external_reference_id`, run by a dedicated system actor, blocked in `READ_ONLY` (423).
- **Human Authority Rule:** the feed may fill empty fields but never overwrites `MANUAL` values; conflicts raise a discrepancy alert for staff instead of being silently dropped.
- **Safety lockout:** conflicting values after customs approval never touch live columns; they alert and route to the revision path.
- **Terminal-state guard:** declarations for terminal-state requests are logged and discarded.

### Review Notes
- **R1 (missing `eta`) 🟡, high:** `berthing_request` has no `eta` column in the current DBML, and `imo_number` lives on `vessel`, so the proposed (`imo_number`, `eta`) index is impossible. Add `eta` to `berthing_request` and index (`vessel_id`, `eta`) instead.
- **R2 (lockout scope) 🟡, high:** the original lockout covered only feed conflicts. A manual late draft change has the same grounding risk, and manual entry is now the baseline, so the lockout was extended to all changes (rule 2 above).
- **R3 (no revise path) 🟡, high:** the lifecycle only allows `NEEDS_REVISION` from `RECEIVED_FROM_EXTERNAL`. Add transitions from `CUSTOMS_APPROVED` and `ENTRY_WINDOW_CONFIRMED` back to a revision state (name open, consistent with D4's note that the status vocabulary is not locked).
- **R12 (silent drop) 🟡, low:** silently ignoring a larger feed draft could hide a hazard, so conflicts must be logged and alerted (future adapter).

---

## 6. Terminology Change: Nafeza → EGMSW

### Problem Description
The System Design v2 BMC corrections table records an earlier correction: *"EMSW" as Egypt's single window → **Nafeza** (Egypt's real National Single Window).* The vessel General Declaration that feeds ATLANTECH comes from the maritime single window, so the project now uses **EGMSW** (Egyptian Maritime Single Window) consistently.

### Required Changes
- Replace every mention of **Nafeza** with **EGMSW**.
- *Why:* one consistent name for the upstream source of the General Declaration across the BMC, use cases, and DB notes.

### Review Notes
- **R14 🟡:** this reverses the earlier correction. I applied it as instructed but could not verify EGMSW's API availability (relevant only to the future adapter).
- After the swap, the BMC row reads almost like the original "EMSW" row, so I suggest rewording it (see Part B, section B5).

---

# Part B — Summary tables

## B1. Decisions

| # | Decision | Status |
|---|---|---|
| E1 | Baseline: Port Authority Staff in the middle (read EGMSW, enter in the intake form) | 🟢 |
| E2 | Automated EGMSW adapter optional / future, on paper only | 🟢 |
| E3 | Nafeza → EGMSW everywhere | 🟢 |

## B2. Review findings

| # | Severity | Section | Finding | Proposed fix (🟡) |
|---|---|---|---|---|
| R1 | **High** | 5 | `eta` does not exist on `berthing_request`; `imo_number` lives on `vessel`, so (`imo_number`, `eta`) cannot be one index. | Add `eta`; index (`vessel_id`, `eta`). |
| R2 | **High** | 5 | Safety lockout covered only the feed; a manual late draft change has the same risk. | Lockout applies to any draft/ETA change after customs approval: re-run Part 1 and re-confirm the window. |
| R3 | **High** | 5 | No lifecycle transition for "Revise" from `CUSTOMS_APPROVED` / `ENTRY_WINDOW_CONFIRMED`. | Add the transitions (status name open). |
| R4 | Medium | 1 | `status` overlaps `parse_status`; no quarantined state. | `parse_status`: `PENDING/PARSED/QUARANTINED/FAILED`; `status`: `STAGED/ACTIVE/SUPERSEDED/WITHDRAWN`. |
| R5 | Medium | 1 | "One ACTIVE per request" is not enforceable in MySQL as stated. | Unique generated column `active_request_key` plus a transactional swap. |
| R6 | Medium | 1 | `berthing_request_id` nullable or not (FR-1.2.3). | Recommend NOT NULL. |
| R7 | Medium | 2 | Only the last corrector is recorded. | Add `corrected_at`; log each correction to `audit_log`. |
| R8 | Medium | 2 | The parser must expose re-validation and write quarantined data. | Parser requirement through the Redis queue. |
| R9 | Medium | 3 | Dropping `vessel.draft` breaks Part 1 UKC, Part 2 hard filter, FR-1.4.2. | Read `arrival_draft`; block Part 1 if empty. |
| R10 | Low | 3 | New vessel fields are empty for BAPLIE-created vessels; `depth` is ambiguous. | Nullable, required at Preliminary Approval; rename `moulded_depth`. |
| R11 | Low | 4 | Adapter account must not be loginable. | Non-loginable seeded user (future). |
| R12 | Low | 5 | Silent drop of conflicting feed values hides hazards. | Discrepancy log and alert (future). |
| R13 | Low | 2 | `staged_payload` is commercially sensitive. | Terminal-scoped access; retention rule. |
| R14 | Info | 6 | Nafeza → EGMSW reverses an earlier correction; EGMSW API unverified. | Applied as instructed; API is an assumption. |

## B3. Consolidated DBML changes

### Baseline (implemented now)

**`baplie_file`**
```dbml
Table baplie_file {
  id                    int [pk]
  vessel_id             int [ref: > vessel.id]            // 🟡 keep as convenience, or drop
  berthing_request_id   int [not null, ref: > berthing_request.id]   // NEW 🟡 (R6)
  uploaded_by           int [ref: > user.id]
  file_hash_sha256      varchar
  uploaded_at           datetime
  parse_status          varchar   // 🟡 PENDING | PARSED | QUARANTINED | FAILED (R4)
  status                varchar   // NEW 🟡 STAGED | ACTIVE | SUPERSEDED | WITHDRAWN
  staged_payload        json      // NEW quarantine of extracted data
  parse_diagnostics     json      // NEW parser error list
  is_manually_corrected boolean [default: false]            // NEW
  corrected_by          int [null, ref: > user.id]          // NEW
  corrected_at          datetime [null]                     // NEW 🟡 (R7)
  active_request_key    int [null, unique]   // NEW 🟡 generated: IF(status='ACTIVE', berthing_request_id, NULL) (R5)
}
```

**`container`**
```dbml
Table container {
  // REMOVE: vessel_id
  baplie_file_id int [not null, ref: > baplie_file.id]   // NEW
  // other columns unchanged
}
```

**`vessel`** (static master data; `draft` removed)
```dbml
Table vessel {
  // REMOVE: draft
  beam            decimal   // NEW (العرض بالمتر)
  gross_tonnage   decimal   // NEW (الحمولة الكلية)
  net_tonnage     decimal   // NEW (الحمولة الصافية)
  moulded_depth   decimal   // NEW 🟡 (العمق), renamed from "depth" (R10)
  call_sign       varchar   // NEW (دليل النداء)
  flag_state      varchar   // NEW (دولة العلم)
  // all new columns nullable; required at Preliminary Approval 🟡
}
```

**`berthing_request`** (dynamic voyage data)
```dbml
Table berthing_request {
  eta                    datetime       // NEW 🟡 (R1), missing from the current DBML
  arrival_draft          decimal        // NEW (الغاطس بالمتر)
  departure_draft        decimal        // NEW
  previous_port          varchar        // NEW 🟡 suggest UN/LOCODE
  next_port              varchar        // NEW 🟡 suggest UN/LOCODE
  external_reference_id  varchar [null, unique]   // NEW, EGMSW trip/declaration number (رقم الرحلة)
  // indexes: (vessel_id, eta)   🟡 replaces (imo_number, eta) (R1)
}
```

### Forward-compatible (inactive until the adapter is built)

| Change | Baseline behavior | Why keep it in the schema |
|---|---|---|
| `berthing_request.draft_source`, `eta_source`, `ports_source` (`MANUAL / EGMSW_FEED / REGISTRY`) | Always `MANUAL` (default) 🟡 | Cheap now; avoids migrating a populated table later. For strict minimalism, defer with the adapter. |
| `user.is_system_actor` (or seeded "EGMSW Adapter Service") | **Not added** | Needed only when automated jobs write data (R11). |
| Discrepancy log for conflicting feed values | **Not added** | Exists only with the adapter (R12). |

## B4. Precedence rules at a glance

| Rule | Baseline (manual) | Future adapter |
|---|---|---|
| Duplicate guard | `external_reference_id` unique + (`vessel_id`, `eta`) warning | Upsert by `external_reference_id` |
| Human authority | Staff values authoritative | Feed fills empty fields only; conflicts alert |
| Safety lockout | Any draft/ETA change after customs approval triggers revision, Part 1 re-run, re-confirmation | Feed conflicts never touch live columns; alert and revise |
| Terminal-state guard | No edits in `CANCELLED / REJECTED / COMPLETED` | Declarations logged and discarded |
| Network / license | n/a | Outbound pull only; blocked in `READ_ONLY` (423) |

## B5. Nafeza → EGMSW

| Location | Current text | Change |
|---|---|---|
| `ATLANTECH_System_Design_Full_v2.md` §8 (BMC corrections table) | `"EMSW" as Egypt's single window` → `**Nafeza** (Egypt's real National Single Window)` | Replace **Nafeza** with **EGMSW (Egyptian Maritime Single Window)**. Suggested rewording of the row: *"Generic 'EMSW' → **EGMSW** (Egyptian Maritime Single Window), the source of the vessel General Declaration."* 🟡 |
| `ATLANTECH_Business_Model_Canvas_v2.pdf` and its published artifact | Contains the Nafeza wording | Not provided to me; needs the same replacement and a regenerated PDF. |
| Use Case Analysis / Master Context | No "Nafeza" found | None. They should gain EGMSW references (see B6). |

## B6. Impact on other documents

| Document | Update needed |
|---|---|
| **Master Context** §38, FR-1.3.2 | FR-1.3.2 ("receive requests through external API/adapter") becomes **optional / future**. Add EGMSW as the upstream source staff read from. Add `eta`, voyage-draft fields, and BAPLIE versioning to §54–55. |
| **Use Case Analysis** | **UC-079** → mark *future / optional*, reword as a pull-based adapter. **UC-078** note: staff transcribe from EGMSW. **UC-074–077**: versioned BAPLIE and quarantine. **UC-080/083/085**: extended lockout (R2). New use cases: *Withdraw BAPLIE*, *Replace BAPLIE (new revision)*. |
| **AI file** | Part 1 UKC and the Part 2 hard filter read `berthing_request.arrival_draft`, not `vessel.draft`. |
| **State diagram** | Add revision transitions from `CUSTOMS_APPROVED` / `ENTRY_WINDOW_CONFIRMED` (R3). |

