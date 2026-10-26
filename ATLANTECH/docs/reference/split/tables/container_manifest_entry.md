<!-- Generated from docs/reference/database/PortSync.sql lines 498-516 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `container_manifest_entry` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `baplie_file_id` bigint NOT NULL,
  `container_id` bigint NOT NULL,
  `full_empty` ENUM ('FULL', 'EMPTY') COMMENT 'NULL = missing in BAPLIE; staff fixes it (FR-1.2.5). Backend blocks CUSTOMS_APPROVED and yard recommendations while any line is incomplete',
  `weight_kg` int COMMENT 'Gross weight (VGM), kg',
  `imdg_class` varchar(3) COMMENT 'NULL = not dangerous',
  `oog_over_front_cm` int COMMENT 'BAPLIE DIM overhang, cm; NULL = none. Overall size = standard size (container_size_type) + overhangs, compared with oog_zone_detail',
  `oog_over_back_cm` int,
  `oog_over_right_cm` int,
  `oog_over_left_cm` int,
  `oog_over_height_cm` int,
  `reefer_set_temperature` decimal(5,2) COMMENT 'Â°C. Reefer (container_size_type.is_reefer) with NULL here = data error flagged by the parser',
  `stowage_bay` int,
  `stowage_row` int COMMENT '00 = centre line',
  `stowage_tier` int,
  `port_of_load` char(5) COMMENT 'UN/LOCODE',
  `port_of_discharge` char(5) COMMENT 'UN/LOCODE; = port_profile.port_code means discharged here'
);

<!-- Generated from docs/reference/database/PortSync.md lines 1660-1713 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `container_manifest_entry`

> Per-call container data from BAPLIE. Container identity in container (Module 5).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `baplie_file_id` | `bigint` | NO |  | FK → `baplie_file.id` |  |
| `container_id` | `bigint` | NO |  | FK → `container.id` |  |
| `full_empty` | `ENUM(FULL, EMPTY)` | YES |  |  | NULL = missing in BAPLIE; staff fixes it (FR-1.2.5). Backend blocks CUSTOMS_APPROVED and yard recommendations while any line is incomplete |
| `weight_kg` | `int` | YES |  |  | Gross weight (VGM), kg |
| `imdg_class` | `varchar(3)` | YES |  | FK → `imdg_class.code` | NULL = not dangerous |
| `oog_over_front_cm` | `int` | YES |  |  | BAPLIE DIM overhang, cm; NULL = none. Overall size = standard size (container_size_type) + overhangs, compared with oog_zone_detail |
| `oog_over_back_cm` | `int` | YES |  |  |  |
| `oog_over_right_cm` | `int` | YES |  |  |  |
| `oog_over_left_cm` | `int` | YES |  |  |  |
| `oog_over_height_cm` | `int` | YES |  |  |  |
| `reefer_set_temperature` | `decimal(5,2)` | YES |  |  | Â°C. Reefer (container_size_type.is_reefer) with NULL here = data error flagged by the parser |
| `stowage_bay` | `int` | YES |  |  |  |
| `stowage_row` | `int` | YES |  |  | 00 = centre line |
| `stowage_tier` | `int` | YES |  |  |  |
| `port_of_load` | `char(5)` | YES |  |  | UN/LOCODE |
| `port_of_discharge` | `char(5)` | YES |  |  | UN/LOCODE; = port_profile.port_code means discharged here |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_cme_oog` | `(((oog_over_front_cm is null) or (oog_over_front_cm > 0)) and ((oog_over_back_cm is null) or (oog_over_back_cm > 0)) and ((oog_over_right_cm is null) or (oog_over_right_cm > 0)) and ((oog_over_left_cm is null) or (oog_over_left_cm > 0)) and ((oog_over_height_cm is null) or (oog_over_height_cm > 0)))` |
| `chk_cme_pod` | `((port_of_discharge is null) or regexp_like(port_of_discharge,'^[A-Z]{2}[A-Z2-9]{3}$','c'))` |
| `chk_cme_pol` | `((port_of_load is null) or regexp_like(port_of_load,'^[A-Z]{2}[A-Z2-9]{3}$','c'))` |
| `chk_cme_reefer` | `((reefer_set_temperature is null) or (reefer_set_temperature between -(40) and 40))` |
| `chk_cme_stowage` | `(((stowage_bay is null) or (stowage_bay > 0)) and ((stowage_row is null) or (stowage_row >= 0)) and ((stowage_tier is null) or (stowage_tier > 0)))` |
| `chk_cme_weight` | `((weight_kg is null) or (weight_kg > 0))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_cme_container` | No | container_id |
| `fk_cme_imdg` | No | imdg_class |
| `uq_cme_file_container` | Yes | baplie_file_id, container_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_cme_container` | container_id | [`container`](#container) (id) |  |
| `fk_cme_file` | baplie_file_id | [`baplie_file`](#baplie_file) (id) |  |
| `fk_cme_imdg` | imdg_class | [`imdg_class`](#imdg_class) (code) |  |

**Referenced by:** [`yard_placement_item`](#yard_placement_item).manifest_entry_id

---
