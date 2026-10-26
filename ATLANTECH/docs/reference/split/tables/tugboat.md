<!-- Generated from docs/reference/database/PortSync.sql lines 165-172 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `tugboat` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(100) UNIQUE NOT NULL,
  `tug_power` decimal(8,2) NOT NULL,
  `tug_power_unit` ENUM ('BOLLARD_PULL_TONNES', 'HP', 'KW') NOT NULL COMMENT 'Chosen by the Tug Manager',
  `capacity` decimal(8,2) COMMENT 'Kept as in source (P4-03 rejected)',
  `status` ENUM ('AVAILABLE', 'BUSY', 'MAINTENANCE', 'INACTIVE') NOT NULL DEFAULT 'AVAILABLE' COMMENT 'BUSY for the plan duration; Tug Master sets it back to AVAILABLE from the tablet; Tug Officer notified if still BUSY after planned_end'
);

<!-- Generated from docs/reference/database/PortSync.md lines 581-602 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `tugboat`

> Port tug fleet (shared Marine Services resource).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `name` | `varchar(100)` | NO |  | UQ |  |
| `tug_power` | `decimal(8,2)` | NO |  |  |  |
| `tug_power_unit` | `ENUM(BOLLARD_PULL_TONNES, HP, KW)` | NO |  |  | Chosen by the Tug Manager |
| `capacity` | `decimal(8,2)` | YES |  |  | Kept as in source (P4-03 rejected) |
| `status` | `ENUM(AVAILABLE, BUSY, MAINTENANCE, INACTIVE)` | NO | `'AVAILABLE'` |  | BUSY for the plan duration; Tug Master sets it back to AVAILABLE from the tablet; Tug Officer notified if still BUSY after planned_end |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_tugboat_power` | `(tug_power > 0)` |

**Referenced by:** [`tug_master_profile`](#tug_master_profile).assigned_tugboat_id, [`recommendation_option_tug`](#recommendation_option_tug).tugboat_id, [`recommendation_override`](#recommendation_override).override_tugboat_id, [`recommendation_override`](#recommendation_override).original_tugboat_id, [`plan_component`](#plan_component).tugboat_id

---
