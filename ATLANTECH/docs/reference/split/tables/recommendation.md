<!-- Generated from docs/reference/database/PortSync.sql lines 660-671 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `recommendation` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `solver_run_id` bigint NOT NULL,
  `berthing_request_id` bigint NOT NULL,
  `terminal_id` bigint NOT NULL COMMENT 'Copy of the requested terminal, enforced by composite FK',
  `entry_window_proposal_id` bigint NOT NULL COMMENT 'Confirmed Part 1 input of the same request',
  `status` ENUM ('PENDING_REVIEW', 'CONFIRMED', 'REJECTED', 'INVALIDATED', 'REPLACED', 'MODIFIED_BY_STAFF') NOT NULL DEFAULT 'PENDING_REVIEW',
  `source` ENUM ('AI', 'FIFO') NOT NULL DEFAULT 'AI' COMMENT 'FIFO = fallback after a solver technical failure',
  `purpose` ENUM ('INITIAL', 'DELAY_REPLAN') NOT NULL DEFAULT 'INITIAL',
  `active_key` bigint UNIQUE COMMENT 'GENERATED (stored): IF(status IN (PENDING_REVIEW, MODIFIED_BY_STAFF, CONFIRMED) AND purpose = INITIAL, berthing_request_id, NULL)',
  `generated_at` datetime NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 2150-2185 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `recommendation`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `solver_run_id` | `bigint` | NO |  | FK → `solver_run.id` |  |
| `berthing_request_id` | `bigint` | NO |  | FK → `entry_window_proposal.id, berthing_request_id` |  |
| `terminal_id` | `bigint` | NO |  | FK → `berthing_request.id, requested_terminal_id` | Copy of the requested terminal, enforced by composite FK |
| `entry_window_proposal_id` | `bigint` | NO |  | FK → `entry_window_proposal.id, berthing_request_id` | Confirmed Part 1 input of the same request |
| `status` | `ENUM(PENDING_REVIEW, CONFIRMED, REJECTED, INVALIDATED, REPLACED, MODIFIED_BY_STAFF)` | NO | `'PENDING_REVIEW'` |  |  |
| `source` | `ENUM(AI, FIFO)` | NO | `'AI'` |  | FIFO = fallback after a solver technical failure |
| `purpose` | `ENUM(INITIAL, DELAY_REPLAN)` | NO | `'INITIAL'` |  |  |
| `active_key` | `bigint` | YES |  | UQ | GENERATED (stored): IF(status IN (PENDING_REVIEW, MODIFIED_BY_STAFF, CONFIRMED) AND purpose = INITIAL, berthing_request_id, NULL) |
| `generated_at` | `datetime` | NO |  |  |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_rec_proposal` | No | entry_window_proposal_id, berthing_request_id |
| `fk_rec_request` | No | berthing_request_id, terminal_id |
| `fk_rec_run` | No | solver_run_id |
| `uq_rec_id_request` | Yes | id, berthing_request_id |
| `uq_rec_id_terminal` | Yes | id, terminal_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_rec_proposal` | entry_window_proposal_id, berthing_request_id | [`entry_window_proposal`](#entry_window_proposal) (id, berthing_request_id) |  |
| `fk_rec_request` | berthing_request_id, terminal_id | [`berthing_request`](#berthing_request) (id, requested_terminal_id) |  |
| `fk_rec_run` | solver_run_id | [`solver_run`](#solver_run) (id) |  |

**Referenced by:** [`recommendation_review`](#recommendation_review).recommendation_id, [`recommendation_option`](#recommendation_option).recommendation_id, terminal_id, [`terminal_recommendation`](#terminal_recommendation).recommendation_id, terminal_id, [`operational_plan`](#operational_plan).recommendation_id, berthing_request_id

---
