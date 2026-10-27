<!-- Generated from docs/reference/database/PortSync.sql lines 125-128 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `pilot_profile` (
  `user_id` bigint PRIMARY KEY,
  `pilot_rank` ENUM ('FIRST', 'SECOND', 'THIRD') NOT NULL COMMENT 'FIRST = most senior'
);

<!-- Generated from docs/reference/database/PortSync.md lines 440-457 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `pilot_profile`

> 1:1 with user. App rule: user must hold MARINE_PILOT in MARINE_SERVICES (D-29).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `user_id` | `bigint` | NO |  | PK · FK → `user.id` |  |
| `pilot_rank` | `ENUM(FIRST, SECOND, THIRD)` | NO |  |  | FIRST = most senior |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_pilot_profile_user` | user_id | [`user`](#user) (id) |  |

**Referenced by:** [`pilot_schedule`](#pilot_schedule).pilot_user_id, [`recommendation_option`](#recommendation_option).pilot_user_id, [`recommendation_override`](#recommendation_override).override_pilot_user_id, [`recommendation_override`](#recommendation_override).original_pilot_user_id

---
