<!-- Generated from docs/reference/database/PortSync.sql lines 10-30 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `license_state` (
  `id` tinyint PRIMARY KEY DEFAULT 1,
  `token` text COMMENT 'Current signed licence token',
  `license_ref` bigint COMMENT 'SaaS license.id (no FK, other DB)',
  `plan_name` varchar(80),
  `max_terminals` int,
  `max_berths` int,
  `max_users` int,
  `license_expires_at` datetime,
  `token_expires_at` datetime,
  `port_status` ENUM ('ONBOARDING', 'ACTIVE', 'SUSPENDED', 'OFFBOARDED'),
  `license_status` ENUM ('PENDING', 'ACTIVE', 'SUSPENDED', 'EXPIRED', 'REVOKED'),
  `status_received_at` datetime,
  `last_check_in_attempt_at` datetime,
  `last_successful_check_in_at` datetime,
  `last_check_in_error` varchar(255),
  `max_seen_time` datetime COMMENT 'Clock-rollback guard',
  `mode` ENUM ('SETUP', 'FULL', 'READ_ONLY') NOT NULL DEFAULT 'READ_ONLY',
  `read_only_reason` ENUM ('NONE', 'NO_LICENSE', 'TOKEN_EXPIRED', 'SUSPENDED', 'REVOKED', 'INVALID_SIGNATURE', 'CLOCK_ROLLBACK', 'PORT_SUSPENDED', 'PORT_OFFBOARDED', 'LICENSE_NOT_YET_IN_EFFECT') NOT NULL DEFAULT 'NO_LICENSE',
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

<!-- Generated from docs/reference/database/PortSync.md lines 147-180 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `license_state`

> Local copy of the SaaS licence (hybrid signed token). Public key and API credential live in config, not here.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `tinyint` | NO | `1` | PK |  |
| `token` | `text` | YES |  |  | Current signed licence token |
| `license_ref` | `bigint` | YES |  |  | SaaS license.id (no FK, other DB) |
| `plan_name` | `varchar(80)` | YES |  |  |  |
| `max_terminals` | `int` | YES |  |  |  |
| `max_berths` | `int` | YES |  |  |  |
| `max_users` | `int` | YES |  |  |  |
| `license_expires_at` | `datetime` | YES |  |  |  |
| `token_expires_at` | `datetime` | YES |  |  |  |
| `port_status` | `ENUM(ONBOARDING, ACTIVE, SUSPENDED, OFFBOARDED)` | YES |  |  |  |
| `license_status` | `ENUM(PENDING, ACTIVE, SUSPENDED, EXPIRED, REVOKED)` | YES |  |  |  |
| `status_received_at` | `datetime` | YES |  |  |  |
| `last_check_in_attempt_at` | `datetime` | YES |  |  |  |
| `last_successful_check_in_at` | `datetime` | YES |  |  |  |
| `last_check_in_error` | `varchar(255)` | YES |  |  |  |
| `max_seen_time` | `datetime` | YES |  |  | Clock-rollback guard |
| `mode` | `ENUM(SETUP, FULL, READ_ONLY)` | NO | `'READ_ONLY'` |  |  |
| `read_only_reason` | `ENUM(NONE, NO_LICENSE, TOKEN_EXPIRED, SUSPENDED, REVOKED, INVALID_SIGNATURE, CLOCK_ROLLBACK, PORT_SUSPENDED, PORT_OFFBOARDED, LICENSE_NOT_YET_IN_EFFECT)` | NO | `'NO_LICENSE'` |  |  |
| `updated_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_license_state_reason` | `((mode IN ('FULL','SETUP')) = (read_only_reason = 'NONE'))` |
| `chk_license_state_singleton` | `(id = 1)` |

---
