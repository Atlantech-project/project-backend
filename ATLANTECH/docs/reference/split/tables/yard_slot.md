<!-- Generated from docs/reference/database/PortSync.sql lines 323-331 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `yard_slot` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `yard_block_id` bigint NOT NULL,
  `bay` int NOT NULL,
  `row_no` int NOT NULL,
  `tier` int NOT NULL,
  `status` ENUM ('ACTIVE', 'BLOCKED') NOT NULL DEFAULT 'ACTIVE' COMMENT 'Administrative only; occupied = container_id IS NOT NULL',
  `container_id` bigint UNIQUE
);

<!-- Generated from docs/reference/database/PortSync.md lines 1106-1141 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `yard_slot`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `yard_block_id` | `bigint` | NO |  | FK → `yard_block.id` |  |
| `bay` | `int` | NO |  |  |  |
| `row_no` | `int` | NO |  |  |  |
| `tier` | `int` | NO |  |  |  |
| `status` | `ENUM(ACTIVE, BLOCKED)` | NO | `'ACTIVE'` |  | Administrative only; occupied = container_id IS NOT NULL |
| `container_id` | `bigint` | YES |  | UQ · FK → `container.id` |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ys_blocked_empty` | `((status = 'ACTIVE') or (container_id is null))` |
| `chk_ys_position` | `((bay > 0) and (row_no > 0) and (tier > 0))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_ys_id_block` | Yes | id, yard_block_id |
| `uq_ys_position` | Yes | yard_block_id, bay, row_no, tier |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ys_block` | yard_block_id | [`yard_block`](#yard_block) (id) |  |
| `fk_ys_container` | container_id | [`container`](#container) (id) |  |

**Referenced by:** [`yard_placement_item`](#yard_placement_item).yard_slot_id, yard_block_id

---
