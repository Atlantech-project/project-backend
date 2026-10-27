<!-- Generated from docs/reference/database/PortSync.sql lines 342-347 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `yard_zone_type` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(30) UNIQUE NOT NULL,
  `name` varchar(80) NOT NULL,
  `zone_detail` ENUM ('NONE', 'REEFER', 'DANGEROUS_GOODS', 'OOG') NOT NULL DEFAULT 'NONE' COMMENT 'Which detail table zones of this type use; chosen by the admin when adding a type'
);

<!-- Generated from docs/reference/database/PortSync.md lines 1182-1199 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `yard_zone_type`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(30)` | NO |  | UQ |  |
| `name` | `varchar(80)` | NO |  |  |  |
| `zone_detail` | `ENUM(NONE, REEFER, DANGEROUS_GOODS, OOG)` | NO | `'NONE'` |  | Which detail table zones of this type use; chosen by the admin when adding a type |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_yzt_id_detail` | Yes | id, zone_detail |

**Referenced by:** [`yard_zone`](#yard_zone).yard_zone_type_id, zone_detail

---
