<!-- Generated from docs/reference/database/PortSync.sql lines 174-178 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `container` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `container_number` char(11) UNIQUE NOT NULL COMMENT 'ISO 6346, uppercase, no spaces; check digit validated by parser (warning)',
  `size_type_id` bigint NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 604-634 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `container`

> Identity only. Per-call attributes (weight, stowage, ports) live in container_manifest_entry.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `container_number` | `char(11)` | NO |  | UQ | ISO 6346, uppercase, no spaces; check digit validated by parser (warning) |
| `size_type_id` | `bigint` | NO |  | FK → `container_size_type.id` |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_container_number` | `regexp_like(container_number,'^[A-Z]{3}[UJZ][0-9]{7}$','c')` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `idx_container_size_type` | No | size_type_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_container_size_type` | size_type_id | [`container_size_type`](#container_size_type) (id) |  |

**Referenced by:** [`yard_slot`](#yard_slot).container_id, [`container_manifest_entry`](#container_manifest_entry).container_id

---
