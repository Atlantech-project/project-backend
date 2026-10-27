<!-- Generated from docs/reference/database/SaaS.sql lines 70-80 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `saas_admin` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(150) UNIQUE NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `roles` varchar(255) NOT NULL DEFAULT '' COMMENT 'SET(PLATFORM, LICENSE, BILLING) - logical admin roles; one admin may hold several (DBML has no SET type)',
  `is_active` boolean NOT NULL DEFAULT true,
  `last_login_at` datetime,
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

<!-- Generated from docs/reference/database/SaaS.md lines 192-210 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `saas_admin`

> ATLANTECH platform administrators. Never deleted: deactivate with is_active.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `name` | `varchar(120)` | NO |  |  |  |
| `email` | `varchar(150)` | NO |  | UQ |  |
| `password_hash` | `varchar(255)` | NO |  |  |  |
| `roles` | `varchar(255)` | NO | `''` |  | SET(PLATFORM, LICENSE, BILLING) - logical admin roles; one admin may hold several (DBML has no SET type) |
| `is_active` | `boolean` | NO | `true` |  |  |
| `last_login_at` | `datetime` | YES |  |  |  |
| `created_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `updated_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Referenced by:** [`platform_port_registry`](#platform_port_registry).onboarded_by, [`platform_audit_log`](#platform_audit_log).saas_admin_id

---
