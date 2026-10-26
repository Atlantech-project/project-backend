<!-- Generated from docs/reference/database/PortSync.sql lines 294-299 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `dg_zone_allowed_class` (
  `yard_zone_id` bigint NOT NULL,
  `zone_detail` ENUM ('NONE', 'REEFER', 'DANGEROUS_GOODS', 'OOG') NOT NULL DEFAULT 'DANGEROUS_GOODS',
  `imdg_class` varchar(3) NOT NULL,
  PRIMARY KEY (`yard_zone_id`, `imdg_class`)
);

<!-- Generated from docs/reference/database/PortSync.md lines 988-1018 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `dg_zone_allowed_class`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `yard_zone_id` | `bigint` | NO |  | PK · FK → `yard_zone.id, zone_detail` |  |
| `zone_detail` | `ENUM(NONE, REEFER, DANGEROUS_GOODS, OOG)` | NO | `'DANGEROUS_GOODS'` | FK → `yard_zone.id, zone_detail` |  |
| `imdg_class` | `varchar(3)` | NO |  | PK · FK → `imdg_class.code` |  |

**Composite primary key:** `yard_zone_id`, `imdg_class`

**Check constraints**

| Name | Rule |
|---|---|
| `chk_dzac_type` | `(zone_detail = 'DANGEROUS_GOODS')` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_dzac_imdg` | No | imdg_class |
| `fk_dzac_zone` | No | yard_zone_id, zone_detail |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_dzac_imdg` | imdg_class | [`imdg_class`](#imdg_class) (code) |  |
| `fk_dzac_zone` | yard_zone_id, zone_detail | [`yard_zone`](#yard_zone) (id, zone_detail) |  |

---
