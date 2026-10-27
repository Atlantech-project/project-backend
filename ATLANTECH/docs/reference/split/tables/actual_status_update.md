<!-- Generated from docs/reference/database/PortSync.sql lines 816-828 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `actual_status_update` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `client_event_id` char(36) UNIQUE NOT NULL COMMENT 'UUID generated on the device; duplicates from offline re-sends are ignored',
  `plan_component_id` bigint NOT NULL,
  `component_type` ENUM ('BERTH', 'PILOT', 'TUG', 'TERMINAL_OPS') NOT NULL COMMENT 'Copy of the component type, enforced by composite FK',
  `reported_by` bigint NOT NULL,
  `status` varchar(30) NOT NULL COMMENT 'Must be a status of this component type (status_type)',
  `reported_at` datetime NOT NULL COMMENT 'Time on the ground (device)',
  `client_sent_at` datetime COMMENT 'Device clock when the batch was sent',
  `received_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP) COMMENT 'Server receipt (offline sync)',
  `clock_skew_suspect` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = received_at - client_sent_at > 5 min; excluded from evaluation',
  `sync_source` ENUM ('MOBILE', 'TABLET', 'WEB', 'EXTERNAL') NOT NULL
);

<!-- Generated from docs/reference/database/PortSync.md lines 2671-2706 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `actual_status_update`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `client_event_id` | `char(36)` | NO |  | UQ | UUID generated on the device; duplicates from offline re-sends are ignored |
| `plan_component_id` | `bigint` | NO |  | FK → `plan_component.id, component_type` |  |
| `component_type` | `ENUM(BERTH, PILOT, TUG, TERMINAL_OPS)` | NO |  | FK → `plan_component.id, component_type` | Copy of the component type, enforced by composite FK |
| `reported_by` | `bigint` | NO |  | FK → `user.id` |  |
| `status` | `varchar(30)` | NO |  | FK → `status_type.component_type, code` | Must be a status of this component type (status_type) |
| `reported_at` | `datetime` | NO |  |  | Time on the ground (device) |
| `client_sent_at` | `datetime` | YES |  |  | Device clock when the batch was sent |
| `received_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  | Server receipt (offline sync) |
| `clock_skew_suspect` | `tinyint(1)` | NO | `0` |  | 1 = received_at - client_sent_at > 5 min; excluded from evaluation |
| `sync_source` | `ENUM(MOBILE, TABLET, WEB, EXTERNAL)` | NO |  |  |  |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_asu_component` | No | plan_component_id, component_type |
| `fk_asu_status` | No | component_type, status |
| `fk_asu_user` | No | reported_by |
| `idx_asu_component_time` | No | plan_component_id, reported_at |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_asu_component` | plan_component_id, component_type | [`plan_component`](#plan_component) (id, component_type) |  |
| `fk_asu_status` | component_type, status | [`status_type`](#status_type) (component_type, code) |  |
| `fk_asu_user` | reported_by | [`user`](#user) (id) |  |

**Referenced by:** [`deviation_log`](#deviation_log).status_update_id

---
