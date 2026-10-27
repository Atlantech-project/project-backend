<!-- Generated from docs/reference/database/PortSync.sql lines 157-163 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `tug_master_schedule` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `tug_master_user_id` bigint NOT NULL,
  `shift_date` date NOT NULL COMMENT 'Port local date the shift starts on',
  `port_shift_id` bigint NOT NULL,
  `created_by` bigint NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 550-579 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `tug_master_schedule`

> Which tug master works which fixed shift on which day.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `tug_master_user_id` | `bigint` | NO |  | FK → `tug_master_profile.user_id` |  |
| `shift_date` | `date` | NO |  |  | Port local date the shift starts on |
| `port_shift_id` | `bigint` | NO |  | FK → `port_shift.id` |  |
| `created_by` | `bigint` | NO |  | FK → `user.id` |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_tms_created_by` | No | created_by |
| `fk_tms_shift` | No | port_shift_id |
| `idx_tug_master_schedule_date` | No | shift_date, port_shift_id |
| `uq_tug_master_schedule` | Yes | tug_master_user_id, shift_date, port_shift_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_tms_created_by` | created_by | [`user`](#user) (id) |  |
| `fk_tms_master` | tug_master_user_id | [`tug_master_profile`](#tug_master_profile) (user_id) |  |
| `fk_tms_shift` | port_shift_id | [`port_shift`](#port_shift) (id) |  |

---
