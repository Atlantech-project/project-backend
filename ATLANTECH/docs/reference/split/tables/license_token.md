<!-- Generated from docs/reference/database/SaaS.sql lines 42-54 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `license_token` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `license_id` bigint NOT NULL,
  `jti` char(36) UNIQUE NOT NULL COMMENT 'Token id inside the signed token',
  `issued_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `expires_at` datetime NOT NULL COMMENT '~35 days, <= license.expires_at',
  `payload` json NOT NULL COMMENT 'Copy of signed claims',
  `token_hash` char(64) UNIQUE NOT NULL COMMENT 'SHA-256 of signed token; signing key not in DB',
  `delivery_method` ENUM ('PULL', 'MANUAL') NOT NULL,
  `delivered_at` datetime COMMENT 'NULL = not yet collected by the port',
  `revoked_at` datetime,
  CONSTRAINT `chk_license_token_validity` CHECK (expires_at > issued_at)
);

<!-- Generated from docs/reference/database/SaaS.md lines 115-150 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `license_token`

> Signed licence tokens (hybrid: daily pull + manual fallback).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `license_id` | `bigint` | NO |  | FK → `license.id` |  |
| `jti` | `char(36)` | NO |  | UQ | Token id inside the signed token |
| `issued_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `expires_at` | `datetime` | NO |  |  | ~35 days, <= license.expires_at |
| `payload` | `json` | NO |  |  | Copy of signed claims |
| `token_hash` | `char(64)` | NO |  | UQ | SHA-256 of signed token; signing key not in DB |
| `delivery_method` | `ENUM(PULL, MANUAL)` | NO |  |  |  |
| `delivered_at` | `datetime` | YES |  |  | NULL = not yet collected by the port |
| `revoked_at` | `datetime` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_license_token_validity` | `expires_at > issued_at` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `idx_license_token_license_expires` | No | license_id, expires_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| — | license_id | [`license`](#license) (id) |  |

---
