<!-- Generated from docs/reference/database/SaaS.sql lines 82-102 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `license` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `port_registry_id` bigint NOT NULL,
  `license_plan_id` bigint NOT NULL,
  `max_terminals` int NOT NULL COMMENT 'Snapshot of plan at issue time',
  `max_berths` int NOT NULL COMMENT 'Snapshot',
  `max_users` int NOT NULL COMMENT 'Snapshot',
  `price_per_year` decimal(12,2) NOT NULL COMMENT 'Agreed annual price (snapshot)',
  `currency` char(3) NOT NULL COMMENT 'Snapshot',
  `issued_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP) COMMENT 'Administrative issue time',
  `valid_from` datetime NOT NULL COMMENT 'Start of validity; may be future for early renewals',
  `expires_at` datetime NOT NULL,
  `status` ENUM ('PENDING', 'ACTIVE', 'EXPIRED', 'SUSPENDED', 'REVOKED', 'SUPERSEDED') NOT NULL DEFAULT 'PENDING',
  `on_hold` boolean NOT NULL DEFAULT false COMMENT 'PENDING renewal held while the current licence is SUSPENDED',
  `active_port_key` bigint UNIQUE COMMENT 'GENERATED (stored): IF(status = ACTIVE, port_registry_id, NULL). One ACTIVE licence per port',
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  CONSTRAINT `chk_license_validity` CHECK (expires_at > valid_from),
  CONSTRAINT `chk_license_limits` CHECK (max_terminals >= 0 AND max_berths >= 0 AND max_users >= 0),
  CONSTRAINT `chk_license_price` CHECK (price_per_year >= 0)
);

<!-- Generated from docs/reference/database/SaaS.md lines 212-259 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `license`

> Many licences per port over time. At most ONE ACTIVE per port (enforced in SQL via generated column active_port_key + UNIQUE).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `port_registry_id` | `bigint` | NO |  | FK → `platform_port_registry.id` |  |
| `license_plan_id` | `bigint` | NO |  | FK → `license_plan.id` |  |
| `max_terminals` | `int` | NO |  |  | Snapshot of plan at issue time |
| `max_berths` | `int` | NO |  |  | Snapshot |
| `max_users` | `int` | NO |  |  | Snapshot |
| `price_per_year` | `decimal(12,2)` | NO |  |  | Agreed annual price (snapshot) |
| `currency` | `char(3)` | NO |  |  | Snapshot |
| `issued_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  | Administrative issue time |
| `valid_from` | `datetime` | NO |  |  | Start of validity; may be future for early renewals |
| `expires_at` | `datetime` | NO |  |  |  |
| `status` | `ENUM(PENDING, ACTIVE, EXPIRED, SUSPENDED, REVOKED, SUPERSEDED)` | NO | `'PENDING'` |  |  |
| `on_hold` | `boolean` | NO | `false` |  | PENDING renewal held while the current licence is SUSPENDED |
| `active_port_key` | `bigint` | YES |  | UQ | GENERATED (stored): IF(status = ACTIVE, port_registry_id, NULL). One ACTIVE licence per port |
| `created_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `updated_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_license_validity` | `expires_at > valid_from` |
| `chk_license_limits` | `max_terminals >= 0 AND max_berths >= 0 AND max_users >= 0` |
| `chk_license_price` | `price_per_year >= 0` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `idx_license_port_status` | No | port_registry_id, status |
| `idx_license_status_expires` | No | status, expires_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| — | port_registry_id | [`platform_port_registry`](#platform_port_registry) (id) |  |
| — | license_plan_id | [`license_plan`](#license_plan) (id) |  |

**Referenced by:** [`license_token`](#license_token).license_id, [`billing_transaction`](#billing_transaction).license_id

---
