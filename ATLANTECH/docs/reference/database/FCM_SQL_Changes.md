# ATLANTECH — FCM Push: SQL Change Report (PortSync)

*23 October 2026 · Source: `FCM_Push_Change_Report.md` decisions C-1, C-2 and C-3 · Database: Port DB (`PortSync.sql`) only. The Central SaaS database is not touched.*

## 0. Decisions this report is based on

| # | Decision | Status |
|---|---|---|
| C-1 | The Firebase project and its service-account key belong to the **backend**. The key lives in Laravel configuration (`.env` or the secrets store), never in a database table | Decided 23 Oct |
| C-2 | Store the FCM token on `user_device` | Decided 23 Oct, this report |
| C-3 | Log push attempts in `notification_log` with a new channel `PUSH` | Decided 23 Oct |

## 1. Summary

| # | Table | Change | Depends on |
|---|---|---|---|
| 1 | `user_device` | Add `push_token`, `push_token_updated_at`, unique index, check rule | C-2 |
| 2 | `port_setting` | Seed row `push_enabled` (switch push off per port) | C-2 |
| 3 | `notification_log` | Add `PUSH` to `channel`, check rule "a push is never acknowledged" | C-3 (decided) |

No new table. No change to existing rows. All three changes are backward compatible: with push off or no token, the system behaves exactly as before.

## 2. Change 1 — `user_device` (push token)

**Why:** the server needs the FCM registration token of each phone to send the wake-up push. The token belongs to the app install on one device, and the device row already exists (`user_device`, one row per user and device).

**New columns**

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `push_token` | `varchar(255)` | YES | | FCM registration token. NULL = this device gets no push. Today's tokens are about 160 characters |
| `push_token_updated_at` | `datetime` | YES | | Last time the app registered or refreshed the token |

**Index:** `uq_user_device_push_token` UNIQUE (`push_token`). One token can sit on only one row, so a shared phone can never wake two users (MySQL allows many NULLs in a unique index).

**Check rule (kept as a table comment, like the other CHECKs in PortSync):** `chk_ud_push_revoked: ((revoked_at is null) or (push_token is null))`. A revoked device has no token.

**DDL**

```sql
ALTER TABLE `user_device`
  ADD COLUMN `push_token` varchar(255) NULL COMMENT 'FCM registration token; NULL = no push for this device',
  ADD COLUMN `push_token_updated_at` datetime NULL COMMENT 'Last registration or refresh of push_token';

CREATE UNIQUE INDEX `uq_user_device_push_token` ON `user_device` (`push_token`);

ALTER TABLE `user_device` COMMENT = 'Never deleted: revoked_at is set instead.
CHECK chk_ud_push_revoked: ((revoked_at is null) or (push_token is null))
';
```

**Rules the backend enforces (no SQL can express them)**

| Event | What the backend does, in one transaction |
|---|---|
| App sends a token (`PUT /api/v1/devices/push-token`) | `UPDATE user_device SET push_token = NULL` where `push_token = :token` on any other row, then set it on the caller's row with `push_token_updated_at = now()` |
| Online logout | Set `push_token = NULL` on that device row |
| Device revoked (Port Admin) | `revoked_at = now()` and `push_token = NULL` |
| User deactivated | `push_token = NULL` on all the user's device rows |
| FCM answers `UNREGISTERED` or `INVALID_ARGUMENT` | `push_token = NULL` on that row |
| Sending a push | Only to rows where `user_id` = the notification's user, `revoked_at IS NULL`, `push_token IS NOT NULL` |

## 3. Change 2 — `port_setting` seed row

**Why:** a port must be able to turn push off (for example if Port IT refuses egress to Google, or a tender requires it). With the setting off, the system is the original design.

```sql
INSERT INTO `port_setting` (`setting_key`, `setting_value`, `value_type`, `description`)
VALUES ('push_enabled', 'true', 'BOOL', 'Send content-free FCM wake-up pushes to field phones');
```

This is data, not a schema change.

## 4. Change 3 — `notification_log.channel`

**Why:** each push attempt gets its own row, so staff can see whether the wake-up reached FCM. The `MOBILE` row stays the delivery and acknowledgement record.

| Column | Before | After |
|---|---|---|
| `channel` | `ENUM(WEB, MOBILE)` | `ENUM(WEB, MOBILE, PUSH)` |

**Check rule (table comment):** `chk_nl_push_no_ack: ((channel <> 'PUSH') or (status <> 'ACKNOWLEDGED'))`. A PUSH row is only `SENT` or `FAILED` (with `failure_reason`, already enforced by `chk_nl_failed`).

```sql
ALTER TABLE `notification_log`
  MODIFY `channel` ENUM ('WEB', 'MOBILE', 'PUSH') NOT NULL;

ALTER TABLE `notification_log` COMMENT = 'Each retry, escalation and push attempt is its own row.
CHECK chk_nl_ack: ((status = ''ACKNOWLEDGED'') = (acknowledged_at is not null))
CHECK chk_nl_failed: ((status = ''FAILED'') = (failure_reason is not null))
CHECK chk_nl_push_no_ack: ((channel <> ''PUSH'') or (status <> ''ACKNOWLEDGED''))
';
```

`SENT` on a PUSH row means FCM accepted the message, not that the phone showed it.

## 5. Not in the database

| Item | Where it lives |
|---|---|
| Firebase service-account key (JSON) | Laravel config or secrets store on the port server (C-1) |
| Firebase project id | Laravel config |
| Firebase app config (`google-services.json`, `GoogleService-Info.plist`) | Inside the Flutter build (one build for all ports) |
| APNs auth key (`.p8`) | Uploaded to the Firebase console, never on the port server |

## 6. Documents to update after acceptance

| Document | Change |
|---|---|
| `PortSync.md` | `user_device` table, `uq_user_device_push_token`, check comment; `notification_log.channel` `PUSH` and its check comment |
| `ClassDiagramContext.md` | 2A: UserDevice columns (close OI-20); 2D: NotificationChannel `PUSH` (close OI-19) |
| `ATLANTECH_Master_Project_Context_Final.md` | 7.3 "Mobile push (pending SQL change)" becomes a decided line |
| Class diagrams 2A and 2D | Draw the columns |
