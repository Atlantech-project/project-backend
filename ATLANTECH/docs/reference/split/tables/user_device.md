<!-- Generated from docs/reference/database/PortSync.sql lines 911-919 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `user_device` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `device_id` varchar(100) NOT NULL,
  `platform` ENUM ('ANDROID', 'IOS') NOT NULL,
  `app_version` varchar(20) NOT NULL,
  `last_seen_at` datetime,
  `revoked_at` datetime COMMENT 'Never deleted: set to revoke'
);

<!-- Generated from docs/reference/database/PortSync.md lines 2949-2973 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `user_device`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `user_id` | `bigint` | NO |  | FK → `user.id` |  |
| `device_id` | `varchar(100)` | NO |  |  |  |
| `platform` | `ENUM(ANDROID, IOS)` | NO |  |  |  |
| `app_version` | `varchar(20)` | NO |  |  |  |
| `last_seen_at` | `datetime` | YES |  |  |  |
| `revoked_at` | `datetime` | YES |  |  | Never deleted: set to revoke |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_user_device` | Yes | user_id, device_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| — | user_id | [`user`](#user) (id) |  |

---
