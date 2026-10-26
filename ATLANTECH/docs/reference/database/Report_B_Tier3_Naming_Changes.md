# Report B: Tier 3, naming changes in the SQL (UML name replaces the SQL name)

These items have the same meaning under a different name. For each one you apply the rename (Option A) or keep the SQL name and translate in the code (Option B, the mapping is in `Coding_Notes_and_Mapping.md`, section 1). Your decisions are recorded below. The class diagrams use the final SQL name in every case.

Nothing has been run on a database. If the database is still empty, edit the `CREATE TABLE` statements directly (the "fresh" statements below). If it holds rows, widen the ENUM, run the `UPDATE`, then narrow the ENUM.

## Summary and your decisions

| # | Table.column | Rename | Decision |
|---|---|---|---|
| B-1 | `berthing_request.status` | RECEIVED → SUBMITTED | Applied through Report A, section 0.1 (the type must be defined anyway) |
| B-2 | `berthing_request_status_history.from_status`, `to_status` | RECEIVED → SUBMITTED, add PLAN_CANCELLED | Applied through Report A, section 0.1 |
| B-3 | `baplie_file.status` | SUPERSEDED → REPLACED (STAGED kept) | Applied through Report A, section 0.1 |
| B-4 | `baplie_file.parse_status` | PARSED, FAILED, QUARANTINED vs UML COMPLETED, HAS_ERRORS | **Not changed.** SQL names stay and the class diagram uses the SQL names |
| B-5 | `entry_window_proposal.status` | SUPERSEDED → REPLACED | **Accepted** |
| B-6 | `operational_plan.status` | ACTIVE → CONFIRMED | **Accepted** |
| B-7 | `recommendation.status` | add MODIFIED_BY_STAFF only | **Accepted in this reduced form.** The other values stay (see B-7) |
| B-8 | `actual_status_update.client_event_id`, `reported_at` | renames | **Ignored** |
| B-9 | `berthing_request` table comment | wording | Not discussed. Still recommended (comment only) |
| B-10 | other items | none | No change needed |

Nothing has been run on a database. If the database is still empty, edit the `CREATE TABLE` statements directly (the "fresh" statements below). If it holds rows, widen the ENUM, run the `UPDATE`, then narrow the ENUM.

---

## B-1 and B-2: request status and history (already in Report A, section 0.1)

| SQL today | UML target | Where |
|---|---|---|
| RECEIVED | SUBMITTED | `berthing_request.status`, history `from_status` and `to_status` |
| (missing) | PLAN_CANCELLED | history ENUMs (SM-4: PLAN_CONFIRMED to PLAN_CANCELLED, and PLAN_CANCELLED back to ENTRY_WINDOW_CONFIRMED) |

Why: SM-4, FR-BR-02, FR-BR-11. The history table cannot store a status it does not have, so PLAN_CANCELLED is needed under Option B too.

If rows exist, migrate with the three-step pattern:

```sql
-- widen (keep RECEIVED), migrate, narrow
ALTER TABLE `berthing_request` MODIFY `status` ENUM('RECEIVED','SUBMITTED','NEEDS_REVISION','PRELIMINARY_APPROVED',
  'CUSTOMS_APPROVED','ENTRY_WINDOW_CONFIRMED','RECOMMENDATION_GENERATED','PLAN_CONFIRMED','PLAN_CANCELLED',
  'COMPLETED','REJECTED','CANCELLED') NOT NULL DEFAULT 'SUBMITTED';
UPDATE `berthing_request` SET `status` = 'SUBMITTED' WHERE `status` = 'RECEIVED';
ALTER TABLE `berthing_request` MODIFY `status` ENUM('SUBMITTED','NEEDS_REVISION','PRELIMINARY_APPROVED',
  'CUSTOMS_APPROVED','ENTRY_WINDOW_CONFIRMED','RECOMMENDATION_GENERATED','PLAN_CONFIRMED','PLAN_CANCELLED',
  'COMPLETED','REJECTED','CANCELLED') NOT NULL DEFAULT 'SUBMITTED';
-- repeat for berthing_request_status_history.from_status and to_status
```

## B-3: BAPLIE file status (already in Report A, section 0.1)

SUPERSEDED → REPLACED (FR-BQ-07, AD-8). STAGED stays because a file exists before it is ACTIVE; the UML has no name for it, so the class diagram should add STAGED to `BaplieFileStatus` (class change, View 2C).

```sql
-- with rows: widen, then
UPDATE `baplie_file` SET `status` = 'REPLACED' WHERE `status` = 'SUPERSEDED';
-- then narrow to ENUM('STAGED','ACTIVE','REPLACED','WITHDRAWN')
```

Also update the table comment: "old ACTIVE -> REPLACED".

## B-4: BAPLIE parse status: not changed (decision)

You decided to keep the SQL values and to use the SQL names in the class diagram, because the names are more meaningful when the class diagram is turned into code.

| SQL value (stays, used in the class diagram) | UML name (documents only) |
|---|---|
| PENDING | PENDING |
| PARSED | COMPLETED |
| FAILED | HAS_ERRORS |
| QUARANTINED | HAS_ERRORS |

- No SQL statement.
- The class diagram (View 2C) enumeration is `PENDING, PARSED, FAILED, QUARANTINED`.
- Coding rule to confirm with your team: write QUARANTINED when the file has errors but the extracted data is kept in `staged_payload` for staff to correct; write FAILED when the file could not be read at all. Both mean HAS_ERRORS in the UML and FRD v3 (FR-BQ-06, FR-BQ-08). The API can return `HAS_ERRORS` to the dashboard if you want to match the FRD wording.
- Fix the table comment of `staged_payload`: it already says "Quarantine", which matches.

## B-5: entry-window proposal status: accepted

SUPERSEDED → REPLACED (FR-EW-08: a new run replaces the earlier run). Values after: PROPOSED, CONFIRMED, REPLACED, REJECTED. The generated `confirmed_key` only uses CONFIRMED, so it is not affected.

```sql
-- fresh (edit CREATE TABLE)
`status` ENUM('PROPOSED','CONFIRMED','REPLACED','REJECTED') NOT NULL DEFAULT 'PROPOSED'
-- with rows
ALTER TABLE `entry_window_proposal` MODIFY `status`
  ENUM('PROPOSED','CONFIRMED','SUPERSEDED','REJECTED','REPLACED') NOT NULL DEFAULT 'PROPOSED';
UPDATE `entry_window_proposal` SET `status` = 'REPLACED' WHERE `status` = 'SUPERSEDED';
ALTER TABLE `entry_window_proposal` MODIFY `status`
  ENUM('PROPOSED','CONFIRMED','REPLACED','REJECTED') NOT NULL DEFAULT 'PROPOSED';
```

If you applied Report A, Tier 2 item 10 (reject-all fields), its check `chk_ewp_reject_set` uses only REJECTED, so it is not affected.

## B-6: operational plan status: accepted

ACTIVE → CONFIRMED (SM-5, FR-PL-02). Values after: CONFIRMED, CANCELLED, COMPLETED. The generated `active_key` uses the new name.

```sql
-- fresh (edit CREATE TABLE, and use this expression in PortSync_base_ddl_fixes.sql, already updated)
`status` ENUM('CONFIRMED','CANCELLED','COMPLETED') NOT NULL DEFAULT 'CONFIRMED'
-- active_key: GENERATED ALWAYS AS (IF(`status` = 'CONFIRMED', `berthing_request_id`, NULL)) STORED

-- with rows (the base fix script already ran)
ALTER TABLE `operational_plan` DROP INDEX `active_key`, DROP COLUMN `active_key`;
ALTER TABLE `operational_plan` MODIFY `status` ENUM('ACTIVE','CONFIRMED','CANCELLED','COMPLETED') NOT NULL DEFAULT 'ACTIVE';
UPDATE `operational_plan` SET `status` = 'CONFIRMED' WHERE `status` = 'ACTIVE';
ALTER TABLE `operational_plan`
  MODIFY `status` ENUM('CONFIRMED','CANCELLED','COMPLETED') NOT NULL DEFAULT 'CONFIRMED',
  ADD COLUMN `active_key` bigint GENERATED ALWAYS AS (IF(`status` = 'CONFIRMED', `berthing_request_id`, NULL)) STORED,
  ADD UNIQUE KEY `uq_op_active` (`active_key`);
```

## B-7: recommendation status: add MODIFIED_BY_STAFF only (decision)

You decided to keep the existing SQL values and add the one value the SQL lacks.

| Value | Status |
|---|---|
| PENDING_REVIEW | stays (UML GENERATED) |
| CONFIRMED | stays |
| REJECTED | stays |
| INVALIDATED | stays |
| SUPERSEDED | stays (UML REPLACED) |
| **MODIFIED_BY_STAFF** | **added** (FR-RC-13: a staff override creates a new version marked MODIFIED_BY_STAFF) |

The class diagram uses these SQL names plus MODIFIED_BY_STAFF. The ENUM change is included in Report A, Tier 1 item 4 (it also redefines `active_key`, which now counts MODIFIED_BY_STAFF as an active version):

```sql
ALTER TABLE `recommendation` MODIFY `status`
  ENUM('PENDING_REVIEW','CONFIRMED','REJECTED','INVALIDATED','SUPERSEDED','MODIFIED_BY_STAFF')
  NOT NULL DEFAULT 'PENDING_REVIEW';
```

I put the new value at the end of the list so MySQL can add it without rebuilding the table. Coding consequences are in Coding Notes, section 5 (override and confirmation order).

## B-8: column renames: ignored (decision)

`client_event_id` and `reported_at` keep their SQL names. The class diagram uses the SQL names for `ActualStatusUpdate`. The mobile Outbox class keeps the UML name `idempotency_key`, because the Outbox is not a database table. The mapping stays in Coding Notes, section 1.

## B-9: `berthing_request` table comment (comment only)

Replace the comment text so it matches AD-9 and SM-4:

> status = current state; history in berthing_request_status_history (same transaction). Return for revision only from SUBMITTED. ENTRY_WINDOW_CONFIRMED may be reopened to CUSTOMS_APPROVED while no plan is confirmed. Cancel only before Preliminary Approval.

```sql
ALTER TABLE `berthing_request` COMMENT =
 'status = current state; history in berthing_request_status_history (same transaction). Return for revision only from SUBMITTED; ENTRY_WINDOW_CONFIRMED may be reopened to CUSTOMS_APPROVED while no plan is confirmed. Cancel only before Preliminary Approval.';
```

## B-10: no change needed

| Item | Why |
|---|---|
| Role names (PORT_GM is the Port Admin, and so on) | Mapping only, listed in Coding Notes |
| CT and NCT Administrator share TERMINAL_ADMIN | Decided in Master Context 7.3 |
| Licence EXPIRED versus token expired (OI-09) | One reason, TOKEN_EXPIRED; word the message from the licence status |
| Read-only reasons for port suspension, offboarding, not yet in effect | Already added in Report A, item 4 |
| `STARTED` and `COMPLETED` status codes versus UML `DONE` | Seed rows; the code `COMPLETED` is the UML state DONE (Coding Notes, section 7) |

## Result for the class diagrams

The class diagram uses the SQL name after your decisions: request status (UML names, applied in SQL), BAPLIE file status (`STAGED, ACTIVE, REPLACED, WITHDRAWN`), parse status (`PENDING, PARSED, FAILED, QUARANTINED`), proposal status (`PROPOSED, CONFIRMED, REPLACED, REJECTED`), plan status (`CONFIRMED, CANCELLED, COMPLETED`), recommendation status (`PENDING_REVIEW, MODIFIED_BY_STAFF, CONFIRMED, REJECTED, INVALIDATED, SUPERSEDED`).
