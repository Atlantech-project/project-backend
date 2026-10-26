<!-- Generated from docs/reference/database/PortSync.sql lines 741-749 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `recommendation_rationale_factor` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `option_id` bigint NOT NULL,
  `factor_type` ENUM ('CONSTRAINT', 'OBJECTIVE_TERM') NOT NULL,
  `constraint_type_id` bigint,
  `objective_term_id` bigint,
  `factor_value` decimal(14,4),
  `display_order` int NOT NULL DEFAULT 0
);

<!-- Generated from docs/reference/database/PortSync.md lines 2407-2443 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `recommendation_rationale_factor`

> Constraints/objective terms that actually drove the solve (FR-1.4.10).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `option_id` | `bigint` | NO |  | FK → `recommendation_option.id` |  |
| `factor_type` | `ENUM(CONSTRAINT, OBJECTIVE_TERM)` | NO |  |  |  |
| `constraint_type_id` | `bigint` | YES |  | FK → `constraint_type.id` |  |
| `objective_term_id` | `bigint` | YES |  | FK → `objective_term.id` |  |
| `factor_value` | `decimal(14,4)` | YES |  |  |  |
| `display_order` | `int` | NO | `0` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_rrf_kind` | `(((factor_type = 'CONSTRAINT') and (constraint_type_id is not null) and (objective_term_id is null)) or ((factor_type = 'OBJECTIVE_TERM') and (objective_term_id is not null) and (constraint_type_id is null)))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_rrf_constraint` | No | constraint_type_id |
| `fk_rrf_objective` | No | objective_term_id |
| `fk_rrf_option` | No | option_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_rrf_constraint` | constraint_type_id | [`constraint_type`](#constraint_type) (id) |  |
| `fk_rrf_objective` | objective_term_id | [`objective_term`](#objective_term) (id) |  |
| `fk_rrf_option` | option_id | [`recommendation_option`](#recommendation_option) (id) |  |

---
