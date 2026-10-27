<!-- Generated from docs/reference/database/PortSync.sql lines 790-799 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `operational_plan` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `berthing_request_id` bigint NOT NULL,
  `recommendation_id` bigint NOT NULL COMMENT 'Enforces option <-> recommendation <-> request consistency',
  `recommendation_option_id` bigint NOT NULL COMMENT 'The confirmed (selected) option',
  `status` ENUM ('CONFIRMED', 'CANCELLED', 'COMPLETED') NOT NULL DEFAULT 'CONFIRMED',
  `active_key` bigint UNIQUE COMMENT 'GENERATED (stored): IF(status = CONFIRMED, berthing_request_id, NULL)',
  `confirmed_by` bigint NOT NULL,
  `confirmed_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

<!-- Generated from docs/reference/database/PortSync.md lines 2584-2615 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `operational_plan`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `berthing_request_id` | `bigint` | NO |  | FK → `recommendation.id, berthing_request_id` |  |
| `recommendation_id` | `bigint` | NO |  | FK → `recommendation_option.id, recommendation_id` | Enforces option <-> recommendation <-> request consistency |
| `recommendation_option_id` | `bigint` | NO |  | FK → `recommendation_option.id, recommendation_id` | The confirmed (selected) option |
| `status` | `ENUM(CONFIRMED, CANCELLED, COMPLETED)` | NO | `'CONFIRMED'` |  |  |
| `active_key` | `bigint` | YES |  | UQ | GENERATED (stored): IF(status = CONFIRMED, berthing_request_id, NULL) |
| `confirmed_by` | `bigint` | NO |  | FK → `user.id` |  |
| `confirmed_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_op_option` | No | recommendation_option_id, recommendation_id |
| `fk_op_rec` | No | recommendation_id, berthing_request_id |
| `fk_op_user` | No | confirmed_by |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_op_option` | recommendation_option_id, recommendation_id | [`recommendation_option`](#recommendation_option) (id, recommendation_id) |  |
| `fk_op_rec` | recommendation_id, berthing_request_id | [`recommendation`](#recommendation) (id, berthing_request_id) |  |
| `fk_op_user` | confirmed_by | [`user`](#user) (id) |  |

**Referenced by:** [`plan_component`](#plan_component).operational_plan_id

---
