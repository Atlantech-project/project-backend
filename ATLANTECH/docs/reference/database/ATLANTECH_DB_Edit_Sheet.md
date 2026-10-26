# ATLANTECH: DB edit sheet (SQL + DBML)

Final, approved changes. Edit the **source files directly** (the `CREATE TABLE` statements in `PortSync.sql` / `SaaS.sql`, and the DBML). The database is rebuilt from the files, so no `ALTER` or data migration is needed.

- **FIND** = the line as it is today. **REPLACE** = the new line. **ADD** = a new line inside the same `CREATE TABLE`.
- DBML lines use dbdiagram syntax. Adjust the enum names to the ones used in your DBML.
- Result: Port DB 87 → **90** tables, SaaS DB stays **8**, total **98**.

---

## 0. Global fixes (PortSync)

| # | Problem | Fix |
|---|---|---|
| G1 | `request_source`, `request_status`, `baplie_file_status` are used but never declared, so the SQL does not run | **DBML:** declare the 3 enums (section 1). **SQL:** use the inline `ENUM(...)` lines below |
| G2 | All 115 CHECK constraints are only text in table comments, so MySQL ignores them | Run `PortSync_base_ddl_fixes.sql` part 1 after creating the tables. DBML: keep them as notes |
| G3 | 13 "GENERATED (stored)" columns are plain columns, so the unique rules are not enforced | Run `PortSync_base_ddl_fixes.sql` part 2. DBML cannot express generated columns: keep the column with a note |
| G4 | Broken characters in comments (`â€”`, `â†”`, `Â°C`, `Â§`) | Re-save as UTF-8 and fix them |

---

## 1. Enums to declare in DBML (G1)

```dbml
Enum request_source { MANUAL  EXTERNAL }

Enum request_status {
  SUBMITTED  NEEDS_REVISION  PRELIMINARY_APPROVED  CUSTOMS_APPROVED  ENTRY_WINDOW_CONFIRMED
  RECOMMENDATION_GENERATED  PLAN_CONFIRMED  PLAN_CANCELLED  COMPLETED  REJECTED  CANCELLED
}

Enum baplie_file_status { STAGED  ACTIVE  REPLACED  WITHDRAWN }
```

---

## 2. Port DB: changed tables

### 2.1 `user`: forced password change
| | |
|---|---|
| ADD (SQL) | `` `must_change_password` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = one-time password in use; must change before any other action', `` |
| ADD (DBML) | `must_change_password boolean [not null, default: false, note: '1 = one-time password in use']` |

### 2.2 `user_role_assignment`: ending a role
| | |
|---|---|
| ADD (SQL) | `` `active_from` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP), `` |
| ADD (SQL) | `` `active_to` datetime COMMENT 'NULL = role active; set when the role is ended (never deleted)', `` |
| ADD (SQL) | `` `active_assignment_key` tinyint GENERATED ALWAYS AS (IF(`active_to` IS NULL, 1, NULL)) STORED `` |
| FIND (SQL) | ``CREATE UNIQUE INDEX `uq_ura_user_role_unit` ON `user_role_assignment` (`user_id`, `role`, `organization_unit_id`);`` |
| REPLACE | ``CREATE UNIQUE INDEX `uq_ura_active` ON `user_role_assignment` (`user_id`, `role`, `organization_unit_id`, `active_assignment_key`);`` |
| ADD CHECK | ``ALTER TABLE `user_role_assignment` ADD CONSTRAINT `chk_ura_period` CHECK (`active_to` IS NULL OR `active_to` > `active_from`);`` |
| DBML | `active_from datetime [not null, default: `now()`]`, `active_to datetime [note: 'NULL = active']`, `active_assignment_key tinyint [note: 'GENERATED: 1 when active_to IS NULL']`, index `(user_id, role, organization_unit_id, active_assignment_key) [unique]` |

### 2.3 `audit_log`: safety exception and refused attempts
| | |
|---|---|
| FIND | `` `entity_id` bigint NOT NULL, `` |
| REPLACE | `` `entity_id` bigint COMMENT 'NULL when a refused attempt has no entity (e.g. failed login)', `` |
| FIND | `` `action` ENUM ('CREATE', 'UPDATE', 'DELETE', 'STATUS_CHANGE') NOT NULL, `` |
| REPLACE | `` `action` ENUM ('CREATE', 'UPDATE', 'DELETE', 'STATUS_CHANGE', 'SAFETY_EXCEPTION', 'REJECTED_ATTEMPT') NOT NULL, `` |

### 2.4 `berthing_request`: real enum types, new status names
| | |
|---|---|
| FIND | `` `source` request_source NOT NULL, `` |
| REPLACE | `` `source` ENUM ('MANUAL', 'EXTERNAL') NOT NULL, `` |
| FIND | `` `status` request_status NOT NULL DEFAULT 'RECEIVED', `` |
| REPLACE | `` `status` ENUM ('SUBMITTED', 'NEEDS_REVISION', 'PRELIMINARY_APPROVED', 'CUSTOMS_APPROVED', 'ENTRY_WINDOW_CONFIRMED', 'RECOMMENDATION_GENERATED', 'PLAN_CONFIRMED', 'PLAN_CANCELLED', 'COMPLETED', 'REJECTED', 'CANCELLED') NOT NULL DEFAULT 'SUBMITTED', `` |
| Table comment | Replace the revision sentence with: *"Return for revision only from SUBMITTED; ENTRY_WINDOW_CONFIRMED may be reopened to CUSTOMS_APPROVED while no plan is confirmed. Cancel only before Preliminary Approval."* |

### 2.5 `berthing_request_status_history`: same 11 values
| | |
|---|---|
| FIND | `from_status` and `to_status` ENUM lists (start with `'RECEIVED'`, no `'PLAN_CANCELLED'`) |
| REPLACE (both) | `ENUM ('SUBMITTED', 'NEEDS_REVISION', 'PRELIMINARY_APPROVED', 'CUSTOMS_APPROVED', 'ENTRY_WINDOW_CONFIRMED', 'RECOMMENDATION_GENERATED', 'PLAN_CONFIRMED', 'PLAN_CANCELLED', 'COMPLETED', 'REJECTED', 'CANCELLED')` (keep `NULL` on `from_status`, `NOT NULL` on `to_status`) |
| DBML | both columns use `request_status` |

### 2.6 `baplie_file`: status type and duplicate rule
| | |
|---|---|
| FIND | `` `file_hash_sha256` char(64) UNIQUE NOT NULL, `` |
| REPLACE | `` `file_hash_sha256` char(64) NOT NULL, `` (global UNIQUE removed) |
| FIND | `` `status` baplie_file_status NOT NULL DEFAULT 'STAGED' COMMENT '... SUPERSEDED = replaced by a revision ...', `` |
| REPLACE | `` `status` ENUM ('STAGED', 'ACTIVE', 'REPLACED', 'WITHDRAWN') NOT NULL DEFAULT 'STAGED' COMMENT 'STAGED = uploaded, not yet in use; ACTIVE = in use; REPLACED = replaced by a revision; WITHDRAWN = uploaded by mistake', `` |
| ADD | `` `live_hash_key` char(64) GENERATED ALWAYS AS (IF(`status` = 'WITHDRAWN', NULL, `file_hash_sha256`)) STORED, `` |
| ADD index | ``CREATE UNIQUE INDEX `uq_bf_request_live_hash` ON `baplie_file` (`berthing_request_id`, `live_hash_key`);`` |
| No change | `parse_status` stays `PENDING, PARSED, FAILED, QUARANTINED` (decided) |

### 2.7 `entry_window_proposal`: REPLACED and reject-all reason
| | |
|---|---|
| FIND | `` `status` ENUM ('PROPOSED', 'CONFIRMED', 'SUPERSEDED', 'REJECTED') NOT NULL DEFAULT 'PROPOSED', `` |
| REPLACE | `` `status` ENUM ('PROPOSED', 'CONFIRMED', 'REPLACED', 'REJECTED') NOT NULL DEFAULT 'PROPOSED', `` |
| ADD | `` `rejection_reason` varchar(500) COMMENT 'Required when REJECTED (all windows rejected)', `` |
| ADD | `` `rejected_by` bigint, `` |
| ADD | `` `rejected_at` datetime, `` |
| ADD FK | ``ALTER TABLE `entry_window_proposal` ADD CONSTRAINT `fk_ewp_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `user` (`id`);`` |
| ADD CHECK | ``ALTER TABLE `entry_window_proposal` ADD CONSTRAINT `chk_ewp_reject_set` CHECK (((`rejected_by` IS NULL) = (`rejected_at` IS NULL)) AND ((`status` = 'REJECTED') = (`rejection_reason` IS NOT NULL)));`` |

### 2.8 `forecast_run`: stale data, provider, manual entry
| | |
|---|---|
| FIND | `` `pull_type` ENUM ('SCHEDULED', 'ON_DEMAND') NOT NULL, `` |
| REPLACE | `` `pull_type` ENUM ('SCHEDULED', 'ON_DEMAND', 'MANUAL') NOT NULL, `` |
| ADD | `` `provider_rank` ENUM ('PRIMARY', 'SECONDARY', 'MANUAL') COMMENT 'Which provider supplied the data', `` |
| ADD | `` `data_stale` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = latest data older than the allowed age', `` |
| ADD | `` `submitted_by` bigint COMMENT 'Required when pull_type = MANUAL', `` |
| ADD FK | ``ALTER TABLE `forecast_run` ADD CONSTRAINT `fk_fr_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `user` (`id`);`` |
| Comment of CHECK `chk_fr_on_demand` | becomes: `(pull_type = 'SCHEDULED') = (berthing_request_id IS NULL)`; ADD `chk_fr_manual`: `(pull_type = 'MANUAL') = (submitted_by IS NOT NULL)` |

### 2.9 `recommendation`: override value, AI/FIFO, delay re-plan
| | |
|---|---|
| FIND | `` `status` ENUM ('PENDING_REVIEW', 'CONFIRMED', 'REJECTED', 'INVALIDATED', 'SUPERSEDED') NOT NULL DEFAULT 'PENDING_REVIEW', `` |
| REPLACE | `` `status` ENUM ('PENDING_REVIEW', 'CONFIRMED', 'REJECTED', 'INVALIDATED', 'REPLACED', 'MODIFIED_BY_STAFF') NOT NULL DEFAULT 'PENDING_REVIEW', `` (SUPERSEDED renamed REPLACED, the same word the other tables use) |
| ADD | `` `source` ENUM ('AI', 'FIFO') NOT NULL DEFAULT 'AI' COMMENT 'FIFO = fallback after a solver technical failure', `` |
| ADD | `` `purpose` ENUM ('INITIAL', 'DELAY_REPLAN') NOT NULL DEFAULT 'INITIAL', `` |
| FIND | `` `active_key` bigint UNIQUE COMMENT 'GENERATED (stored): ...', `` |
| REPLACE | `` `active_key` bigint GENERATED ALWAYS AS (IF(`status` IN ('PENDING_REVIEW','MODIFIED_BY_STAFF','CONFIRMED') AND `purpose` = 'INITIAL', `berthing_request_id`, NULL)) STORED UNIQUE, `` |

### 2.10 `solver_run`: result detail
| | |
|---|---|
| ADD | `` `result_detail` json COMMENT 'INFEASIBLE: binding constraints; ERROR: error message', `` |

### 2.11 `operational_plan`: ACTIVE becomes CONFIRMED
| | |
|---|---|
| FIND | `` `status` ENUM ('ACTIVE', 'CANCELLED', 'COMPLETED') NOT NULL DEFAULT 'ACTIVE', `` |
| REPLACE | `` `status` ENUM ('CONFIRMED', 'CANCELLED', 'COMPLETED') NOT NULL DEFAULT 'CONFIRMED', `` |
| FIND | `` `active_key` bigint UNIQUE COMMENT 'GENERATED (stored): if((status = _latin1''ACTIVE''),...)', `` |
| REPLACE | `` `active_key` bigint GENERATED ALWAYS AS (IF(`status` = 'CONFIRMED', `berthing_request_id`, NULL)) STORED UNIQUE, `` |

### 2.12 `terminal_recommendation`: yard lifecycle
| | |
|---|---|
| ADD | `` `lifecycle_status` ENUM ('YARD_REC_REQUESTED', 'YARD_REC_GENERATED', 'GENERATION_FAILED', 'YARD_REC_ACCEPTED', 'YARD_REC_REJECTED', 'EXPORTED_TO_TOS') COMMENT 'YARD_PLACEMENT only', `` |
| ADD | `` `failure_reason` varchar(500), `` |
| ADD | `` `exported_at` datetime, `` / `` `exported_by` bigint, `` |
| ADD | `` `rationale_template_id` bigint, `` / `` `confidence` decimal(4,3), `` |
| ADD FK | `exported_by` → `user.id` (`fk_tr_exported_by`); `rationale_template_id` → `rationale_template.id` (`fk_tr_template`) |
| ADD CHECK | `chk_tr_lifecycle_yard`: `lifecycle_status IS NULL OR rec_type = 'YARD_PLACEMENT'`; `chk_tr_confidence`: `confidence IS NULL OR confidence BETWEEN 0 AND 1` |

### 2.13 `notification_log`: new types
| | |
|---|---|
| FIND | `type` ENUM (8 values, ends with `'LICENSE_EXPIRY_WARNING'`) |
| REPLACE | `` `type` ENUM ('PLAN_ASSIGNMENT', 'PRE_EXECUTION_REMINDER', 'TUG_STILL_BUSY', 'ENTRY_WINDOW_READY', 'RECOMMENDATION_READY', 'PLAN_CANCELLED', 'RETRAINING_REVIEW_NEEDED', 'LICENSE_EXPIRY_WARNING', 'ACK_ESCALATION', 'YARD_RECOMMENDATION_READY', 'YARD_RECOMMENDATION_FAILED', 'DRIFT_ALERT') NOT NULL, `` |

### 2.14 `actual_status_update`: clock skew
| | |
|---|---|
| ADD | `` `client_sent_at` datetime COMMENT 'Device clock when the batch was sent', `` |
| ADD | `` `clock_skew_suspect` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = received_at - client_sent_at > 5 min; excluded from evaluation', `` |
| No change | `client_event_id`, `reported_at` keep their names |

### 2.15 `license_state`: SETUP mode and received statuses
| | |
|---|---|
| FIND | `` `mode` ENUM ('FULL', 'READ_ONLY') NOT NULL DEFAULT 'READ_ONLY', `` |
| REPLACE | `` `mode` ENUM ('SETUP', 'FULL', 'READ_ONLY') NOT NULL DEFAULT 'READ_ONLY', `` |
| FIND | `read_only_reason` ENUM (7 values) |
| REPLACE | `` `read_only_reason` ENUM ('NONE', 'NO_LICENSE', 'TOKEN_EXPIRED', 'SUSPENDED', 'REVOKED', 'INVALID_SIGNATURE', 'CLOCK_ROLLBACK', 'PORT_SUSPENDED', 'PORT_OFFBOARDED', 'LICENSE_NOT_YET_IN_EFFECT') NOT NULL DEFAULT 'NO_LICENSE', `` |
| ADD | `` `port_status` ENUM ('ONBOARDING', 'ACTIVE', 'SUSPENDED', 'OFFBOARDED'), `` |
| ADD | `` `license_status` ENUM ('PENDING', 'ACTIVE', 'SUSPENDED', 'EXPIRED', 'REVOKED'), `` |
| ADD | `` `status_received_at` datetime, `` |
| CHECK comment | `chk_license_state_reason` becomes: `(mode IN ('FULL','SETUP')) = (read_only_reason = 'NONE')` |

---

## 3. Port DB: new tables

```sql
CREATE TABLE `user_device` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `device_id` varchar(100) NOT NULL,
  `platform` ENUM ('ANDROID', 'IOS') NOT NULL,
  `app_version` varchar(20) NOT NULL,
  `last_seen_at` datetime,
  `revoked_at` datetime COMMENT 'Never deleted: set to revoke',
  UNIQUE KEY `uq_user_device` (`user_id`, `device_id`),
  CONSTRAINT `fk_device_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
);

CREATE TABLE `part1_run` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `berthing_request_id` bigint NOT NULL,
  `status` ENUM ('QUEUED', 'RUNNING', 'DONE', 'FAILED', 'REPLACED') NOT NULL DEFAULT 'QUEUED',
  `outcome` ENUM ('WINDOWS_FOUND', 'NO_FEASIBLE_WINDOW', 'ENV_DATA_UNAVAILABLE'),
  `retry_count` tinyint NOT NULL DEFAULT 0,
  `entry_window_proposal_id` bigint COMMENT 'Set only when windows are found',
  `queued_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `started_at` datetime,
  `finished_at` datetime,
  CONSTRAINT `fk_p1r_request` FOREIGN KEY (`berthing_request_id`) REFERENCES `berthing_request` (`id`),
  CONSTRAINT `fk_p1r_proposal` FOREIGN KEY (`entry_window_proposal_id`) REFERENCES `entry_window_proposal` (`id`),
  CONSTRAINT `chk_p1r_retry` CHECK (`retry_count` BETWEEN 0 AND 3)
);

CREATE TABLE `recommendation_review` (
  `recommendation_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `viewed_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  PRIMARY KEY (`recommendation_id`, `user_id`, `viewed_at`),
  CONSTRAINT `fk_rrv_rec` FOREIGN KEY (`recommendation_id`) REFERENCES `recommendation` (`id`),
  CONSTRAINT `fk_rrv_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
);
```

```dbml
Table user_device {
  id bigint [pk, increment]
  user_id bigint [not null, ref: > user.id]
  device_id varchar(100) [not null]
  platform device_platform [not null]   // Enum device_platform { ANDROID  IOS }
  app_version varchar(20) [not null]
  last_seen_at datetime
  revoked_at datetime [note: 'Never deleted: set to revoke']
  indexes { (user_id, device_id) [unique] }
}

Table part1_run {
  id bigint [pk, increment]
  berthing_request_id bigint [not null, ref: > berthing_request.id]
  status part1_run_status [not null, default: 'QUEUED']        // QUEUED RUNNING DONE FAILED REPLACED
  outcome part1_outcome                                         // WINDOWS_FOUND NO_FEASIBLE_WINDOW ENV_DATA_UNAVAILABLE
  retry_count tinyint [not null, default: 0, note: '0..3']
  entry_window_proposal_id bigint [ref: > entry_window_proposal.id]
  queued_at datetime [not null, default: `now()`]
  started_at datetime
  finished_at datetime
}

Table recommendation_review {
  recommendation_id bigint [not null, ref: > recommendation.id]
  user_id bigint [not null, ref: > user.id]
  viewed_at datetime [not null, default: `now()`]
  indexes { (recommendation_id, user_id, viewed_at) [pk] }
}
```

Note for documentation only (not in `PortSync.sql`): Laravel creates `personal_access_tokens` (add `revoked_at datetime NULL` in its migration) and `sessions`.

---

## 4. SaaS DB (`SaaS.sql`)

| Table | Change | Line |
|---|---|---|
| `saas_admin` | ADD | `` `roles` SET ('PLATFORM', 'LICENSE', 'BILLING') NOT NULL DEFAULT '' COMMENT 'Logical admin roles; one admin may hold several', `` |
| `license` | ADD | `` `on_hold` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'PENDING renewal held while the current licence is SUSPENDED', `` |
| `license` | ADD (missing, the comment already promises it) | `` `active_port_key` bigint GENERATED ALWAYS AS (IF(`status` = 'ACTIVE', `port_registry_id`, NULL)) STORED UNIQUE, `` |
| `billing_transaction` | ADD | `` `live_period_key` date GENERATED ALWAYS AS (IF(`status` IN ('PENDING','FAILED','PAID'), `period_start`, NULL)) STORED, `` |
| `billing_transaction` | FIND | ``CREATE UNIQUE INDEX `uq_billing_license_period` ON `billing_transaction` (`license_id`, `period_start`);`` |
| | REPLACE | ``CREATE UNIQUE INDEX `uq_billing_license_live_period` ON `billing_transaction` (`license_id`, `live_period_key`);`` |
| `license.status` | No change | `SUPERSEDED` stays |

DBML (SaaS): `roles varchar [note: 'SET: PLATFORM, LICENSE, BILLING']` (DBML has no SET type), `on_hold boolean [not null, default: false]`, generated keys as columns with a `GENERATED` note.

---

## 5. Not changed (decided, do not add)

`row_version` (handled in code) · `is_trusted`, resolution-note columns · `token_iat` · `notification_log.attempt_no` · `solver_run.failure_reason`, binding-factor table · `sync_rejected_event` · TLS certificate table · renames of `parse_status`, `client_event_id`, `reported_at`.

## 6. After editing

1. Create both databases from the edited files.
2. Run `PortSync_base_ddl_fixes.sql`. In part 2, skip the `operational_plan.active_key` block, because section 2.11 already writes it as a generated column. Keep every other block.
3. Update the counts in `PortSync.md`, `SaaS.md` and the Master Context: 90 Port + 8 SaaS = 98 tables.
