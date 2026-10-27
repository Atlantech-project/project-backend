<!-- Generated from docs/reference/database/PortSync.sql lines 272-278 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `tank_detail` (
  `storage_facility_id` bigint PRIMARY KEY,
  `detail_kind` ENUM ('NONE', 'WAREHOUSE', 'CONTAINER_YARD', 'TANK') NOT NULL DEFAULT 'TANK' COMMENT 'Same ENUM as parent (FK compares ENUM index)',
  `min_temperature` decimal(5,2) COMMENT 'Â°C',
  `max_temperature` decimal(5,2) COMMENT 'Â°C',
  `max_pressure_bar` decimal(6,2) COMMENT 'Pressure limit (bar); NULL = atmospheric tank'
);

<!-- Generated from docs/reference/database/PortSync.md lines 899-929 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `tank_detail`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `storage_facility_id` | `bigint` | NO |  | PK · FK → `storage_facility.id, detail_kind` |  |
| `detail_kind` | `ENUM(NONE, WAREHOUSE, CONTAINER_YARD, TANK)` | NO | `'TANK'` | FK → `storage_facility.id, detail_kind` | Same ENUM as parent (FK compares ENUM index) |
| `min_temperature` | `decimal(5,2)` | YES |  |  | Â°C |
| `max_temperature` | `decimal(5,2)` | YES |  |  | Â°C |
| `max_pressure_bar` | `decimal(6,2)` | YES |  |  | Pressure limit (bar); NULL = atmospheric tank |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_td_kind` | `(detail_kind = 'TANK')` |
| `chk_td_pressure` | `((max_pressure_bar is null) or (max_pressure_bar > 0))` |
| `chk_td_temp` | `((min_temperature is null) or (max_temperature is null) or (min_temperature <= max_temperature))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_td_facility` | No | storage_facility_id, detail_kind |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_td_facility` | storage_facility_id, detail_kind | [`storage_facility`](#storage_facility) (id, detail_kind) |  |

---
