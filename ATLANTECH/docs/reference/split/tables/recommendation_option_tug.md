<!-- Generated from docs/reference/database/PortSync.sql lines 690-694 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `recommendation_option_tug` (
  `option_id` bigint NOT NULL,
  `tugboat_id` bigint NOT NULL,
  PRIMARY KEY (`option_id`, `tugboat_id`)
);

<!-- Generated from docs/reference/database/PortSync.md lines 2240-2264 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `recommendation_option_tug`

> Number of tugs decided by the AI formula.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `option_id` | `bigint` | NO |  | PK · FK → `recommendation_option.id` |  |
| `tugboat_id` | `bigint` | NO |  | PK · FK → `tugboat.id` |  |

**Composite primary key:** `option_id`, `tugboat_id`

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_rot_tug` | No | tugboat_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_rot_option` | option_id | [`recommendation_option`](#recommendation_option) (id) |  |
| `fk_rot_tug` | tugboat_id | [`tugboat`](#tugboat) (id) |  |

---
