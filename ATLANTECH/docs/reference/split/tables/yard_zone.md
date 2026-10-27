<!-- Generated from docs/reference/database/PortSync.sql lines 333-340 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `yard_zone` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `container_yard_id` bigint NOT NULL COMMENT 'storage_facility.id of a CONTAINER_YARD',
  `detail_kind` ENUM ('NONE', 'WAREHOUSE', 'CONTAINER_YARD', 'TANK') NOT NULL DEFAULT 'CONTAINER_YARD' COMMENT 'Same ENUM as storage_facility (FK compares ENUM index)',
  `yard_zone_type_id` bigint NOT NULL,
  `zone_detail` ENUM ('NONE', 'REEFER', 'DANGEROUS_GOODS', 'OOG') NOT NULL COMMENT 'Copy of yard_zone_type.zone_detail, enforced by composite FK',
  `code` varchar(30) NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 1143-1180 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `yard_zone`

> Capacity/occupancy are derived from yard_slot.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `container_yard_id` | `bigint` | NO |  | FK → `storage_facility.id, detail_kind` | storage_facility.id of a CONTAINER_YARD |
| `detail_kind` | `ENUM(NONE, WAREHOUSE, CONTAINER_YARD, TANK)` | NO | `'CONTAINER_YARD'` | FK → `storage_facility.id, detail_kind` | Same ENUM as storage_facility (FK compares ENUM index) |
| `yard_zone_type_id` | `bigint` | NO |  | FK → `yard_zone_type.id, zone_detail` |  |
| `zone_detail` | `ENUM(NONE, REEFER, DANGEROUS_GOODS, OOG)` | NO |  | FK → `yard_zone_type.id, zone_detail` | Copy of yard_zone_type.zone_detail, enforced by composite FK |
| `code` | `varchar(30)` | NO |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_yz_kind` | `(detail_kind = 'CONTAINER_YARD')` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_yz_type` | No | yard_zone_type_id, zone_detail |
| `fk_yz_yard` | No | container_yard_id, detail_kind |
| `uq_yz_id_detail` | Yes | id, zone_detail |
| `uq_yz_yard_code` | Yes | container_yard_id, code |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_yz_type` | yard_zone_type_id, zone_detail | [`yard_zone_type`](#yard_zone_type) (id, zone_detail) |  |
| `fk_yz_yard` | container_yard_id, detail_kind | [`storage_facility`](#storage_facility) (id, detail_kind) |  |

**Referenced by:** [`dg_zone_allowed_class`](#dg_zone_allowed_class).yard_zone_id, zone_detail, [`oog_zone_detail`](#oog_zone_detail).yard_zone_id, zone_detail, [`reefer_zone_detail`](#reefer_zone_detail).yard_zone_id, zone_detail, [`yard_block`](#yard_block).yard_zone_id

---
