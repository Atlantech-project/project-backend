<!-- Generated from docs/reference/database/PortSync.sql lines 152-155 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `tug_master_profile` (
  `user_id` bigint PRIMARY KEY,
  `assigned_tugboat_id` bigint UNIQUE COMMENT 'NULL = explicitly unassigned; UNIQUE = one master per tug (FR-1.1.2)'
);

<!-- Generated from docs/reference/database/PortSync.md lines 530-548 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `tug_master_profile`

> 1:1 with user. App rule: user must hold TUG_MASTER in MARINE_SERVICES (D-29).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `user_id` | `bigint` | NO |  | PK · FK → `user.id` |  |
| `assigned_tugboat_id` | `bigint` | YES |  | UQ · FK → `tugboat.id` | NULL = explicitly unassigned; UNIQUE = one master per tug (FR-1.1.2) |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_tug_master_tugboat` | assigned_tugboat_id | [`tugboat`](#tugboat) (id) |  |
| `fk_tug_master_user` | user_id | [`user`](#user) (id) |  |

**Referenced by:** [`tug_master_schedule`](#tug_master_schedule).tug_master_user_id

---
