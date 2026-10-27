<!-- Generated from docs/reference/database/PortSync.sql lines 751-759 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `storage_allocation_item` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `terminal_recommendation_id` bigint NOT NULL,
  `rec_type` ENUM ('TIMING', 'YARD_PLACEMENT', 'STORAGE_ALLOCATION') NOT NULL DEFAULT 'STORAGE_ALLOCATION',
  `storage_facility_id` bigint NOT NULL,
  `cargo_type_id` bigint NOT NULL,
  `quantity` decimal(14,2) NOT NULL,
  `quantity_unit` ENUM ('TONNES', 'CUBIC_METRES', 'SQUARE_METRES', 'TEU', 'UNITS') NOT NULL COMMENT 'Must equal the facility capacity_unit (composite FK)'
);

<!-- Generated from docs/reference/database/PortSync.md lines 2445-2483 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `storage_allocation_item`

> On ACCEPTED, backend adds quantity to storage_facility.reserved_capacity (D-46).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `terminal_recommendation_id` | `bigint` | NO |  | FK → `terminal_recommendation.id, rec_type` |  |
| `rec_type` | `ENUM(TIMING, YARD_PLACEMENT, STORAGE_ALLOCATION)` | NO | `'STORAGE_ALLOCATION'` | FK → `terminal_recommendation.id, rec_type` |  |
| `storage_facility_id` | `bigint` | NO |  | FK → `storage_facility_cargo_compatibility.storage_facility_id, cargo_type_id` |  |
| `cargo_type_id` | `bigint` | NO |  | FK → `storage_facility_cargo_compatibility.storage_facility_id, cargo_type_id` |  |
| `quantity` | `decimal(14,2)` | NO |  |  |  |
| `quantity_unit` | `ENUM(TONNES, CUBIC_METRES, SQUARE_METRES, TEU, UNITS)` | NO |  | FK → `storage_facility.id, capacity_unit` | Must equal the facility capacity_unit (composite FK) |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_sai_quantity` | `(quantity > 0)` |
| `chk_sai_type` | `(rec_type = 'STORAGE_ALLOCATION')` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_sai_compatible` | No | storage_facility_id, cargo_type_id |
| `fk_sai_tr` | No | terminal_recommendation_id, rec_type |
| `fk_sai_unit` | No | storage_facility_id, quantity_unit |
| `uq_sai_rec_facility_cargo` | Yes | terminal_recommendation_id, storage_facility_id, cargo_type_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_sai_compatible` | storage_facility_id, cargo_type_id | [`storage_facility_cargo_compatibility`](#storage_facility_cargo_compatibility) (storage_facility_id, cargo_type_id) |  |
| `fk_sai_tr` | terminal_recommendation_id, rec_type | [`terminal_recommendation`](#terminal_recommendation) (id, rec_type) |  |
| `fk_sai_unit` | storage_facility_id, quantity_unit | [`storage_facility`](#storage_facility) (id, capacity_unit) |  |

---
