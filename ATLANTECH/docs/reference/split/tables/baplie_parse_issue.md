<!-- Generated from docs/reference/database/PortSync.sql lines 490-496 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `baplie_parse_issue` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `baplie_file_id` bigint NOT NULL,
  `severity` ENUM ('ERROR', 'WARNING') NOT NULL,
  `segment_ref` varchar(60),
  `message` varchar(500) NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 1634-1658 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `baplie_parse_issue`

> FR-1.2.4 diagnostics.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `baplie_file_id` | `bigint` | NO |  | FK → `baplie_file.id` |  |
| `severity` | `ENUM(ERROR, WARNING)` | NO |  |  |  |
| `segment_ref` | `varchar(60)` | YES |  |  |  |
| `message` | `varchar(500)` | NO |  |  |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_bpi_file` | No | baplie_file_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_bpi_file` | baplie_file_id | [`baplie_file`](#baplie_file) (id) |  |

---
