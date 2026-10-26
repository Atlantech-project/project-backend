<!-- Generated from docs/reference/database/PortSync.sql lines 718-722 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `objective_term` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(40) UNIQUE NOT NULL,
  `name` varchar(100) NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 2335-2347 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `objective_term`

> CP-SAT objective terms (P16-02). Codes agreed with the AI team.

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(40)` | NO |  | UQ |  |
| `name` | `varchar(100)` | NO |  |  |  |

**Referenced by:** [`recommendation_rationale_factor`](#recommendation_rationale_factor).objective_term_id

---
