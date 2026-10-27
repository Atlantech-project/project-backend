<!-- Generated from docs/reference/database/PortSync.sql lines 220-223 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `imdg_class` (
  `code` varchar(3) PRIMARY KEY COMMENT 'IMDG class / division, e.g. 3, 2.1',
  `name` varchar(80) NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 741-752 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `imdg_class`

> Fixed IMDG list; one source for every imdg_class column.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `code` | `varchar(3)` | NO |  | PK | IMDG class / division, e.g. 3, 2.1 |
| `name` | `varchar(80)` | NO |  |  |  |

**Referenced by:** [`cargo_type`](#cargo_type).imdg_class, [`dg_zone_allowed_class`](#dg_zone_allowed_class).imdg_class, [`safety_constraint`](#safety_constraint).imdg_class, [`container_manifest_entry`](#container_manifest_entry).imdg_class

---
