<!-- Generated from docs/reference/database/PortSync.sql lines 143-150 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `port_shift` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(20) UNIQUE NOT NULL,
  `name` varchar(60) NOT NULL,
  `start_time` time NOT NULL COMMENT 'Port local time (port_profile.timezone)',
  `end_time` time NOT NULL COMMENT 'If end_time < start_time the shift ends the next day',
  `is_active` tinyint(1) NOT NULL DEFAULT 1
);

<!-- Generated from docs/reference/database/PortSync.md lines 507-528 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `port_shift`

> Fixed port shifts, defined on the settings screen. App rule: active shifts must not overlap.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(20)` | NO |  | UQ |  |
| `name` | `varchar(60)` | NO |  |  |  |
| `start_time` | `time` | NO |  |  | Port local time (port_profile.timezone) |
| `end_time` | `time` | NO |  |  | If end_time < start_time the shift ends the next day |
| `is_active` | `tinyint(1)` | NO | `1` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_port_shift_times` | `(end_time <> start_time)` |

**Referenced by:** [`pilot_schedule`](#pilot_schedule).port_shift_id, [`tug_master_schedule`](#tug_master_schedule).port_shift_id

---
