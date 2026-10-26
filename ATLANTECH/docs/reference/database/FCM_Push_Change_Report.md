# ATLANTECH — FCM Push Wake-up: Change Report

*22 October 2026 · Decision: Option 2, FCM as a content-free wake-up signal only · Scope of this round: context documents only, no diagrams*

## 1. The decision

WebSocket event + REST fetch stays the only path that carries notification content. Firebase Cloud Messaging (APNs for iOS) is added only to wake a closed or backgrounded app so that a field user sees a time-critical task.

| Rule | Value |
|---|---|
| Payload | Generic visible text and at most an opaque notification id. No vessel, berth, time, person or cargo data |
| After the push | The app connects through the per-app VPN and fetches by REST (`GET /api/v1/sync/changes`) |
| Direction | Outbound HTTPS from the port server to FCM. Nothing calls into the port |
| Reliability | Best effort. A failure is recorded and blocks nothing. Retries (T+10, T+20) and escalation (T+30) are unchanged. A push is never acknowledgement |
| Default notification set | `PLAN_ASSIGNMENT`, T+10 and T+20 acknowledgement retries, `PRE_EXECUTION_REMINDER`, `PLAN_CANCELLED` (MOBILE channel, field users) |
| Token lifecycle | Registered after sign-in and on token refresh; one device, one active user; cleared on logout, device revoke, user deactivation, or when FCM reports it invalid |
| READ_ONLY | Pushes for allowed notifications continue; push-token registration is on the allowlist |
| Priority | P2, post-MVP (FRD v3.1 FW-14) |

## 2. What was updated

| File | Changes |
|---|---|
| `ATLANTECH_Master_Project_Context_Final.md` | 5.3 structure (FCM as external system); 5.4 protocol table (Mobile row reworded, new "Mobile push (wake-up)" row); **new 5.5** with the rules above; 7.3 pending SQL note; rule 4 in section 9 |
| `Functional Requirements.md` | Version **3.1**; change note in document control; section 2.3; conflicts table (Notifications); FR-AC-06, FR-AC-08, FR-AC-10, FR-AC-13; FR-DP-05 rewritten; **new F-DP-2, FR-DP-11 to FR-DP-15**; FR-MB-06; FR-LIC-02 allowlist; **new FW-14**; traceability row |
| `ClassDiagramContext.md` | Note in 0.3; UserDevice pending ops (registerPushToken, clearPushToken) and **OI-20**; NotificationLog pending op sendPushWakeUp and **OI-19**; NotificationChannel pending PUSH. No drawn columns added |
| `UML_Context.md` | **Not changed yet.** Only the first 262 KB of its 375 KB could be read, so rewriting it would delete the end of the file. The exact patch is in section 3; it touches only the first part of the file |

## 3. Patch for `UML_Context.md`

Five find-and-replace edits. The diagram walk-throughs further down (2.15, AD-13, AD-15 and others) are generated from the diagram code and stay as they are until the diagrams change; edit 5 records that gap.

**Edit 1 — section 1, Port Server bullet**

Find:
> a realtime server (Reverb: SSE to the web dashboard, WebSocket to the mobile app),

Replace with:
> a realtime server (Reverb: SSE to the web dashboard, WebSocket to the mobile app), a notification sender that also sends content-free FCM wake-up pushes to field phones (outbound only),

**Edit 2 — section 1, Mobile app bullet (append at the end)**

> A closed or backgrounded app is woken by a content-free FCM push (APNs on iOS) and then fetches the content through the VPN by REST.

**Edit 3 — section 4, rule 9**

Find:
> SSE to the web dashboard and WebSocket events to the mobile app, sent only after the database commit; there is no push service.

Replace with:
> SSE to the web dashboard and WebSocket events to the mobile app, sent only after the database commit. For time-critical field notifications the server also sends a content-free FCM wake-up push (no operational data); the content is always fetched by REST, and a push is best effort and never acknowledgement.

**Edit 4 — section 9, Clients row**

Find:
> | Clients | Vue.js web dashboard; Flutter mobile app | Web over HTTPS with SSE; mobile over HTTPS and WebSocket, offline-first. |

Replace with:
> | Clients | Vue.js web dashboard; Flutter mobile app | Web over HTTPS with SSE; mobile over HTTPS and WebSocket, offline-first, woken by a content-free FCM push when closed or in the background. |

**Edit 5 — section 11, replace the Notifications row and add one row**

Find:
> | Notifications | NFR-REL-4: push notification retries with backoff. | No push service: SSE and WebSocket after commit, re-send at T+10 and T+20 minutes, escalation at T+30. |

Replace with:
> | Notifications | NFR-REL-4: push notification retries with backoff. | SSE and WebSocket after commit, re-send at T+10 and T+20 minutes, escalation at T+30. |
> | Mobile push wake-up | Master Context 5.5 and FRD v3.1 (22 October 2026): content-free FCM wake-up push for time-critical field notifications. | Diagrams still show "no push service" (AD-13 note, 2.15, AD-15). For this topic only, the context and FRD are newer than the diagrams; the diagrams will be updated in a later round. |

## 4. Critical decisions still open

These change code, schema or deployment. Each has a recommendation; none is applied as a decision.

| # | Decision | Why it is critical | Recommendation |
|---|---|---|---|
| C-1 | **Who owns the Firebase project** | One build serves all ports, and the app's Firebase config is compiled into that build. A per-port Firebase project would need a per-port build | One ATLANTECH-owned Firebase project. Each port server gets its **own** service-account key (revocable per port), delivered at bootstrap like the licence public key and stored in configuration, not in the database |
| C-2 | **SQL for the push token** | Without it nothing can be built | `user_device.push_token varchar(255) NULL UNIQUE` and `push_token_updated_at datetime NULL` (ClassDiagramContext 2A OI-20) |
| C-3 | **How push sends are logged** | `notification_log` is the delivery record and audit source | Add `PUSH` to `notification_log.channel`; one row per push attempt, `SENT` or `FAILED` with `failure_reason`; never `ACKNOWLEDGED` (2D OI-19). Alternative: no logging, only the application log |
| C-4 | **Token hand-over between users** | A shared phone could otherwise wake user B with user A's tasks | Registering a token clears it from every other row (enforced by the UNIQUE index), and every push is sent only to the token of the row for the notification's `user_id` |
| C-5 | **iOS delivery style** | iOS throttles silent (data-only) pushes, so a silent wake-up is unreliable | Visible alert with the generic text on both platforms. Android may use a high-priority message |
| C-6 | **Network rules** | Port server egress and phone reception both need FCM traffic | Port IT allows server egress to the FCM endpoints (HTTPS 443). The MDM profile must not block Google Play Services or APNs on managed phones; pushes arrive outside the per-app VPN, which is acceptable because they carry no data |
| C-7 | **New API endpoint** | Contract for the Flutter and backend teams | `PUT /api/v1/devices/push-token` (body: token, platform), allowed in READ_ONLY; clearing happens on logout and revoke without a separate call |
| C-8 | **Notification set** | Too many pushes and users mute them | Keep the default set in section 1. Do not push web-oriented types (`RECOMMENDATION_READY`, `DRIFT_ALERT` and similar). Decide whether `ACK_ESCALATION` also goes to a manager's phone |
| C-9 | **MVP or post-MVP** | Affects the 6-week plan | Post-MVP (FW-14). Flip FR-DP-11 to FR-DP-15 to ✔ if the team wants it in the MVP |
| C-10 | **Tender and data-protection wording** | Google and Apple become processors of device tokens; a ministry buyer may ask | State in the proposal text that pushes carry no operational data and that FCM can be disabled per port (push sender off = original behaviour) |

## 5. Diagram changes for the later round

| Diagram | Change |
|---|---|
| UC-2 | New system actor "Push Service (FCM / APNs)"; new use cases "Register Push Token" (Mobile User) and "Send Wake-up Push" (included by Send Task Notifications and Escalations) |
| UC-3 | "Wake Up App by Push" extends "Receive Realtime Update" |
| 2.1 Mobile sign-in | After the token step: app sends the push token, server stores it on `user_device` |
| 2.2 Logout | Online mobile logout also clears the push token |
| 2.3 Revoke device, deactivate user | Push token cleared |
| 2.15 Dispatch | New participant FCM; after "Push task notification" a parallel "Send wake-up push"; same on the T+10 and T+20 retries |
| 2.17 Lost device | Port Admin revoke also clears the push token |
| 3.1 / 3.2 | Opening from a push triggers the sync cycle and `GET /sync/changes` |
| AD-13 | Replace note N1 ("No push service…") with the wake-up rule; A3 sends WS event and wake-up push |
| AD-15 | Note N1: a closed app is woken by push, then catches up by REST |
| AD-17 | Register the push token after sign-in; on a different user, clear the old binding |
| Class 2A / 2D | Draw the accepted columns from C-2 and C-3 |
| `Users & Actors.md` | Add the system actor "Push Service (FCM / APNs)" |
