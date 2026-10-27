<!-- Generated from docs/reference/database/PortSync.sql lines 898-909 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `notification_log` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `related_entity_type` ENUM ('PLAN_COMPONENT', 'BERTHING_REQUEST', 'RECOMMENDATION', 'ENTRY_WINDOW_PROPOSAL', 'TUGBOAT', 'RETRAINING_RUN', 'LICENSE') NOT NULL,
  `related_entity_id` bigint NOT NULL,
  `type` ENUM ('PLAN_ASSIGNMENT', 'PRE_EXECUTION_REMINDER', 'TUG_STILL_BUSY', 'ENTRY_WINDOW_READY', 'RECOMMENDATION_READY', 'PLAN_CANCELLED', 'RETRAINING_REVIEW_NEEDED', 'LICENSE_EXPIRY_WARNING', 'ACK_ESCALATION', 'YARD_RECOMMENDATION_READY', 'YARD_RECOMMENDATION_FAILED', 'DRIFT_ALERT') NOT NULL,
  `channel` ENUM ('WEB', 'MOBILE') NOT NULL,
  `status` ENUM ('SENT', 'FAILED', 'ACKNOWLEDGED') NOT NULL,
  `failure_reason` varchar(255),
  `sent_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `acknowledged_at` datetime
);

<!-- Generated from docs/reference/database/PortSync.md lines 2912-2947 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `notification_log`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `user_id` | `bigint` | NO |  | FK → `user.id` |  |
| `related_entity_type` | `ENUM(PLAN_COMPONENT, BERTHING_REQUEST, RECOMMENDATION, ENTRY_WINDOW_PROPOSAL, TUGBOAT, RETRAINING_RUN, LICENSE)` | NO |  |  |  |
| `related_entity_id` | `bigint` | NO |  |  |  |
| `type` | `ENUM(PLAN_ASSIGNMENT, PRE_EXECUTION_REMINDER, TUG_STILL_BUSY, ENTRY_WINDOW_READY, RECOMMENDATION_READY, PLAN_CANCELLED, RETRAINING_REVIEW_NEEDED, LICENSE_EXPIRY_WARNING, ACK_ESCALATION, YARD_RECOMMENDATION_READY, YARD_RECOMMENDATION_FAILED, DRIFT_ALERT)` | NO |  |  |  |
| `channel` | `ENUM(WEB, MOBILE)` | NO |  |  |  |
| `status` | `ENUM(SENT, FAILED, ACKNOWLEDGED)` | NO |  |  |  |
| `failure_reason` | `varchar(255)` | YES |  |  |  |
| `sent_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |
| `acknowledged_at` | `datetime` | YES |  |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_nl_ack` | `((status = 'ACKNOWLEDGED') = (acknowledged_at is not null))` |
| `chk_nl_failed` | `((status = 'FAILED') = (failure_reason is not null))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `idx_nl_entity` | No | related_entity_type, related_entity_id |
| `idx_nl_user_status` | No | user_id, status |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_nl_user` | user_id | [`user`](#user) (id) |  |

---
