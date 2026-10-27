<!-- Generated from docs/reference/database/PortSync.sql lines 58-66 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `port_setting` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `setting_key` varchar(80) UNIQUE NOT NULL COMMENT 'reminder_lead_minutes, weather_pull_hours, retrain_interval_days ...',
  `setting_value` varchar(255) NOT NULL,
  `value_type` ENUM ('INT', 'DECIMAL', 'BOOL', 'STRING', 'JSON') NOT NULL,
  `description` varchar(255),
  `updated_by` bigint,
  `updated_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

<!-- Generated from docs/reference/database/PortSync.md lines 234-258 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `port_setting`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `setting_key` | `varchar(80)` | NO |  | UQ | reminder_lead_minutes, weather_pull_hours, retrain_interval_days ... |
| `setting_value` | `varchar(255)` | NO |  |  |  |
| `value_type` | `ENUM(INT, DECIMAL, BOOL, STRING, JSON)` | NO |  |  |  |
| `description` | `varchar(255)` | YES |  |  |  |
| `updated_by` | `bigint` | YES |  | FK → `user.id` |  |
| `updated_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_port_setting_updated_by` | No | updated_by |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_port_setting_updated_by` | updated_by | [`user`](#user) (id) |  |

---
