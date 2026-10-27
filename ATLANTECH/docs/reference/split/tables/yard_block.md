<!-- Generated from docs/reference/database/PortSync.sql lines 317-321 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `yard_block` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `yard_zone_id` bigint NOT NULL,
  `code` varchar(30) NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 1082-1104 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `yard_block`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `yard_zone_id` | `bigint` | NO |  | FK → `yard_zone.id` |  |
| `code` | `varchar(30)` | NO |  |  |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_yb_zone_code` | Yes | yard_zone_id, code |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_yb_zone` | yard_zone_id | [`yard_zone`](#yard_zone) (id) |  |

**Referenced by:** [`yard_slot`](#yard_slot).yard_block_id, [`yard_placement_item`](#yard_placement_item).yard_block_id

---
