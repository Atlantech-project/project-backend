<!-- Generated from docs/reference/database/PortSync.sql lines 301-308 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `oog_zone_detail` (
  `yard_zone_id` bigint PRIMARY KEY,
  `zone_detail` ENUM ('NONE', 'REEFER', 'DANGEROUS_GOODS', 'OOG') NOT NULL DEFAULT 'OOG',
  `max_length` decimal(6,2) COMMENT 'm',
  `max_width` decimal(6,2) COMMENT 'm',
  `max_height` decimal(6,2) COMMENT 'm',
  `max_weight` decimal(8,2) COMMENT 'tonnes'
);

<!-- Generated from docs/reference/database/PortSync.md lines 1020-1050 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `oog_zone_detail`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `yard_zone_id` | `bigint` | NO |  | PK · FK → `yard_zone.id, zone_detail` |  |
| `zone_detail` | `ENUM(NONE, REEFER, DANGEROUS_GOODS, OOG)` | NO | `'OOG'` | FK → `yard_zone.id, zone_detail` |  |
| `max_length` | `decimal(6,2)` | YES |  |  | m |
| `max_width` | `decimal(6,2)` | YES |  |  | m |
| `max_height` | `decimal(6,2)` | YES |  |  | m |
| `max_weight` | `decimal(8,2)` | YES |  |  | tonnes |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ozd_positive` | `(((max_length is null) or (max_length > 0)) and ((max_width is null) or (max_width > 0)) and ((max_height is null) or (max_height > 0)) and ((max_weight is null) or (max_weight > 0)))` |
| `chk_ozd_type` | `(zone_detail = 'OOG')` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_ozd_zone` | No | yard_zone_id, zone_detail |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ozd_zone` | yard_zone_id, zone_detail | [`yard_zone`](#yard_zone) (id, zone_detail) |  |

---
