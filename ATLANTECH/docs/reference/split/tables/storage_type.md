<!-- Generated from docs/reference/database/PortSync.sql lines 265-270 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `storage_type` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(30) UNIQUE NOT NULL,
  `name` varchar(80) NOT NULL,
  `detail_kind` ENUM ('NONE', 'WAREHOUSE', 'CONTAINER_YARD', 'TANK') NOT NULL DEFAULT 'NONE' COMMENT 'Which detail table facilities of this type may use'
);

<!-- Generated from docs/reference/database/PortSync.md lines 880-897 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `storage_type`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(30)` | NO |  | UQ |  |
| `name` | `varchar(80)` | NO |  |  |  |
| `detail_kind` | `ENUM(NONE, WAREHOUSE, CONTAINER_YARD, TANK)` | NO | `'NONE'` |  | Which detail table facilities of this type may use |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_storage_type_id_kind` | Yes | id, detail_kind |

**Referenced by:** [`storage_facility`](#storage_facility).storage_type_id, detail_kind

---
