<!-- Generated from docs/reference/database/PortSync.sql lines 801-814 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `plan_component` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `operational_plan_id` bigint NOT NULL,
  `component_type` ENUM ('BERTH', 'PILOT', 'TUG', 'TERMINAL_OPS') NOT NULL,
  `berth_id` bigint,
  `tugboat_id` bigint,
  `terminal_id` bigint,
  `assigned_user_id` bigint COMMENT 'Pilot / Tug Master / terminal supervisor (role checked by backend, D-33)',
  `assigned_by` bigint COMMENT 'Dept. Officer (FR-1.5.1/2)',
  `assigned_at` datetime,
  `planned_start` datetime NOT NULL,
  `planned_end` datetime NOT NULL,
  `reservation_state` ENUM ('RESERVED', 'RELEASED', 'CONSUMED') NOT NULL DEFAULT 'RESERVED' COMMENT 'RELEASED on plan cancellation'
);

<!-- Generated from docs/reference/database/PortSync.md lines 2617-2669 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `plan_component`

> One row per dispatched unit (one per tug). Berth/tug occupancy = RESERVED rows overlapping a window (D-20).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `operational_plan_id` | `bigint` | NO |  | FK → `operational_plan.id` |  |
| `component_type` | `ENUM(BERTH, PILOT, TUG, TERMINAL_OPS)` | NO |  |  |  |
| `berth_id` | `bigint` | YES |  | FK → `berth.id` |  |
| `tugboat_id` | `bigint` | YES |  | FK → `tugboat.id` |  |
| `terminal_id` | `bigint` | YES |  | FK → `terminal.id` |  |
| `assigned_user_id` | `bigint` | YES |  | FK → `user.id` | Pilot / Tug Master / terminal supervisor (role checked by backend, D-33) |
| `assigned_by` | `bigint` | YES |  | FK → `user.id` | Dept. Officer (FR-1.5.1/2) |
| `assigned_at` | `datetime` | YES |  |  |  |
| `planned_start` | `datetime` | NO |  |  |  |
| `planned_end` | `datetime` | NO |  |  |  |
| `reservation_state` | `ENUM(RESERVED, RELEASED, CONSUMED)` | NO | `'RESERVED'` |  | RELEASED on plan cancellation |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_pc_assigned` | `((assigned_user_id is null) or (assigned_at is not null))` |
| `chk_pc_resource` | `(((component_type = 'BERTH') and (berth_id is not null) and (tugboat_id is null) and (terminal_id is null)) or ((component_type = 'TUG') and (berth_id is null) and (tugboat_id is not null) and (terminal_id is null)) or ((component_type = 'TERMINAL_OPS') and (berth_id is null) and (tugboat_id is null) and (terminal_id is not null)) or ((component_type = 'PILOT') and (berth_id is null) and (tugboat_id is null) and (terminal_id is null)))` |
| `chk_pc_time` | `(planned_end > planned_start)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `fk_pc_assigner` | No | assigned_by |
| `fk_pc_plan` | No | operational_plan_id |
| `fk_pc_terminal` | No | terminal_id |
| `idx_pc_berth_time` | No | berth_id, planned_start |
| `idx_pc_tug_time` | No | tugboat_id, planned_start |
| `idx_pc_user_time` | No | assigned_user_id, planned_start |
| `uq_pc_id_type` | Yes | id, component_type |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_pc_assignee` | assigned_user_id | [`user`](#user) (id) |  |
| `fk_pc_assigner` | assigned_by | [`user`](#user) (id) |  |
| `fk_pc_berth` | berth_id | [`berth`](#berth) (id) |  |
| `fk_pc_plan` | operational_plan_id | [`operational_plan`](#operational_plan) (id) |  |
| `fk_pc_terminal` | terminal_id | [`terminal`](#terminal) (id) |  |
| `fk_pc_tug` | tugboat_id | [`tugboat`](#tugboat) (id) |  |

**Referenced by:** [`storage_movement`](#storage_movement).plan_component_id, [`actual_status_update`](#actual_status_update).plan_component_id, component_type, [`deviation_log`](#deviation_log).plan_component_id

---
