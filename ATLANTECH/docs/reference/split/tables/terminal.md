<!-- Generated from docs/reference/database/PortSync.sql lines 87-94 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `terminal` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(20) UNIQUE NOT NULL,
  `name` varchar(120) UNIQUE NOT NULL,
  `terminal_type` ENUM ('CONTAINER', 'NON_CONTAINER') NOT NULL,
  `boundary_coordinates` json COMMENT 'GeoJSON Polygon [lon, lat]; inside port boundary (app rule)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'All registered terminals (active or not) count against license max_terminals'
);

<!-- Generated from docs/reference/database/PortSync.md lines 322-341 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `terminal`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(20)` | NO |  | UQ |  |
| `name` | `varchar(120)` | NO |  | UQ |  |
| `terminal_type` | `ENUM(CONTAINER, NON_CONTAINER)` | NO |  |  |  |
| `boundary_coordinates` | `json` | YES |  |  | GeoJSON Polygon [lon, lat]; inside port boundary (app rule) |
| `is_active` | `tinyint(1)` | NO | `1` |  | All registered terminals (active or not) count against license max_terminals |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_terminal_id_type` | Yes | id, terminal_type |

**Referenced by:** [`berth_terminal`](#berth_terminal).terminal_id, [`organization_unit`](#organization_unit).terminal_id, [`storage_facility`](#storage_facility).terminal_id, terminal_type, [`terminal_cargo_type`](#terminal_cargo_type).terminal_id, [`equipment`](#equipment).terminal_id, [`safety_constraint`](#safety_constraint).terminal_id, [`terminal_productivity_profile`](#terminal_productivity_profile).terminal_id, terminal_type, [`berthing_request`](#berthing_request).requested_terminal_id, [`terminal_recommendation`](#terminal_recommendation).terminal_id, terminal_type, [`plan_component`](#plan_component).terminal_id

---
