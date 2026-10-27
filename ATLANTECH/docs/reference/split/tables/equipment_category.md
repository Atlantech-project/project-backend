<!-- Generated from docs/reference/database/PortSync.sql lines 380-385 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `equipment_category` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(40) UNIQUE NOT NULL,
  `name` varchar(100) NOT NULL,
  `equipment_role` ENUM ('VESSEL_HANDLING', 'CARGO_TRANSPORT', 'TRANSFER_SYSTEM') NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 1308-1319 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `equipment_category`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(40)` | NO |  | UQ |  |
| `name` | `varchar(100)` | NO |  |  |  |
| `equipment_role` | `ENUM(VESSEL_HANDLING, CARGO_TRANSPORT, TRANSFER_SYSTEM)` | NO |  |  |  |

**Referenced by:** [`equipment`](#equipment).equipment_category_id, [`safety_constraint`](#safety_constraint).equipment_category_id

---
