<!-- Generated from docs/reference/database/PortSync.sql lines 68-79 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `berth` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(30) UNIQUE NOT NULL,
  `name` varchar(80),
  `length` decimal(7,2) NOT NULL COMMENT 'metres',
  `water_depth` decimal(6,2) NOT NULL COMMENT 'Water depth alongside the berth (m) below depth_datum',
  `depth_datum` varchar(40) NOT NULL COMMENT 'e.g. LAT; must equal navigation_channel.depth_datum and the active tide datum',
  `max_vessel_loa` decimal(7,2) NOT NULL COMMENT 'metres',
  `status` ENUM ('ACTIVE', 'MAINTENANCE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE' COMMENT 'Administrative only. Reserved/occupied is derived from plan_component and actual status',
  `latitude` decimal(9,6),
  `longitude` decimal(9,6)
);

<!-- Generated from docs/reference/database/PortSync.md lines 260-292 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `berth`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(30)` | NO |  | UQ |  |
| `name` | `varchar(80)` | YES |  |  |  |
| `length` | `decimal(7,2)` | NO |  |  | metres |
| `water_depth` | `decimal(6,2)` | NO |  |  | Water depth alongside the berth (m) below depth_datum |
| `depth_datum` | `varchar(40)` | NO |  |  | e.g. LAT; must equal navigation_channel.depth_datum and the active tide datum |
| `max_vessel_loa` | `decimal(7,2)` | NO |  |  | metres |
| `status` | `ENUM(ACTIVE, MAINTENANCE, INACTIVE)` | NO | `'ACTIVE'` |  | Administrative only. Reserved/occupied is derived from plan_component and actual status |
| `latitude` | `decimal(9,6)` | YES |  |  |  |
| `longitude` | `decimal(9,6)` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_berth_dimensions` | `((length > 0) and (water_depth > 0) and (max_vessel_loa > 0))` |
| `chk_berth_lat` | `((latitude is null) or (latitude between -(90) and 90))` |
| `chk_berth_loa_fits` | `(max_vessel_loa <= length)` |
| `chk_berth_lon` | `((longitude is null) or (longitude between -(180) and 180))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `idx_berth_status` | No | status |

**Referenced by:** [`berth_terminal`](#berth_terminal).berth_id, [`safety_constraint`](#safety_constraint).berth_id, [`recommendation_override`](#recommendation_override).override_berth_id, [`recommendation_override`](#recommendation_override).original_berth_id, [`plan_component`](#plan_component).berth_id

---
