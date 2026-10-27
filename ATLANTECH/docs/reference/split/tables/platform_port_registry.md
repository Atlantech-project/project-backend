<!-- Generated from docs/reference/database/SaaS.sql lines 25-40 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `platform_port_registry` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `public_id` char(36) UNIQUE NOT NULL COMMENT 'Stable UUID shared with the Port deployment and carried in licence tokens',
  `port_code` varchar(10) UNIQUE COMMENT 'UN/LOCODE, e.g. EGALY',
  `port_name` varchar(150) NOT NULL,
  `governorate_id` smallint NOT NULL,
  `server_endpoint` varchar(255) NOT NULL COMMENT 'Management/support address of the port server (e.g. VPN). Not the app URL; no runtime calls',
  `api_credential_hash` varchar(255) COMMENT 'Credential hash for the daily licence pull',
  `api_credential_rotated_at` datetime,
  `last_check_in_at` datetime COMMENT 'Last successful licence pull',
  `onboarded_by` bigint NOT NULL,
  `onboarded_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `status` ENUM ('ONBOARDING', 'ACTIVE', 'SUSPENDED', 'OFFBOARDED') NOT NULL DEFAULT 'ONBOARDING',
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

<!-- Generated from docs/reference/database/SaaS.md lines 76-113 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `platform_port_registry`

> Customer ports / deployments. Never deleted: OFFBOARDED.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `public_id` | `char(36)` | NO |  | UQ | Stable UUID shared with the Port deployment and carried in licence tokens |
| `port_code` | `varchar(10)` | YES |  | UQ | UN/LOCODE, e.g. EGALY |
| `port_name` | `varchar(150)` | NO |  |  |  |
| `governorate_id` | `smallint` | NO |  | FK → `governorate.id` |  |
| `server_endpoint` | `varchar(255)` | NO |  |  | Management/support address of the port server (e.g. VPN). Not the app URL; no runtime calls |
| `api_credential_hash` | `varchar(255)` | YES |  |  | Credential hash for the daily licence pull |
| `api_credential_rotated_at` | `datetime` | YES |  |  |  |
| `last_check_in_at` | `datetime` | YES |  |  | Last successful licence pull |
| `onboarded_by` | `bigint` | NO |  | FK → `saas_admin.id` |  |
| `onboarded_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `status` | `ENUM(ONBOARDING, ACTIVE, SUSPENDED, OFFBOARDED)` | NO | `'ONBOARDING'` |  |  |
| `created_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `updated_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `idx_port_status` | No | status |
| `idx_port_governorate` | No | governorate_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| — | governorate_id | [`governorate`](#governorate) (id) |  |
| — | onboarded_by | [`saas_admin`](#saas_admin) (id) |  |

**Referenced by:** [`platform_audit_log`](#platform_audit_log).target_port_registry_id, [`license`](#license).port_registry_id

---
