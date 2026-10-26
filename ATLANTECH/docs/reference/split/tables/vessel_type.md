<!-- Generated from docs/reference/database/PortSync.sql lines 204-208 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `vessel_type` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(30) UNIQUE NOT NULL,
  `name` varchar(80) NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 695-705 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `vessel_type`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(30)` | NO |  | UQ |  |
| `name` | `varchar(80)` | NO |  |  |  |

**Referenced by:** [`vessel`](#vessel).vessel_type_id, [`safety_constraint`](#safety_constraint).vessel_type_id, [`terminal_productivity_profile`](#terminal_productivity_profile).vessel_type_id

---
