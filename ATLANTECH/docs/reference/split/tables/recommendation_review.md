<!-- Generated from docs/reference/database/PortSync.sql lines 933-938 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `recommendation_review` (
  `recommendation_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `viewed_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  PRIMARY KEY (`recommendation_id`, `user_id`, `viewed_at`)
);

<!-- Generated from docs/reference/database/PortSync.md lines 3004-3021 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `recommendation_review`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `recommendation_id` | `bigint` | NO |  | PK · FK → `recommendation.id` |  |
| `user_id` | `bigint` | NO |  | PK · FK → `user.id` |  |
| `viewed_at` | `datetime` | NO | `CURRENT_TIMESTAMP` | PK |  |

**Composite primary key:** `recommendation_id`, `user_id`, `viewed_at`

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| — | recommendation_id | [`recommendation`](#recommendation) (id) |  |
| — | user_id | [`user`](#user) (id) |  |

---
