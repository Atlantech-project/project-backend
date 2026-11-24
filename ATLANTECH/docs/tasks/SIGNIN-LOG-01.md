# SIGNIN-LOG-01 · Track successful sign-ins and sign-outs outside the audit log

| | |
| --- | --- |
| Status | Draft (open questions in section 8) |
| Phase / package | Outside the roadmap · follow-up of P1-C1 Q10 and P1-C4 |
| Sprint · feature | Sprint 3 proposed (lead decides) · FT-02 Sign-in and session |
| Effort (plan) | 1.5 days (estimate) |
| Branch | `feature/SIGNIN-LOG-01-sign-in-events` |
| Depends on | P1-C1, P1-C3, P1-C4 (merged) · P2-E3 (merged) |
| Blocked by | nothing |
| Unblocks | nothing yet; a later read screen or endpoint for the Port Admin (not in this task) |

## 1. What is asked

Today a successful sign-in or sign-out writes nothing anywhere durable on the web, and on mobile it leaves only the token row. The lead decided on 2026-10-29 (P1-C1 Q10) that they are **not** audit rows, and that decision stays. This task adds a separate, light record so the Port Admin side can later answer "who signed in or out, when and from where", without filling `audit_logs`.

Chosen on 2026-11-24 (lead: "same as your recommendations"):

1. A new table `sign_in_events`, one row per successful sign-in and sign-out, kept 180 days and pruned by a scheduled command.
2. A new column `personal_access_tokens.revoked_reason`, so the token history that already exists says why each token ended.
3. Nothing in `audit_logs`, and no new columns on `users` (decided no).

**Done when** (each line is checkable by a test):

- [ ] A successful web sign-in writes one `sign_in_events` row (`SIGN_IN`, `WEB`, the user, the IP); a failed one writes none there (it stays a `REJECTED_ATTEMPT` in `audit_logs`, as today).
- [ ] A successful mobile sign-in writes one row (`SIGN_IN`, `MOBILE`, the device, the IP) in the same transaction as the new token.
- [ ] A web logout and a mobile logout each write one `SIGN_OUT` row.
- [ ] Every place that revokes a token sets `revoked_reason`: `LOGOUT`, `REPLACED`, `PASSWORD_CHANGE`, `DEVICE_REVOKED`, `ACCOUNT_DISABLED`. A token that was revoked before this task keeps a NULL reason.
- [ ] If writing the event fails, the sign-in or sign-out still answers as before and the failure is logged (see Q-B).
- [ ] `atlantech:prune-sign-in-events` deletes rows older than the configured days, in chunks, and is scheduled daily. It never touches `audit_logs` or `personal_access_tokens`.
- [ ] `audit_logs` is unchanged by any successful sign-in or sign-out (the Q10 decision still holds).
- [ ] `schema.sql`, the table ownership matrix and the guide are updated; the full suite passes.

**Out of scope:** an endpoint or screen that reads the events (needs a contract and a privacy decision, see Q-D); failed sign-ins (already audited); sessions that expire by themselves on the web (the server cannot see them end); `last_sign_in_*` columns on `users` (decided no).

## 2. Sources read

| Source | Section / ID | What it says for this task |
| --- | --- | --- |
| `docs/tasks/P1-C1.md` | Q10, N6 | Lead decision 2026-10-29: no audit row for a successful sign-in; only 401 and 403 are audited. No reason was recorded. |
| `docs/tasks/P1-C4.md` | line 48 | Logout writes no audit row (Q10); mobile logout only sets `revoked_at`. |
| `docs/reference/backend/atlantech_backend_tasks.md` | P1-C1 to C4 | The sign-in and sign-out tasks this builds on. |
| Requirements | FR-GL-02, FR-GL-03, NFR-SEC-6 | FR-GL-02: every saved write has an audit entry and every refused attempt is stored. FR-GL-03: no hard delete of users, admins, tokens, ports or master data. NFR-SEC-6: the audit log is append-only. |
| Code on `develop` `554b09c` | `AuthController::login/logout`, `MobileSignInService::issueToken`, `PasswordChangeService`, `DeviceRevocationService`, `EnsureTokenDevice`, `EnsureAccountUsable`, `PersonalAccessToken` | Where a sign-in, a sign-out and each token revocation happen (see section 3). |
| `database/migrations/2026_11_05_100000_create_personal_access_tokens_table.php` | | Token rows are never deleted; the table is a framework table with no worker grant. |

## 3. Design

### Endpoints

None. No route, request or response changes, so no OpenAPI or readiness-pack change except one line in FT-02's Known gaps (see section 5).

### Data

**New table `sign_in_events`** (Backend writes, no worker grant, like `personal_access_tokens`):

| Column | Type | Why |
| --- | --- | --- |
| `id` | bigint unsigned, PK | |
| `user_id` | bigint unsigned, NOT NULL, FK `users` | Only a known user signs in successfully; the event is meaningless without one. |
| `event` | ENUM `SIGN_IN`, `SIGN_OUT` | Two values, SQL names equal the PHP enum (project rule). |
| `channel` | ENUM `WEB`, `MOBILE` | Web and mobile are different tracks (cookie session vs token); a Port Admin will ask which. |
| `user_device_id` | bigint unsigned, NULL, FK `user_devices` | Set for mobile only; says which tablet without copying the device id string. |
| `ip_address` | varchar(45), NULL | 45 characters hold the longest IPv6 text form; NULL when the request has none (a console call). |
| `occurred_at` | datetime(6), NOT NULL | Microseconds keep two events of one user in order, like the audit log. |

Indexes: `(user_id, occurred_at)` for "events of this user"; `(occurred_at)` for the daily prune. No other index: the table is small and mostly written.

**Change to `personal_access_tokens`:** new nullable column `revoked_reason` ENUM `LOGOUT`, `REPLACED`, `PASSWORD_CHANGE`, `DEVICE_REVOKED`, `ACCOUNT_DISABLED`, and `CHECK (revoked_reason IS NULL OR revoked_at IS NOT NULL)`. Why nullable: tokens revoked before this task have no known reason, and a made-up value would be wrong history. Why the CHECK: a reason on a live token is a bug the database can catch.

**Where each reason is set** (the six places on `develop` that end a token):

| Reason | Place | When |
| --- | --- | --- |
| `LOGOUT` | `AuthController::logout` | The user signs out on mobile. |
| `REPLACED` | `MobileSignInService::issueToken` (`update(['revoked_at' => now()])` on the device's open tokens) | A new sign-in on the same device ends the old token. |
| `PASSWORD_CHANGE` | `PasswordChangeService::change` | The user's other tokens end when the password changes. |
| `DEVICE_REVOKED` | `DeviceRevocationService` (revoke, revoke all) and `EnsureTokenDevice` | The Port Admin cancels a device, or a request arrives from a cancelled device. |
| `ACCOUNT_DISABLED` | `EnsureAccountUsable` | A request arrives for a deactivated user. |

`PersonalAccessToken::revoke()` gets a required reason argument, so no new revocation can forget it. Bulk `update()` calls set it in the same statement.

**Enums:** `SignInEventType` (`SIGN_IN`, `SIGN_OUT`), `SignInChannel` (`WEB`, `MOBILE`), `TokenRevokedReason`, in `app/Modules/Access/Enums`, with SQL-equal names (CT-21 style; not part of the shared status vocabulary because no other team reads them).

**Model:** `SignInEvent` in `app/Modules/Access/Models`, never updated (`UPDATED_AT` off), like `AuditLog` in spirit: `update()` and `delete()` are refused outside the prune command, which uses a query. The model has no relation to `audit_logs`.

**Retention:** `config/signin.php` `retention_days` = env `SIGN_IN_EVENTS_RETENTION_DAYS`, default 180. Why 180: long enough for an investigation after a quarter, short enough that the table stays near 0.3 million rows at the planned scale (estimate: 500 users × 4 events a day). The prune is not a violation of FR-GL-03 (users, admins, tokens, ports, master data) and not of NFR-SEC-6 (the audit log): the events table is neither. Q-E asks the lead to confirm this reading.

### Behaviour

- **Not part of the write chain.** Sign-in and sign-out are not writes of business data (P1-C3: licence mode does not apply), so the chain steps do not apply; the events are recorded inside the sign-in and sign-out code paths.
- **Same transaction where one exists.** Mobile sign-in: the event row is inserted inside the transaction of `issueToken`, so a token never exists without its event or the other way round. Web sign-in and both logouts have no transaction of their own: one insert after the action succeeds.
- **Failure of the insert does not block the user** (Q-B): the exception is caught, logged with the user id and channel, and the sign-in or sign-out answers as before. Reason: this is a convenience trail, not the legal audit; a full table or a lock must not stop a pilot from signing in at a berth.
- **No event for a refusal.** 401, 403 and 429 stay `REJECTED_ATTEMPT` rows in `audit_logs`, as the lead decided in N6.
- **Web logout order:** the event is written before the session is invalidated, while `$request->user()` still exists.
- Licence mode: not applicable (not a write). Events and messages: none; no SSE, no AI job.

## 4. Steps

| # | Step | Mode | Why this mode |
| --- | --- | --- | --- |
| 1 | Migrations: create `sign_in_events`; add `revoked_reason` and its CHECK to `personal_access_tokens` | C | A schema change; the lead approves the columns before they land. |
| 2 | Enums `SignInEventType`, `SignInChannel`, `TokenRevokedReason`; model `SignInEvent`; `config/signin.php` | A | Mechanical, following the pattern of the existing enums and models. |
| 3 | `SignInEventRecorder` service: `record(User, SignInEventType, SignInChannel, ?UserDevice, ?string $ip)` with the "log and carry on" failure rule | E | The one rule the lead should be able to explain (what is recorded, and why a failure must not block sign-in). |
| 4 | Wire the recorder into web login and logout, mobile login (inside the transaction) and mobile logout; make `revoke()` take a reason and set it at the six places in section 3 | C | Touches the sign-in path and every token revocation: security code, the lead approves the diff. |
| 5 | Prune command `atlantech:prune-sign-in-events`, daily schedule in `routes/console.php`, chunked delete | C | Deletes data; the lead approves the chunk size and the schedule before it lands. |
| 6 | Contract artefacts: regenerate `schema.sql`, update the table ownership matrix (Backend writes, no worker grant), the guide (F05, F05b, F07, F15), and a row in Coding Notes section 9 amending Q10 | C | Contract artefacts (CT-15, CT-17) are Mode C by rule. |
| 7 | Tests below; Pint | A | Straightforward paths, described in the table. |

### Tests

| Test | Covers |
| --- | --- |
| Web sign-in writes a `SIGN_IN` / `WEB` row with the user and IP | done-when 1 |
| A wrong password, a disabled account and a throttled request write no `sign_in_events` row, and the `REJECTED_ATTEMPT` row in `audit_logs` is unchanged | 1, Q10 still holds |
| Mobile sign-in writes `SIGN_IN` / `MOBILE` with `user_device_id`, in the same transaction (a forced failure after the insert leaves no token and no event) | 2, critical |
| Web logout and mobile logout each write `SIGN_OUT` | 3 |
| Each of the six revocation paths sets the right `revoked_reason`; a token revoked before this task keeps NULL; a live token with a reason is refused by the CHECK | 4, critical |
| The recorder swallows a database error, logs it, and sign-in still answers 200 | 5, critical |
| The prune deletes only rows older than the setting, in chunks, and leaves `audit_logs` and `personal_access_tokens` untouched | 6 |
| `successful sign-in leaves audit_logs unchanged` | 7 |
| `WorkerGrantsTest` and `ContractsSchemaTest` pass with the new table | 8 |

## 5. Other teams

**Needed from other teams:** nothing.

**This task changes something other teams use:**

| Team | What changes | Where it is published |
| --- | --- | --- |
| AI | `schema.sql` gets one table and one column; workers get no grant on either. A table the AI does not write, so it is not an `ai-impact` change, but the snapshot is shared (CT-17). | `contracts/db/schema.sql` (PortHub PR paired with the backend PR) |
| Frontend, Mobile, QA | Nothing visible. FT-02's Known gaps gets one line: successful sign-ins and sign-outs are recorded for 180 days and are not readable through the API yet. | `contracts/readiness/FT-02.md` |

Contract artefacts to update in the same PR: `schema.sql` (CT-17), the table ownership matrix (CT-15). No OpenAPI change.

## 6. Impact on other backend work

- A later read endpoint or screen for the events builds on this table; it needs its own contract, role (probably `PORT_GM`) and a privacy decision on the IP (Q-D).
- `PersonalAccessToken::revoke()` changes signature; the six places are all in `Access` and are updated here. Any later code that revokes a token must pass a reason.
- The table count in the guide (92 port tables) and the database diagram groups (F05b, `FRAMEWORK_IN_GROUPS`) change by one (Q-F).
- `IdempotentWrite` reads `revoked_at` only; unaffected.

## 7. Conflicts and risks

| Finding | Sources that disagree | Proposed resolution | Decided by |
| --- | --- | --- | --- |
| Q10 says successful sign-ins are not audited; this task records them elsewhere | P1-C1 Q10 vs the new table | Not a conflict: the decision was about `audit_logs`, which stays untouched. Record the amendment in Coding Notes section 9 so nobody reads Q10 as "no trail at all". | Proposed; the lead confirms in Q-G |
| Pruning rows vs "no hard delete" | FR-GL-03 vs the prune command | FR-GL-03 lists users, admins, tokens, ports and master data; events are none of them. Confirm in Q-E. | Open |
| An IP address is personal data | none in the documents | Stored for 180 days only, and not exposed by any endpoint in this task. A read endpoint must decide who sees it (Q-D). | Open |
| A forced failure of the recorder inside the mobile transaction would roll back the token | design | The mobile insert is inside the transaction by design (token and event together); the "log and carry on" rule applies to the web and logout paths and to errors that are not the transaction's own. Settle the exact rule in step 3, with the test described above. | Developer, in review |
| `sanctum:prune-expired` must never be scheduled (P1-C3) | P1-C3 | Unchanged; this task adds its own prune for events only. | Existing rule |

## 8. Open questions

| # | Question | Answer | Answered by / date |
| --- | --- | --- | --- |
| Q1 | Which alternatives? | A separate `sign_in_events` table plus `revoked_reason` on tokens; 180 days; nothing in `audit_logs` | Marwan · 2026-11-24 |
| Q2 | Add `last_sign_in_at` and `last_sign_in_channel` to `users`? | No. The last event is one query on `sign_in_events`, and a write on `users` at every sign-in would touch the version token. | Marwan · 2026-11-24 |
| Q-A | Sprint and ID: this is not in the roadmap. Which sprint, and is `SIGNIN-LOG-01` the right ID? Recommendation: Sprint 3, as a backend support task. | Open | |
| Q-B | If the insert of an event fails, should sign-in still succeed? Recommendation: yes, log and carry on (it is a trail, not the audit). | Open | |
| Q-C | Store the IP? Recommendation: yes, 45 characters, pruned with the row. | Open | |
| Q-D | Who may later read the events (probably `PORT_GM`) and may they see the IP? Decide when the read task is planned. | Open | |
| Q-E | Confirm that pruning `sign_in_events` after 180 days is outside FR-GL-03 and NFR-SEC-6. | Open | |
| Q-F | Does the new table count among the port tables (92 becomes 93) or among the framework tables like `personal_access_tokens`? Recommendation: a port table, because the Backend owns it and the Port Admin will read it. | Open | |
| Q-G | Add a row to Coding Notes section 9 stating that Q10 concerns `audit_logs` only? Recommendation: yes. | Open | |

## 9. Progress log

| Date | Step | Result |
| --- | --- | --- |
| 2026-11-24 | Plan | Written from the code on `develop` `554b09c`; Q1 and Q2 answered by the lead. Nothing built. |

## 10. Review

Not started.
