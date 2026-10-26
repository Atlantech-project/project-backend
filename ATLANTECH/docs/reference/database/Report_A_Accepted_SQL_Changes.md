# Report A: SQL changes you accepted (Tier 1 and Tier 2)

Nothing here has been run on a database. All statements target MySQL 8.0.16 or later. Run them on a copy first. The statements are adapted from the Pro account's `ATLANTECH_SQL_Change_List.md` (which was dry-run on an empty MySQL 8.0 copy) and corrected against your real SQL files.

## How to read this report

- **Section 0** is new: three defects I found by reading your real SQL files (Step 2). They must be fixed whatever else you decide.
- **Sections 1 and 2** are your accepted Tier 1 and Tier 2 items, with the final statements.
- **Section 3** explains the reasoning behind Tier 2 item 13 (trusted marker and clock skew). You chose option B.
- **Section 4** is what you did not accept, and what replaces it.
- **Section 5** is the order to apply everything.
- If the database is still empty, you can edit the `CREATE TABLE` statements directly instead of running `ALTER`. If it already has rows, use the three-step pattern: widen the ENUM, run the `UPDATE`, narrow the ENUM.

---

## 0. Defects in the real SQL files (mandatory under any option)

### 0.1 Three undefined types (`PortSync.sql` cannot be created as written)

`berthing_request.source`, `berthing_request.status` and `baplie_file.status` use types `request_source`, `request_status` and `baplie_file_status` that are never defined. Replace them inside the `CREATE TABLE` statements:

```sql
-- berthing_request
`source` ENUM('MANUAL','EXTERNAL') NOT NULL,
`status` ENUM('SUBMITTED','NEEDS_REVISION','PRELIMINARY_APPROVED','CUSTOMS_APPROVED',
              'ENTRY_WINDOW_CONFIRMED','RECOMMENDATION_GENERATED','PLAN_CONFIRMED','PLAN_CANCELLED',
              'COMPLETED','REJECTED','CANCELLED') NOT NULL DEFAULT 'SUBMITTED',

-- baplie_file
`status` ENUM('STAGED','ACTIVE','REPLACED','WITHDRAWN') NOT NULL DEFAULT 'STAGED',

-- berthing_request_status_history: use the same 11 values in BOTH columns
`from_status` ENUM('SUBMITTED','NEEDS_REVISION','PRELIMINARY_APPROVED','CUSTOMS_APPROVED',
                   'ENTRY_WINDOW_CONFIRMED','RECOMMENDATION_GENERATED','PLAN_CONFIRMED','PLAN_CANCELLED',
                   'COMPLETED','REJECTED','CANCELLED') NULL,
`to_status`   ENUM('SUBMITTED','NEEDS_REVISION','PRELIMINARY_APPROVED','CUSTOMS_APPROVED',
                   'ENTRY_WINDOW_CONFIRMED','RECOMMENDATION_GENERATED','PLAN_CONFIRMED','PLAN_CANCELLED',
                   'COMPLETED','REJECTED','CANCELLED') NOT NULL,
```

Why these values: the lists must be written anyway, so the UML names are used (RECEIVED becomes SUBMITTED, SUPERSEDED becomes REPLACED, PLAN_CANCELLED is added to the history). STAGED is kept because a BAPLIE file exists before it becomes ACTIVE (AD-8, Seq 2.7). This avoids a rename later. See Report B for the other naming items.

### 0.2 CHECK constraints exist only as comments (PortSync)

All 115 CHECK constraints in `PortSync.sql` are text inside table `COMMENT`s, so MySQL does not enforce them. (The SaaS file has real CHECKs.) The generated file `PortSync_base_ddl_fixes.sql` (part 1) turns 113 of them into real constraints. The other two are redefined below: `chk_license_state_reason` (section 2.4) and `chk_fr_on_demand` (section 2.11).

### 0.3 Generated columns are plain columns (PortSync)

13 columns described as `GENERATED (stored)` are ordinary nullable columns, so rules such as "one ACTIVE file per request" or "one ACTIVE plan per request" are not enforced. Part 2 of `PortSync_base_ddl_fixes.sql` replaces them with real `GENERATED ALWAYS AS (...) STORED` columns and their unique indexes. `recommendation.active_key` is redefined in section 1, item 4.

### 0.4 Housekeeping

The comments contain broken characters (for example `â€”`, `Â°C`, `Â§42`, `â†”`). Re-save the file as UTF-8 and fix them.

---

## 1. Tier 1 (all 7 accepted)

### 1. Role end date: `user_role_assignment` (OI-22, P-09)

Requirements: FR-AC-12, FR-GL-03, FR-GL-04.

```sql
ALTER TABLE `user_role_assignment`
  ADD COLUMN `active_from` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  ADD COLUMN `active_to` datetime NULL,
  ADD COLUMN `active_assignment_key` tinyint
    GENERATED ALWAYS AS (IF(`active_to` IS NULL, 1, NULL)) STORED,
  ADD UNIQUE KEY `uq_ura_active` (`user_id`, `role`, `organization_unit_id`, `active_assignment_key`),
  DROP INDEX `uq_ura_user_role_unit`,
  ADD CONSTRAINT `chk_ura_period` CHECK (`active_to` IS NULL OR `active_to` > `active_from`);
```

Effect: ending a role sets `active_to`. A role can be re-assigned later. Only one active row exists per (user, role, unit). Every authorisation query must filter `active_to IS NULL`. The "last active Port Admin" check counts rows with role `PORT_GM` and `active_to IS NULL`, under a lock (FR-GL-04).

### 2. Devices, tokens, sessions (OI-21, P-10, P-11)

Requirements: FR-AC-06, FR-AC-13, FR-MB-08, AD-17 S4/S5.

```sql
CREATE TABLE `user_device` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `device_id` varchar(100) NOT NULL,
  `platform` ENUM('ANDROID','IOS') NOT NULL,
  `app_version` varchar(20) NOT NULL,
  `last_seen_at` datetime NULL,
  `revoked_at` datetime NULL,
  UNIQUE KEY `uq_user_device` (`user_id`, `device_id`),
  CONSTRAINT `fk_device_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
);
```

With the two other new tables (`part1_run`, `recommendation_review`) the Port database goes from 87 to 90 tables, and with the 8 SaaS tables the total is 98. Update the counts in the documents (Master Context says 87 and 95; the PortSync.md overview says 87).

**P-11, document the framework tables** (Laravel Sanctum and sessions, created by Laravel migrations, not by `PortSync.sql`). Add this as a documented reference, and check it against the migration of your Laravel version:

| Table | Columns (framework default) | Your addition |
|---|---|---|
| `personal_access_tokens` | id, tokenable_type, tokenable_id, name, token (unique), abilities, last_used_at, expires_at, created_at, updated_at | add `revoked_at datetime NULL` (FR-GL-03: tokens get `revoked_at`, never deleted). Name the token with the device id (AD-17 S4) |
| `sessions` | id, user_id, ip_address, user_agent, payload, last_activity | none |

### 3. Part 1 run state and outcome (OI-43, P-22)

Requirements: FR-EW-02, FR-EW-03, FR-EW-07, FR-EW-08.

```sql
CREATE TABLE `part1_run` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `berthing_request_id` bigint NOT NULL,
  `status` ENUM('QUEUED','RUNNING','DONE','FAILED','REPLACED') NOT NULL DEFAULT 'QUEUED',
  `outcome` ENUM('WINDOWS_FOUND','NO_FEASIBLE_WINDOW','ENV_DATA_UNAVAILABLE') NULL,
  `retry_count` tinyint NOT NULL DEFAULT 0,
  `entry_window_proposal_id` bigint NULL,
  `queued_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `started_at` datetime NULL,
  `finished_at` datetime NULL,
  CONSTRAINT `fk_p1r_request` FOREIGN KEY (`berthing_request_id`) REFERENCES `berthing_request` (`id`),
  CONSTRAINT `fk_p1r_proposal` FOREIGN KEY (`entry_window_proposal_id`) REFERENCES `entry_window_proposal` (`id`),
  CONSTRAINT `chk_p1r_retry` CHECK (`retry_count` BETWEEN 0 AND 3)
);
```

The outcome names are my choice (the sources only name ENV_DATA_UNAVAILABLE and "no feasible window"). A proposal exists only when windows are found, so `entry_window_proposal` keeps its NOT NULL run FKs.

### 4. Delay re-plan versus the one-pending-recommendation key (OI-54, P-31)

Requirements: FR-PL-08, FR-PL-09, SM-4, AD-12.

```sql
ALTER TABLE `recommendation` DROP INDEX `active_key`, DROP COLUMN `active_key`;
ALTER TABLE `recommendation`
  MODIFY `status` ENUM('PENDING_REVIEW','CONFIRMED','REJECTED','INVALIDATED','SUPERSEDED','MODIFIED_BY_STAFF')
    NOT NULL DEFAULT 'PENDING_REVIEW',
  ADD COLUMN `purpose` ENUM('INITIAL','DELAY_REPLAN') NOT NULL DEFAULT 'INITIAL',
  ADD COLUMN `active_key` bigint
    GENERATED ALWAYS AS (IF(`status` IN ('PENDING_REVIEW','MODIFIED_BY_STAFF','CONFIRMED') AND `purpose` = 'INITIAL',
                            `berthing_request_id`, NULL)) STORED,
  ADD UNIQUE KEY `uq_rec_active` (`active_key`);
```

This statement also adds the value `MODIFIED_BY_STAFF` (Report B, item B-7: you decided to keep the other SQL values and add this one). On a fresh database, put the final `status` list directly in the `CREATE TABLE` and run only the `purpose` and `active_key` parts.

**Two things to remember in code:** (1) when staff confirm a delay re-plan, first set the old version to `SUPERSEDED`, then change the new version's `purpose` to `INITIAL`, in the same transaction; otherwise the key stops guarding the current plan version. (2) a staff override creates a MODIFIED_BY_STAFF version, so set the previous version to `SUPERSEDED` first or the unique key refuses the insert. See Coding Notes, section 5.

### 5. BAPLIE duplicate rule (OI-46, P-21)

Requirement: FR-BQ-03 (same hash for the same request is refused, unless the earlier copy is withdrawn; the same file for another request is allowed).

```sql
ALTER TABLE `baplie_file`
  ADD COLUMN `live_hash_key` char(64)
    GENERATED ALWAYS AS (IF(`status` = 'WITHDRAWN', NULL, `file_hash_sha256`)) STORED,
  ADD UNIQUE KEY `uq_bf_request_live_hash` (`berthing_request_id`, `live_hash_key`),
  DROP INDEX `file_hash_sha256`;
```

If MySQL refuses because a foreign key needs the old index, run `ADD` first and `DROP INDEX` in a second statement.

### 6. Audit actions and refused attempts (OI-26, P-13, P-14)

Requirements: FR-GL-02, FR-PL-07, AD-18 S15.

```sql
ALTER TABLE `audit_log`
  MODIFY `action` ENUM('CREATE','UPDATE','DELETE','STATUS_CHANGE','SAFETY_EXCEPTION','REJECTED_ATTEMPT') NOT NULL,
  MODIFY `entity_id` bigint NULL;
```

`REJECTED_ATTEMPT` is a placeholder name (no source names it). `entity_id` becomes nullable because a refused action such as a failed login with an unknown user has no entity. Put the route, the error code (401, 403, 409, 422, 423) and the reason in `after_state`.

### 7. One ACTIVE licence per port (OI-13, S-06), `SaaS.sql`

Requirement: FR-SAAS-07. The table comment says this is enforced, but the column is missing from the DDL.

```sql
ALTER TABLE `license`
  ADD COLUMN `active_port_key` bigint
    GENERATED ALWAYS AS (IF(`status` = 'ACTIVE', `port_registry_id`, NULL)) STORED,
  ADD UNIQUE KEY `uq_license_active_port` (`active_port_key`);
```

---

## 2. Tier 2 (accepted items)

### 1. Billing period rule (OI-18, S-09), `SaaS.sql`

Requirements: FR-SAAS-12, AD-4 N4. A refunded or cancelled transaction must free the period.

```sql
ALTER TABLE `billing_transaction`
  ADD COLUMN `live_period_key` date
    GENERATED ALWAYS AS (IF(`status` IN ('PENDING','FAILED','PAID'), `period_start`, NULL)) STORED,
  ADD UNIQUE KEY `uq_billing_license_live_period` (`license_id`, `live_period_key`),
  DROP INDEX `uq_billing_license_period`;
```

### 2. Licence hold (OI-19, S-05), `SaaS.sql`

Requirement: FR-SAAS-08, SM-1.

```sql
ALTER TABLE `license` ADD COLUMN `on_hold` tinyint(1) NOT NULL DEFAULT 0;
```

### 3. SaaS admin logical roles (S-01), `SaaS.sql`

Requirement: FR-SAAS-02, AD-7. One admin may hold several roles.

```sql
ALTER TABLE `saas_admin`
  ADD COLUMN `roles` SET('PLATFORM','LICENSE','BILLING') NOT NULL DEFAULT '';
```

I chose the `SET` column because it is the simplest. A separate `saas_admin_role` table also works if you prefer.

### 4. Licence mode and stored statuses (OI-07, OI-08, P-01 to P-06), `PortSync.sql`

Requirements: FR-LIC-01, FR-LIC-03, FR-LIC-06, SM-3, AD-5. `token_iat` (P-07) is not included, because `iat` is in the stored token.

```sql
ALTER TABLE `license_state`
  MODIFY `mode` ENUM('SETUP','FULL','READ_ONLY') NOT NULL DEFAULT 'READ_ONLY',
  MODIFY `read_only_reason` ENUM('NONE','NO_LICENSE','TOKEN_EXPIRED','SUSPENDED','REVOKED',
         'INVALID_SIGNATURE','CLOCK_ROLLBACK','PORT_SUSPENDED','PORT_OFFBOARDED',
         'LICENSE_NOT_YET_IN_EFFECT') NOT NULL DEFAULT 'NO_LICENSE',
  ADD COLUMN `port_status` ENUM('ONBOARDING','ACTIVE','SUSPENDED','OFFBOARDED') NULL,
  ADD COLUMN `license_status` ENUM('PENDING','ACTIVE','SUSPENDED','EXPIRED','REVOKED') NULL,
  ADD COLUMN `status_received_at` datetime NULL,
  ADD CONSTRAINT `chk_license_state_reason`
    CHECK ((`mode` IN ('FULL','SETUP')) = (`read_only_reason` = 'NONE'));
```

Decision: SETUP mode is in the MVP (confirmed by the team lead, who is also the supervisor). FR-LIC-01 and FR-LIC-03 stand; FRD v3 section 2.2 ("simplified FULL and READ_ONLY") must be corrected to name the three modes. The three new reason names are my proposals.

### 5. Forced password change (OI-23, P-08): **accepted** (decision changed)

Requirements: FR-AC-03, AD-17 DF, AD-18 DF, Seq 2.1.

```sql
ALTER TABLE `user`
  ADD COLUMN `must_change_password` tinyint(1) NOT NULL DEFAULT 0;
```

How it works:
- Set to 1 when the Port Admin creates the user or resets the password with a one-time password (FR-AC-09, FR-AC-10).
- While it is 1, login succeeds but every request except "change password" and "log out" is refused, so the user must change the password first (FR-AC-03).
- Set back to 0 in the same transaction as the password change, with an audit entry.

### 6. Row version (OI-24): **will be done in code**

Not applied in SQL. See Coding Notes, section 4.

### 7. Yard recommendation lifecycle (OI-51, P-39, P-40)

Requirements: FR-YD-03, FR-YD-04, FR-YD-05.

```sql
ALTER TABLE `terminal_recommendation`
  ADD COLUMN `lifecycle_status` ENUM('YARD_REC_REQUESTED','YARD_REC_GENERATED','GENERATION_FAILED',
         'YARD_REC_ACCEPTED','YARD_REC_REJECTED','EXPORTED_TO_TOS') NULL,
  ADD COLUMN `failure_reason` varchar(500) NULL,
  ADD COLUMN `exported_at` datetime NULL,
  ADD COLUMN `exported_by` bigint NULL,
  ADD COLUMN `rationale_template_id` bigint NULL,
  ADD COLUMN `confidence` decimal(4,3) NULL,
  ADD CONSTRAINT `fk_tr_exported_by` FOREIGN KEY (`exported_by`) REFERENCES `user` (`id`),
  ADD CONSTRAINT `fk_tr_template` FOREIGN KEY (`rationale_template_id`) REFERENCES `rationale_template` (`id`),
  ADD CONSTRAINT `chk_tr_lifecycle_yard` CHECK (`lifecycle_status` IS NULL OR `rec_type` = 'YARD_PLACEMENT'),
  ADD CONSTRAINT `chk_tr_confidence` CHECK (`confidence` IS NULL OR `confidence` BETWEEN 0 AND 1);
```

### 8. Solver result detail (OI-53), your decision

You decided: one `result_detail json NULL` column on `solver_run`, holding the binding constraints when `status = 'INFEASIBLE'` and the error message when `status = 'ERROR'`. This replaces P-33 (a new table) and P-32 (`failure_reason`).

```sql
ALTER TABLE `solver_run` ADD COLUMN `result_detail` json NULL;
```

Suggested JSON shapes (see Coding Notes, section 6): `{"binding_constraints":[{"constraint_type":"WIND_MAX","value":28.0,"unit":"KNOTS"}]}` and `{"error":"timeout after 10 s","fallback":"FIFO"}`.

### 9. AI or FIFO label (OI-55, P-29)

Requirement: FR-RC-06.

```sql
ALTER TABLE `recommendation`
  ADD COLUMN `source` ENUM('AI','FIFO') NOT NULL DEFAULT 'AI';
```

### 10. Reject-all windows reason (P-24)

Requirement: FR-EW-12.

```sql
ALTER TABLE `entry_window_proposal`
  ADD COLUMN `rejection_reason` varchar(500) NULL,
  ADD COLUMN `rejected_by` bigint NULL,
  ADD COLUMN `rejected_at` datetime NULL,
  ADD CONSTRAINT `fk_ewp_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `user` (`id`),
  ADD CONSTRAINT `chk_ewp_reject_set`
    CHECK (((`rejected_by` IS NULL) = (`rejected_at` IS NULL)) AND ((`status` = 'REJECTED') = (`rejection_reason` IS NOT NULL)));
```

Existing rows that break the check (a REJECTED proposal with no reason) make the `ALTER` fail. Backfill them first.

### 11. Forecast data quality and manual data (P-25, P-26, P-27)

Requirements: FR-ENV-02, FR-ENV-03, FR-ENV-07, FR-EW-15.

```sql
ALTER TABLE `forecast_run`
  ADD COLUMN `data_stale` tinyint(1) NOT NULL DEFAULT 0,
  ADD COLUMN `provider_rank` ENUM('PRIMARY','SECONDARY','MANUAL') NULL,
  MODIFY `pull_type` ENUM('SCHEDULED','ON_DEMAND','MANUAL') NOT NULL,
  ADD COLUMN `submitted_by` bigint NULL,
  ADD CONSTRAINT `fk_fr_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `user` (`id`),
  ADD CONSTRAINT `chk_fr_on_demand` CHECK ((`pull_type` = 'SCHEDULED') = (`berthing_request_id` IS NULL)),
  ADD CONSTRAINT `chk_fr_manual` CHECK ((`pull_type` = 'MANUAL') = (`submitted_by` IS NOT NULL));
```

`chk_fr_on_demand` is the new version of the existing rule: scheduled pulls have no request, on-demand and manual pulls have one.

### 12. Who viewed which recommendation (P-30)

Requirements: FR-RC-12, FR-PL-01.

```sql
CREATE TABLE `recommendation_review` (
  `recommendation_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `viewed_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  PRIMARY KEY (`recommendation_id`, `user_id`, `viewed_at`),
  CONSTRAINT `fk_rrv_rec` FOREIGN KEY (`recommendation_id`) REFERENCES `recommendation` (`id`),
  CONSTRAINT `fk_rrv_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
);
```

I renamed the constraints from the Pro version (`fk_rr_rec`) so they do not look like the `retraining_run` constraints (`fk_rr_*`).

### 13. Trusted marker and clock skew: **option B chosen** (OI-65, OI-66, P-43, P-44)

Requirements: FR-EX-08, FR-LM-03, AD-15, AD-16, Seq 3.2, Seq 3.3. See section 3 for the reasoning.

```sql
ALTER TABLE `actual_status_update`
  ADD COLUMN `client_sent_at` datetime NULL,
  ADD COLUMN `clock_skew_suspect` tinyint(1) NOT NULL DEFAULT 0;
```

What this means in practice:
- `client_sent_at` is the phone's clock when the batch was sent. It is NULL for events that do not come from the mobile app.
- `clock_skew_suspect = 1` when `received_at - client_sent_at` is more than 5 minutes (FR-EX-08). The event is still applied; the flag only excludes it from evaluation.
- There is **no** `is_trusted` column. A record is trusted when its source `actual_status_update` has `clock_skew_suspect = 0`, reached from a deviation through `status_update_id`.
- There are **no** resolution-note columns (P-47) and **no** `deviation_log.is_trusted`. The staff note on a suspect event goes into `audit_log.after_state` (Seq 3.3 already writes an audit entry for it). The record stays untrusted.
- Consequence to remember: a manual correction by staff (AD-16 A7) is not marked untrusted in the MVP. Revisit this only if corrections become part of the training data.

### 14. Notification types (OI-59, P-42)

Requirements: FR-DP-07, AD-13 Q3, AD-14, Seq 3.4.

```sql
ALTER TABLE `notification_log`
  MODIFY `type` ENUM('PLAN_ASSIGNMENT','PRE_EXECUTION_REMINDER','TUG_STILL_BUSY','ENTRY_WINDOW_READY',
         'RECOMMENDATION_READY','PLAN_CANCELLED','RETRAINING_REVIEW_NEEDED','LICENSE_EXPIRY_WARNING',
         'ACK_ESCALATION','YARD_RECOMMENDATION_READY','YARD_RECOMMENDATION_FAILED','DRIFT_ALERT') NOT NULL;
```

The four new names are placeholders. P-41 (attempt counter, escalated_at) is not included: record each retry as its own `notification_log` row, and the escalation as a row of type `ACK_ESCALATION`.

---

## 3. Item 13: why these columns exist, and the option chosen (B)

**The problem they solve.** The mobile app records events offline and sends them later. Staff must be able to see events whose phone clock was wrong (FR-EX-08), and Part 3 (evaluation and model learning) must not learn from untrustworthy data (FR-LM-03: "Clock-skew-suspect events are always untrusted").

| Proposed column | What it is for | Needed? |
|---|---|---|
| `clock_skew_suspect` (P-43) | yes/no: the phone clock and server clock differed by more than 5 minutes when the event was sent. Staff browse these events (Seq 3.3, FR-EX-08) | **Yes**, otherwise staff cannot list suspect events |
| `client_sent_at` (P-44) | the phone's clock at the moment of sending. The gap is `received_at - client_sent_at` (AD-15). Comparing `reported_at` with the server time would wrongly flag every event recorded hours earlier while offline | **Yes**, needed to compute the flag correctly and to show "device time, server time, difference" on the review screen (Seq 3.3) |
| `is_trusted` (P-46) | one yes/no that Part 3 filters on (`WHERE is_trusted = 1`). Set when the record is created: untrusted if the clock was suspect, or if staff manually corrected the record (AD-16 A7, D2) | **Optional.** It is a convenience. It can be derived from the other columns |
| `resolution_note`, `resolved_by`, `resolved_at` (P-47) | staff note on a suspect event (AD-16 S2). The record stays untrusted | **Optional.** The note can go in `audit_log.after_state` (Seq 3.3 already writes an audit entry) |
| `deviation_log.is_trusted` | the same marker on deviations | **Optional**, derived through `status_update_id` |

**Three options:**

| Option | Columns | Trade-off |
|---|---|---|
| **A. Full (Pro account version)** | `client_sent_at`, `clock_skew_suspect`, `is_trusted` on `actual_status_update`; `is_trusted` on `deviation_log`; note columns | Simple Part 3 queries, more columns |
| **B. Minimal (chosen)** | `client_sent_at` and `clock_skew_suspect` on `actual_status_update` only. Trusted = `clock_skew_suspect = 0`, joined through `status_update_id`. The review note goes to the audit log | Fewest columns, covers FR-EX-08 and FR-LM-03 for the MVP. Manual corrections (AD-16) are not marked untrusted, which is acceptable if corrections are out of the MVP |
| **C. Flag only** | `clock_skew_suspect` only | Staff review cannot show the gap, and the flag can only be computed at ingest |

The statements for option B are in section 2, item 13.

---

## 4. Not accepted, and what replaces it

| Item | Decision | What replaces it |
|---|---|---|
| OI-24 row version (P-12) | Done in code | Coding Notes, section 4 |
| OI-53 binding constraints table (P-33), failure reason (P-32) | Replaced by `solver_run.result_detail` | Section 2, item 8 |
| P-41 attempt counter | Not included | Each retry is a `notification_log` row |
| Tier 3 naming items | Separate report | Report B |

---

## 5. Order of application

1. Fix `PortSync.sql` section 0.1 (three types) in the `CREATE TABLE` statements, apply the accepted Report B edits there too (B-5 proposal status REPLACED, B-6 plan status CONFIRMED), and re-save as UTF-8.
2. Create the database from the corrected file.
3. Run `PortSync_base_ddl_fixes.sql` (CHECK constraints and generated columns).
4. Apply Tier 1 items 1 to 6 and Tier 2 items 4, 5, 7, 8, 9, 10, 11, 12, 13, 14 to the Port database. Item 4 of Tier 1 (`recommendation.active_key`) must come after the base fixes.
5. Apply Tier 1 item 7 and Tier 2 items 1, 2, 3 to the SaaS database.
6. Update the documents: table counts (98 in total, 90 Port plus 8 SaaS), `PortSync.md`, `SaaS.md`.
7. Update the class diagrams (new columns and tables) with a patch prompt for the Pro account. I will write it when you confirm Report B and item 13.
