# CT-04 · CT-05 · CT-06 · CT-07 · CT-19 · CT-20 · Remaining Backend Sprint 1 contracts

| | |
| --- | --- |
| Status | Done |
| Phase / package | Sprint 1 contracts (roadmap tasks owned by the Backend Team) |
| Sprint · feature | Sprint 1 · FT-01 Platform foundation and contracts |
| Branch | `feature/CT-sprint1-contracts` here and in PortHub |
| Depends on | CT-01 · CT-08 · DEC-05 · DEC-35 |
| Blocked by | nothing |
| Unblocks | P1-C3 · P1-C5 · P3-I4 · P3-I6 · P5-N1 · P5-N2 · P5-O1 · P5-O2 · FE-033 · FE-057 · FE-059 · FE-074 · FE-077 · MB-F4B · MB-F5 · MB-P52 · MB-P53 |

## 1. What is asked

Resolve the six contracts with the lead and publish them in PortHub. Paths go into `port-api.yaml` only with the code, so this PR adds none: no contract here matches a built endpoint.

**Done when:**

- [x] CT-04 and CT-20 text in the Contracts file, Approved.
- [x] `realtime-sse.md`, `realtime-mobile.md` and `mock-workers.md`, Approved v1.0.
- [x] `sync-events.md` written, Approved v1.1.
- [x] CT-07 approved by the Mobile Team (Farouk, on PortHub PR #27).
- [x] DEC-11, DEC-39, DEC-40 and DEC-44 closed in all three places.
- [x] Changed contracts copied to Code Notes and synced here.

**Out of scope:** CT-21, CT-23 and BE-PK1 (Mariam) · P1-B7 · DEC-41 (Mobile) · DEC-07, DEC-21, DEC-26, DEC-29 and DEC-43 (AI).

## 2. Sources read

| Source | What it says for this task |
| --- | --- |
| `requirements/Functional Requirements.md` FR-AC-04 to 08 | System info fields, device token, 426, logout |
| `requirements/Functional Requirements.md` FR-EX-01 to 08, FR-MB-02 to 08, FR-DP-04 to 10, FR-YD-03 | Statuses, Outbox rules, notification and SSE receivers |
| `uml/UML_Context.md` | `port_public_id`, `token_warning_days_left`, `DRIFT_ALERT` to the Port Admin (`PORT_GM`) |
| `ui/ATLANTECH_Screens.pdf` P-18, P-37, P-38, P-50, P-54, P-55 | Office roles, terminal scope, task fields, status labels |
| `database/FCM_Push_Change_Report.md` | Notification types meant for field users |
| PortHub `contracts/db/schema.sql` | Column names and types; no `personal_access_tokens`; no recommendation number; `plan_components` has no status column |
| Contracts CT-08 to CT-15 | Queues and C-06 outcomes for each mock scenario |

## 3. Decisions by the lead (Marwan, 2026-10-31)

| Topic | Decision |
| --- | --- |
| Mobile sign-in data | `X-Device-Id` and `X-App-Version` headers only; `platform` in the body |
| `port_id` | `port_profiles.public_id`, with `port_code` beside it |
| Licence in system info | Fields present and fixed: `FULL` and `null`, until the licence work (DEC-54) |
| Mobile token store | Sanctum `personal_access_tokens`, tied to a `user_devices` row; P1-C3 adds the table |
| SSE receivers | The table in `realtime-sse.md` |
| SSE catch-up | 1 hour, then `RESYNC` |
| Mobile event types | `TASK_UPDATED` and `NOTIFICATION_CREATED` |
| DEC-39 · DEC-40 · DEC-44 · DEC-11 | Option 1 each; the repeat of an `ACKNOWLEDGE` is recognised from the notification state |
| Sync rules | 100 events per request, the `reason_code` list, `MOBILE` or `TABLET`, `reported_at`, opaque `sync_token` |
| 423 on sync | None in v1.0 |
| Mock selection | Vessel tag, then `mock_scenario` JSON, then the default |
| CT-20 and open AI decisions | Close it now with nullable fields; `version_no` is computed and `version` stays the token |
| CT-07 Mobile review (DEC-58) | Acknowledge marks the whole re-send chain; CT-21 example is the error; server-set `system_label`; `GET /system/info` is the connectivity check |
| Approval | Five approved by the lead without team review; CT-07 approved by the lead and the Mobile Team |

## 4. What later tasks must do

- **P1-C3:** add `personal_access_tokens` to CT-17, and let the password change and logout routes accept the Bearer token.
- **P5-O2:** the "creation events 423 in READ_ONLY" text in the backend tasks file is deferred (DEC-54).
- **P3-I4:** the "Blocked: D-06" note in the backend tasks file is out of date, because DEC-05 is closed. Set the connection time limit and the worker pool size there.
- **P5-O1:** build `system_label` (CT-07), including its storage and any CT-17 addition. Check the 24-hour rule and the clear rules with the lead when planning.
- **P5-O3:** an acknowledgement marks all `SENT` rows of the chain.
- **UML:** the sync sequence in the Documentation copy needs three changes: `GET /system/ping` is out of date (DEC-58), the 100-event batch is missing (CT-07), and it must not say "flagged" on mobile (DEC-44).
- **Seeds:** `status_types` needs `STARTED` and `DONE` for `TERMINAL_OPS` (DEC-39).

## 5. Open points for the lead

- `TUG_STILL_BUSY` has no flow in the requirements or the UML. It is outside the MOBILE list in CT-06 until one exists.
- The CT-21 example lists `plan_components.status`, which is not a column, and `ESCALATED` in `notification.status`, which is not a value (DEC-58). CT-21 is Mariam's.

## 9. Progress log

| Date | Step | Result |
| --- | --- | --- |
| 2026-10-31 | The Mobile Team approved CT-07 | CT-07 Approved v1.1 |
| 2026-10-31 | Applied the Mobile review of CT-07 (DEC-58) | Back with the Mobile Team for approval |
| 2026-10-31 | Verified the findings against the sources, took the lead's decisions, wrote the contracts | CT-07 waits for the Mobile Team |
