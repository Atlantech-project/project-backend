<!-- Generated from docs/reference/database/PortSync.sql lines 210-218 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `cargo_type` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(30) UNIQUE NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` ENUM ('GENERAL_CARGO', 'BREAK_BULK', 'DRY_BULK', 'LIQUID_BULK', 'GAS', 'RO_RO', 'CONTAINERIZED') NOT NULL,
  `imdg_class` varchar(3) COMMENT 'NULL = not dangerous',
  `min_temperature` decimal(5,2) COMMENT 'Â°C; NULL = no temperature requirement',
  `max_temperature` decimal(5,2) COMMENT 'Â°C; NULL = no temperature requirement'
);

<!-- Generated from docs/reference/database/PortSync.md lines 707-739 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `cargo_type`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(30)` | NO |  | UQ |  |
| `name` | `varchar(100)` | NO |  |  |  |
| `category` | `ENUM(GENERAL_CARGO, BREAK_BULK, DRY_BULK, LIQUID_BULK, GAS, RO_RO, CONTAINERIZED)` | NO |  |  |  |
| `imdg_class` | `varchar(3)` | YES |  | FK → `imdg_class.code` | NULL = not dangerous |
| `min_temperature` | `decimal(5,2)` | YES |  |  | Â°C; NULL = no temperature requirement |
| `max_temperature` | `decimal(5,2)` | YES |  |  | Â°C; NULL = no temperature requirement |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ct_temp` | `((min_temperature is null) or (max_temperature is null) or (min_temperature <= max_temperature))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_ct_imdg` | No | imdg_class |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ct_imdg` | imdg_class | [`imdg_class`](#imdg_class) (code) |  |

**Referenced by:** [`terminal_cargo_type`](#terminal_cargo_type).cargo_type_id

---
