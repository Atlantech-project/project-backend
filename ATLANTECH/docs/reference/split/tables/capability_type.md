<!-- Generated from docs/reference/database/PortSync.sql lines 349-354 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `capability_type` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(60) UNIQUE NOT NULL,
  `name` varchar(100) NOT NULL,
  `unit` ENUM ('TONNES', 'METRES', 'TONNES_PER_HOUR', 'CUBIC_METRES_PER_HOUR', 'MOVES_PER_HOUR', 'COUNT', 'BAR') NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 1201-1212 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `capability_type`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(60)` | NO |  | UQ |  |
| `name` | `varchar(100)` | NO |  |  |  |
| `unit` | `ENUM(TONNES, METRES, TONNES_PER_HOUR, CUBIC_METRES_PER_HOUR, MOVES_PER_HOUR, COUNT, BAR)` | NO |  |  |  |

**Referenced by:** [`equipment_capability`](#equipment_capability).capability_type_id

---
