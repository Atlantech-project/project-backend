<!-- Generated from docs/reference/database/PortSync.sql lines 366-371 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `equipment_capability` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `equipment_id` bigint NOT NULL,
  `capability_type_id` bigint NOT NULL,
  `capability_value` decimal(12,3) NOT NULL COMMENT 'In capability_type.unit'
);

<!-- Generated from docs/reference/database/PortSync.md lines 1251-1280 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `equipment_capability`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `equipment_id` | `bigint` | NO |  | FK → `equipment.id` |  |
| `capability_type_id` | `bigint` | NO |  | FK → `capability_type.id` |  |
| `capability_value` | `decimal(12,3)` | NO |  |  | In capability_type.unit |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_ecap_value` | `(capability_value >= 0)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_ecap_type` | No | capability_type_id |
| `uq_ecap_equipment_type` | Yes | equipment_id, capability_type_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_ecap_equipment` | equipment_id | [`equipment`](#equipment) (id) |  |
| `fk_ecap_type` | capability_type_id | [`capability_type`](#capability_type) (id) |  |

---
