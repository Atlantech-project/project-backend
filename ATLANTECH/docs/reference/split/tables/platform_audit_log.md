<!-- Generated from docs/reference/database/SaaS.sql lines 56-68 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `platform_audit_log` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `actor_type` ENUM ('ADMIN', 'SYSTEM') NOT NULL DEFAULT 'ADMIN',
  `saas_admin_id` bigint COMMENT 'Required when actor_type = ADMIN',
  `action` varchar(60) NOT NULL COMMENT 'Controlled vocabulary in the application',
  `target_port_registry_id` bigint,
  `target_type` varchar(40) COMMENT 'e.g. license, license_plan, billing_transaction',
  `target_id` bigint,
  `details` json,
  `occurred_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  CONSTRAINT `chk_audit_actor` CHECK (actor_type = 'SYSTEM' OR saas_admin_id IS NOT NULL),
  CONSTRAINT `chk_audit_target_pair` CHECK ((target_type IS NULL) = (target_id IS NULL))
);

<!-- Generated from docs/reference/database/SaaS.md lines 152-190 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `platform_audit_log`

> Append-only platform audit trail.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `actor_type` | `ENUM(ADMIN, SYSTEM)` | NO | `'ADMIN'` |  |  |
| `saas_admin_id` | `bigint` | YES |  | FK → `saas_admin.id` | Required when actor_type = ADMIN |
| `action` | `varchar(60)` | NO |  |  | Controlled vocabulary in the application |
| `target_port_registry_id` | `bigint` | YES |  | FK → `platform_port_registry.id` |  |
| `target_type` | `varchar(40)` | YES |  |  | e.g. license, license_plan, billing_transaction |
| `target_id` | `bigint` | YES |  |  |  |
| `details` | `json` | YES |  |  |  |
| `occurred_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_audit_actor` | `actor_type = 'SYSTEM' OR saas_admin_id IS NOT NULL` |
| `chk_audit_target_pair` | `(target_type IS NULL) = (target_id IS NULL)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `idx_audit_port_time` | No | target_port_registry_id, occurred_at |
| `idx_audit_target` | No | target_type, target_id |
| `idx_audit_time` | No | occurred_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| — | saas_admin_id | [`saas_admin`](#saas_admin) (id) |  |
| — | target_port_registry_id | [`platform_port_registry`](#platform_port_registry) (id) |  |

---
