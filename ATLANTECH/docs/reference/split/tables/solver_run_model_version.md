<!-- Generated from docs/reference/database/PortSync.sql lines 712-716 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `solver_run_model_version` (
  `solver_run_id` bigint NOT NULL,
  `model_version_id` bigint NOT NULL,
  PRIMARY KEY (`solver_run_id`, `model_version_id`)
);

<!-- Generated from docs/reference/database/PortSync.md lines 2311-2333 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `solver_run_model_version`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `solver_run_id` | `bigint` | NO |  | PK · FK → `solver_run.id` |  |
| `model_version_id` | `bigint` | NO |  | PK · FK → `ml_model_version.id` |  |

**Composite primary key:** `solver_run_id`, `model_version_id`

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_srmv_model` | No | model_version_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_srmv_model` | model_version_id | [`ml_model_version`](#ml_model_version) (id) |  |
| `fk_srmv_run` | solver_run_id | [`solver_run`](#solver_run) (id) |  |

---
