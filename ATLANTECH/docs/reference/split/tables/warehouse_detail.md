<!-- Generated from docs/reference/database/PortSync.sql lines 286-292 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `warehouse_detail` (
  `storage_facility_id` bigint PRIMARY KEY,
  `detail_kind` ENUM ('NONE', 'WAREHOUSE', 'CONTAINER_YARD', 'TANK') NOT NULL DEFAULT 'WAREHOUSE' COMMENT 'Same ENUM as parent (FK compares ENUM index)',
  `min_temperature` decimal(5,2) COMMENT 'Â°C',
  `max_temperature` decimal(5,2) COMMENT 'Â°C',
  `temperature_monitoring` tinyint(1) NOT NULL DEFAULT 0
);

<!-- Generated from docs/reference/database/PortSync.md lines 957-986 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `warehouse_detail`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `storage_facility_id` | `bigint` | NO |  | PK · FK → `storage_facility.id, detail_kind` |  |
| `detail_kind` | `ENUM(NONE, WAREHOUSE, CONTAINER_YARD, TANK)` | NO | `'WAREHOUSE'` | FK → `storage_facility.id, detail_kind` | Same ENUM as parent (FK compares ENUM index) |
| `min_temperature` | `decimal(5,2)` | YES |  |  | Â°C |
| `max_temperature` | `decimal(5,2)` | YES |  |  | Â°C |
| `temperature_monitoring` | `tinyint(1)` | NO | `0` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_wd_kind` | `(detail_kind = 'WAREHOUSE')` |
| `chk_wd_temp` | `((min_temperature is null) or (max_temperature is null) or (min_temperature <= max_temperature))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_wd_facility` | No | storage_facility_id, detail_kind |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_wd_facility` | storage_facility_id, detail_kind | [`storage_facility`](#storage_facility) (id, detail_kind) |  |

---
