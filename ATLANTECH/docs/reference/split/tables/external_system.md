<!-- Generated from docs/reference/database/PortSync.sql lines 466-470 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `external_system` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(30) UNIQUE NOT NULL,
  `name` varchar(100) NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 1565-1577 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `external_system`

> External adapters that send berthing requests (P10-03).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(30)` | NO |  | UQ |  |
| `name` | `varchar(100)` | NO |  |  |  |

**Referenced by:** [`berthing_request`](#berthing_request).external_system_id

---
