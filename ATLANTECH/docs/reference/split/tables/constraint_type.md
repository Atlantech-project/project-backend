<!-- Generated from docs/reference/database/PortSync.sql lines 387-395 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `constraint_type` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(60) UNIQUE NOT NULL,
  `category` ENUM ('ENVIRONMENTAL', 'UKC', 'VESSEL_SAFETY') NOT NULL,
  `unit` ENUM ('KNOTS', 'METRES_PER_SECOND', 'METRES', 'NAUTICAL_MILES', 'PERCENT', 'DEGREES', 'MINUTES', 'TONNES') NOT NULL,
  `comparator` ENUM ('MAX_ALLOWED', 'MIN_REQUIRED') NOT NULL,
  `is_hard_safety_limit` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'TRUE = never modified by Part 3 / retraining (D12). Not the solver hard/soft choice (D13, deferred)',
  `description` varchar(255)
);

<!-- Generated from docs/reference/database/PortSync.md lines 1321-1335 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `constraint_type`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(60)` | NO |  | UQ |  |
| `category` | `ENUM(ENVIRONMENTAL, UKC, VESSEL_SAFETY)` | NO |  |  |  |
| `unit` | `ENUM(KNOTS, METRES_PER_SECOND, METRES, NAUTICAL_MILES, PERCENT, DEGREES, MINUTES, TONNES)` | NO |  |  |  |
| `comparator` | `ENUM(MAX_ALLOWED, MIN_REQUIRED)` | NO |  |  |  |
| `is_hard_safety_limit` | `tinyint(1)` | NO | `1` |  | TRUE = never modified by Part 3 / retraining (D12). Not the solver hard/soft choice (D13, deferred) |
| `description` | `varchar(255)` | YES |  |  |  |

**Referenced by:** [`safety_constraint`](#safety_constraint).constraint_type_id, [`blocked_period`](#blocked_period).limiting_constraint_type_id, [`recommendation_rationale_factor`](#recommendation_rationale_factor).constraint_type_id

---
