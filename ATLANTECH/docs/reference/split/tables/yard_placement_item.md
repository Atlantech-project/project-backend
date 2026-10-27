<!-- Generated from docs/reference/database/PortSync.sql lines 781-788 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `yard_placement_item` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `terminal_recommendation_id` bigint NOT NULL,
  `rec_type` ENUM ('TIMING', 'YARD_PLACEMENT', 'STORAGE_ALLOCATION') NOT NULL DEFAULT 'YARD_PLACEMENT',
  `manifest_entry_id` bigint NOT NULL,
  `yard_block_id` bigint NOT NULL,
  `yard_slot_id` bigint COMMENT 'Optional; coordination level is block (FR-2.2.3)'
);

<!-- Generated from docs/reference/database/PortSync.md lines 2546-2582 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `yard_placement_item`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `terminal_recommendation_id` | `bigint` | NO |  | FK → `terminal_recommendation.id, rec_type` |  |
| `rec_type` | `ENUM(TIMING, YARD_PLACEMENT, STORAGE_ALLOCATION)` | NO | `'YARD_PLACEMENT'` | FK → `terminal_recommendation.id, rec_type` |  |
| `manifest_entry_id` | `bigint` | NO |  | FK → `container_manifest_entry.id` |  |
| `yard_block_id` | `bigint` | NO |  | FK → `yard_block.id` |  |
| `yard_slot_id` | `bigint` | YES |  | FK → `yard_slot.id, yard_block_id` | Optional; coordination level is block (FR-2.2.3) |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ypi_type` | `(rec_type = 'YARD_PLACEMENT')` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_ypi_block` | No | yard_block_id |
| `fk_ypi_manifest` | No | manifest_entry_id |
| `fk_ypi_slot` | No | yard_slot_id, yard_block_id |
| `fk_ypi_tr` | No | terminal_recommendation_id, rec_type |
| `uq_ypi_rec_container` | Yes | terminal_recommendation_id, manifest_entry_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ypi_block` | yard_block_id | [`yard_block`](#yard_block) (id) |  |
| `fk_ypi_manifest` | manifest_entry_id | [`container_manifest_entry`](#container_manifest_entry) (id) |  |
| `fk_ypi_slot` | yard_slot_id, yard_block_id | [`yard_slot`](#yard_slot) (id, yard_block_id) |  |
| `fk_ypi_tr` | terminal_recommendation_id, rec_type | [`terminal_recommendation`](#terminal_recommendation) (id, rec_type) |  |

---
