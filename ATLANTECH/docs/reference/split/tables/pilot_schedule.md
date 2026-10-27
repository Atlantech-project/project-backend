<!-- Generated from docs/reference/database/PortSync.sql lines 135-141 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `pilot_schedule` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `pilot_user_id` bigint NOT NULL,
  `shift_date` date NOT NULL COMMENT 'Port local date the shift starts on',
  `port_shift_id` bigint NOT NULL,
  `created_by` bigint NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 476-505 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `pilot_schedule`

> Which pilot works which fixed shift on which day.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `pilot_user_id` | `bigint` | NO |  | FK → `pilot_profile.user_id` |  |
| `shift_date` | `date` | NO |  |  | Port local date the shift starts on |
| `port_shift_id` | `bigint` | NO |  | FK → `port_shift.id` |  |
| `created_by` | `bigint` | NO |  | FK → `user.id` |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_pilot_schedule_created_by` | No | created_by |
| `fk_pilot_schedule_shift` | No | port_shift_id |
| `idx_pilot_schedule_date` | No | shift_date, port_shift_id |
| `uq_pilot_schedule` | Yes | pilot_user_id, shift_date, port_shift_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_pilot_schedule_created_by` | created_by | [`user`](#user) (id) |  |
| `fk_pilot_schedule_pilot` | pilot_user_id | [`pilot_profile`](#pilot_profile) (user_id) |  |
| `fk_pilot_schedule_shift` | port_shift_id | [`port_shift`](#port_shift) (id) |  |

---
