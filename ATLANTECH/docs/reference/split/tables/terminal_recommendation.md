<!-- Generated from docs/reference/database/PortSync.sql lines 761-779 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `terminal_recommendation` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `recommendation_id` bigint NOT NULL,
  `terminal_id` bigint NOT NULL,
  `terminal_type` ENUM ('CONTAINER', 'NON_CONTAINER') NOT NULL COMMENT 'Copy, enforced by composite FK',
  `rec_type` ENUM ('TIMING', 'YARD_PLACEMENT', 'STORAGE_ALLOCATION') NOT NULL,
  `window_start` datetime,
  `window_end` datetime,
  `decision` ENUM ('PENDING', 'ACCEPTED', 'REJECTED', 'ADJUSTED') NOT NULL DEFAULT 'PENDING',
  `decided_by` bigint,
  `decided_at` datetime,
  `decision_note` varchar(500),
  `lifecycle_status` ENUM ('YARD_REC_REQUESTED', 'YARD_REC_GENERATED', 'GENERATION_FAILED', 'YARD_REC_ACCEPTED', 'YARD_REC_REJECTED', 'EXPORTED_TO_TOS') COMMENT 'YARD_PLACEMENT only',
  `failure_reason` varchar(500),
  `exported_at` datetime,
  `exported_by` bigint,
  `rationale_template_id` bigint,
  `confidence` decimal(4,3)
);

<!-- Generated from docs/reference/database/PortSync.md lines 2485-2544 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `terminal_recommendation`

> Common terminal output contract (FR-3.2.2). Visible to that terminal only (§42).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `recommendation_id` | `bigint` | NO |  | FK → `recommendation.id, terminal_id` |  |
| `terminal_id` | `bigint` | NO |  | FK → `recommendation.id, terminal_id` |  |
| `terminal_type` | `ENUM(CONTAINER, NON_CONTAINER)` | NO |  | FK → `terminal.id, terminal_type` | Copy, enforced by composite FK |
| `rec_type` | `ENUM(TIMING, YARD_PLACEMENT, STORAGE_ALLOCATION)` | NO |  |  |  |
| `window_start` | `datetime` | YES |  |  |  |
| `window_end` | `datetime` | YES |  |  |  |
| `decision` | `ENUM(PENDING, ACCEPTED, REJECTED, ADJUSTED)` | NO | `'PENDING'` |  |  |
| `decided_by` | `bigint` | YES |  | FK → `user.id` |  |
| `decided_at` | `datetime` | YES |  |  |  |
| `decision_note` | `varchar(500)` | YES |  |  |  |
| `lifecycle_status` | `ENUM(YARD_REC_REQUESTED, YARD_REC_GENERATED, GENERATION_FAILED, YARD_REC_ACCEPTED, YARD_REC_REJECTED, EXPORTED_TO_TOS)` | YES |  |  | YARD_PLACEMENT only |
| `failure_reason` | `varchar(500)` | YES |  |  |  |
| `exported_at` | `datetime` | YES |  |  |  |
| `exported_by` | `bigint` | YES |  | FK → `user.id` |  |
| `rationale_template_id` | `bigint` | YES |  | FK → `rationale_template.id` |  |
| `confidence` | `decimal(4,3)` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_tr_decision` | `(((decision = 'PENDING') = (decided_by is null)) and ((decided_by is null) = (decided_at is null)))` |
| `chk_tr_type_terminal` | `(((rec_type <> 'YARD_PLACEMENT') or (terminal_type = 'CONTAINER')) and ((rec_type <> 'STORAGE_ALLOCATION') or (terminal_type = 'NON_CONTAINER')))` |
| `chk_tr_window` | `(((rec_type = 'TIMING') = ((window_start is not null) and (window_end is not null))) and ((window_start is null) or (window_end > window_start)))` |
| `chk_tr_lifecycle_yard` | `(lifecycle_status IS NULL OR rec_type = 'YARD_PLACEMENT')` |
| `chk_tr_confidence` | `(confidence IS NULL OR confidence BETWEEN 0 AND 1)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_tr_rec` | No | recommendation_id, terminal_id |
| `fk_tr_terminal` | No | terminal_id, terminal_type |
| `fk_tr_user` | No | decided_by |
| `fk_tr_exported_by` | No | exported_by |
| `fk_tr_template` | No | rationale_template_id |
| `idx_tr_terminal_decision` | No | terminal_id, decision |
| `uq_tr_id_type` | Yes | id, rec_type |
| `uq_tr_rec_type` | Yes | recommendation_id, rec_type |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_tr_rec` | recommendation_id, terminal_id | [`recommendation`](#recommendation) (id, terminal_id) |  |
| `fk_tr_terminal` | terminal_id, terminal_type | [`terminal`](#terminal) (id, terminal_type) |  |
| `fk_tr_user` | decided_by | [`user`](#user) (id) |  |
| `fk_tr_exported_by` | exported_by | [`user`](#user) (id) |  |
| `fk_tr_template` | rationale_template_id | [`rationale_template`](#rationale_template) (id) |  |

**Referenced by:** [`storage_allocation_item`](#storage_allocation_item).terminal_recommendation_id, rec_type, [`yard_placement_item`](#yard_placement_item).terminal_recommendation_id, rec_type

---
