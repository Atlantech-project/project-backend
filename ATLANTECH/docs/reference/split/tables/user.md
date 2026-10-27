<!-- Generated from docs/reference/database/PortSync.sql lines 103-112 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `user` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `username` varchar(50) UNIQUE NOT NULL COMMENT 'Login name, stored lowercase',
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(30) COMMENT 'Contact only, not used for login',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Active users count against license max_users',
  `must_change_password` boolean NOT NULL DEFAULT false COMMENT '1 = one-time password in use; must change before any other action',
  `created_at` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

<!-- Generated from docs/reference/database/PortSync.md lines 378-399 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `user`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `name` | `varchar(120)` | NO |  |  |  |
| `username` | `varchar(50)` | NO |  | UQ | Login name, stored lowercase |
| `password_hash` | `varchar(255)` | NO |  |  |  |
| `phone` | `varchar(30)` | YES |  |  | Contact only, not used for login |
| `is_active` | `tinyint(1)` | NO | `1` |  | Active users count against license max_users |
| `must_change_password` | `boolean` | NO | `false` |  | 1 = one-time password in use; must change before any other action |
| `created_at` | `datetime` | NO | `CURRENT_TIMESTAMP` |  |  |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_user_username_lower` | `((username = lower(username)) and (char_length(username) >= 3))` |

**Referenced by:** [`user_device`](#user_device).user_id, [`recommendation_review`](#recommendation_review).user_id, [`port_setting`](#port_setting).updated_by, [`user_role_assignment`](#user_role_assignment).user_id, [`pilot_profile`](#pilot_profile).user_id, [`pilot_schedule`](#pilot_schedule).created_by, [`tug_master_profile`](#tug_master_profile).user_id, [`tug_master_schedule`](#tug_master_schedule).created_by, [`storage_movement`](#storage_movement).recorded_by, [`safety_constraint`](#safety_constraint).created_by, [`berthing_request`](#berthing_request).submitted_by, [`berthing_request_status_history`](#berthing_request_status_history).changed_by, [`baplie_file`](#baplie_file).uploaded_by, [`entry_window_proposal`](#entry_window_proposal).confirmed_by, [`solver_run`](#solver_run).triggered_by, [`recommendation_override`](#recommendation_override).overridden_by, [`terminal_recommendation`](#terminal_recommendation).decided_by, [`operational_plan`](#operational_plan).confirmed_by, [`plan_component`](#plan_component).assigned_user_id, [`plan_component`](#plan_component).assigned_by, [`actual_status_update`](#actual_status_update).reported_by, [`retraining_run`](#retraining_run).reviewed_by, [`audit_log`](#audit_log).actor_id, [`notification_log`](#notification_log).user_id, [`baplie_file`](#baplie_file).corrected_by, [`entry_window_proposal`](#entry_window_proposal).rejected_by, [`forecast_run`](#forecast_run).submitted_by, [`terminal_recommendation`](#terminal_recommendation).exported_by

---
