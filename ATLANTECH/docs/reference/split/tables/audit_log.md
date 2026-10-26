<!-- Generated from docs/reference/database/PortSync.sql lines 887-896 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `audit_log` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `actor_id` bigint COMMENT 'NULL = system / AI worker',
  `entity_type` varchar(60) NOT NULL COMMENT 'Table name, e.g. safety_constraint, user_role_assignment',
  `entity_id` bigint COMMENT 'NULL when a refused attempt has no entity (e.g. failed login)',
  `action` ENUM ('CREATE', 'UPDATE', 'DELETE', 'STATUS_CHANGE', 'SAFETY_EXCEPTION', 'REJECTED_ATTEMPT') NOT NULL,
  `before_state` json,
  `after_state` json,
  `occurred_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

<!-- Generated from docs/reference/database/PortSync.md lines 2875-2910 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `audit_log`

> Append-only: the application DB user gets INSERT + SELECT only on this table (P21-03).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `actor_id` | `bigint` | YES |  | FK → `user.id` | NULL = system / AI worker |
| `entity_type` | `varchar(60)` | NO |  |  | Table name, e.g. safety_constraint, user_role_assignment |
| `entity_id` | `bigint` | YES |  |  | NULL when a refused attempt has no entity (e.g. failed login) |
| `action` | `ENUM(CREATE, UPDATE, DELETE, STATUS_CHANGE, SAFETY_EXCEPTION, REJECTED_ATTEMPT)` | NO |  |  |  |
| `before_state` | `json` | YES |  |  |  |
| `after_state` | `json` | YES |  |  |  |
| `occurred_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_al_states` | `(((action <> 'CREATE') or (before_state is null)) and ((action <> 'DELETE') or (after_state is null)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_al_user` | No | actor_id |
| `idx_al_entity` | No | entity_type, entity_id |
| `idx_al_time` | No | occurred_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_al_user` | actor_id | [`user`](#user) (id) |  |

---
