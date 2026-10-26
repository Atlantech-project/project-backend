<!-- Generated from docs/reference/database/PortSync.sql lines 456-464 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `berthing_request_status_history` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `berthing_request_id` bigint NOT NULL,
  `from_status` ENUM ('SUBMITTED', 'NEEDS_REVISION', 'PRELIMINARY_APPROVED', 'CUSTOMS_APPROVED', 'ENTRY_WINDOW_CONFIRMED', 'RECOMMENDATION_GENERATED', 'PLAN_CONFIRMED', 'PLAN_CANCELLED', 'COMPLETED', 'REJECTED', 'CANCELLED') COMMENT 'NULL on creation',
  `to_status` ENUM ('SUBMITTED', 'NEEDS_REVISION', 'PRELIMINARY_APPROVED', 'CUSTOMS_APPROVED', 'ENTRY_WINDOW_CONFIRMED', 'RECOMMENDATION_GENERATED', 'PLAN_CONFIRMED', 'PLAN_CANCELLED', 'COMPLETED', 'REJECTED', 'CANCELLED') NOT NULL,
  `changed_by` bigint COMMENT 'NULL = system / external adapter',
  `changed_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `note` varchar(500)
);

<!-- Generated from docs/reference/database/PortSync.md lines 1529-1563 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `berthing_request_status_history`

> CUSTOMS_APPROVED row = who/when Customs Approval was recorded (D1, D4).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `berthing_request_id` | `bigint` | NO |  | FK → `berthing_request.id` |  |
| `from_status` | `ENUM(SUBMITTED, NEEDS_REVISION, PRELIMINARY_APPROVED, CUSTOMS_APPROVED, ENTRY_WINDOW_CONFIRMED, RECOMMENDATION_GENERATED, PLAN_CONFIRMED, PLAN_CANCELLED, COMPLETED, REJECTED, CANCELLED)` | YES |  |  | NULL on creation |
| `to_status` | `ENUM(SUBMITTED, NEEDS_REVISION, PRELIMINARY_APPROVED, CUSTOMS_APPROVED, ENTRY_WINDOW_CONFIRMED, RECOMMENDATION_GENERATED, PLAN_CONFIRMED, PLAN_CANCELLED, COMPLETED, REJECTED, CANCELLED)` | NO |  |  |  |
| `changed_by` | `bigint` | YES |  | FK → `user.id` | NULL = system / external adapter |
| `changed_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `note` | `varchar(500)` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_brsh_change` | `((from_status is null) or (from_status <> to_status))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_brsh_user` | No | changed_by |
| `idx_brsh_request` | No | berthing_request_id, changed_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_brsh_request` | berthing_request_id | [`berthing_request`](#berthing_request) (id) |  |
| `fk_brsh_user` | changed_by | [`user`](#user) (id) |  |

---
