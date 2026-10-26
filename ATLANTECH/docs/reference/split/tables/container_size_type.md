<!-- Generated from docs/reference/database/PortSync.sql lines 180-188 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `container_size_type` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` char(4) UNIQUE NOT NULL COMMENT 'ISO 6346 size-type code, e.g. 22G1, 45R1',
  `length_ft` int NOT NULL,
  `is_high_cube` tinyint(1) NOT NULL DEFAULT 0 COMMENT '9''6" high',
  `type_group` ENUM ('GENERAL', 'REEFER', 'TANK', 'OPEN_TOP', 'FLAT_RACK', 'VENTILATED', 'BULK') NOT NULL,
  `teu_factor` decimal(3,2) NOT NULL,
  `is_reefer` tinyint(1) COMMENT 'GENERATED (stored): (type_group = _latin1''REEFER'')'
);

<!-- Generated from docs/reference/database/PortSync.md lines 636-657 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `container_size_type`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `char(4)` | NO |  | UQ | ISO 6346 size-type code, e.g. 22G1, 45R1 |
| `length_ft` | `int` | NO |  |  |  |
| `is_high_cube` | `tinyint(1)` | NO | `0` |  | 9'6" high |
| `type_group` | `ENUM(GENERAL, REEFER, TANK, OPEN_TOP, FLAT_RACK, VENTILATED, BULK)` | NO |  |  |  |
| `teu_factor` | `decimal(3,2)` | NO |  |  |  |
| `is_reefer` | `tinyint(1)` | YES |  |  | GENERATED (stored): (type_group = 'REEFER') |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_cst_length` | `(length_ft in (10,20,40,45))` |
| `chk_cst_teu` | `(teu_factor > 0)` |

**Referenced by:** [`container`](#container).size_type_id

---
