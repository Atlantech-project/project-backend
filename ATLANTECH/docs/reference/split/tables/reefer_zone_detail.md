<!-- Generated from docs/reference/database/PortSync.sql lines 310-315 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `reefer_zone_detail` (
  `yard_zone_id` bigint PRIMARY KEY,
  `zone_detail` ENUM ('NONE', 'REEFER', 'DANGEROUS_GOODS', 'OOG') NOT NULL DEFAULT 'REEFER',
  `total_power_plugs` int NOT NULL COMMENT 'Free plugs = total - reefer containers in the zone (derived)',
  `power_type` varchar(30) COMMENT 'Display only, e.g. 380-460V 3-phase'
);

<!-- Generated from docs/reference/database/PortSync.md lines 1052-1080 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `reefer_zone_detail`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `yard_zone_id` | `bigint` | NO |  | PK · FK → `yard_zone.id, zone_detail` |  |
| `zone_detail` | `ENUM(NONE, REEFER, DANGEROUS_GOODS, OOG)` | NO | `'REEFER'` | FK → `yard_zone.id, zone_detail` |  |
| `total_power_plugs` | `int` | NO |  |  | Free plugs = total - reefer containers in the zone (derived) |
| `power_type` | `varchar(30)` | YES |  |  | Display only, e.g. 380-460V 3-phase |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_rzd_plugs` | `(total_power_plugs > 0)` |
| `chk_rzd_type` | `(zone_detail = 'REEFER')` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_rzd_zone` | No | yard_zone_id, zone_detail |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_rzd_zone` | yard_zone_id, zone_detail | [`yard_zone`](#yard_zone) (id, zone_detail) |  |

---
