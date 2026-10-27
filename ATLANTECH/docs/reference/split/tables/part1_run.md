<!-- Generated from docs/reference/database/PortSync.sql lines 921-931 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `part1_run` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `berthing_request_id` bigint NOT NULL,
  `status` ENUM ('QUEUED', 'RUNNING', 'DONE', 'FAILED', 'REPLACED') NOT NULL DEFAULT 'QUEUED',
  `outcome` ENUM ('WINDOWS_FOUND', 'NO_FEASIBLE_WINDOW', 'ENV_DATA_UNAVAILABLE'),
  `retry_count` tinyint NOT NULL DEFAULT 0 COMMENT '0..3',
  `entry_window_proposal_id` bigint COMMENT 'Set only when windows are found',
  `queued_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `started_at` datetime,
  `finished_at` datetime
);

<!-- Generated from docs/reference/database/PortSync.md lines 2975-3002 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `part1_run`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `berthing_request_id` | `bigint` | NO |  | FK → `berthing_request.id` |  |
| `status` | `ENUM(QUEUED, RUNNING, DONE, FAILED, REPLACED)` | NO | `'QUEUED'` |  |  |
| `outcome` | `ENUM(WINDOWS_FOUND, NO_FEASIBLE_WINDOW, ENV_DATA_UNAVAILABLE)` | YES |  |  |  |
| `retry_count` | `tinyint` | NO | `0` |  | 0..3 |
| `entry_window_proposal_id` | `bigint` | YES |  | FK → `entry_window_proposal.id` | Set only when windows are found |
| `queued_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `started_at` | `datetime` | YES |  |  |  |
| `finished_at` | `datetime` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_p1r_retry` | `(retry_count BETWEEN 0 AND 3)` |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| — | berthing_request_id | [`berthing_request`](#berthing_request) (id) |  |
| — | entry_window_proposal_id | [`entry_window_proposal`](#entry_window_proposal) (id) |  |

---
