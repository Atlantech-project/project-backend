# F15 · What is not built yet, and what comes next

This is the **handover file**. It lists, as verified against the code, what does **not** exist on `develop` at `9559d35` (checked 2026-11-06; the first version was written on 2026-11-04 at `106b40e`, Sprint 2 merged), what is only a plan, what blocks what, and a recommended order for the next work. Where a statement could not be checked it says **Unverified** and what to check. Paths are inside `project-backend/ATLANTECH/` unless stated.

**What changed since the first version (2026-11-04 to 2026-11-06):** the mobile sign-in stack was merged into `develop`: `GET /api/v1/system/info` (P1-C5, PR 54), mobile sign-in `POST /api/v1/auth/mobile/login` with the `personal_access_tokens` table, `EnsureTokenDevice` and a **real** `EnsureMinAppVersion` (P1-C3, PR 55), mobile logout (P1-C4, PR 56) and the review fixes (PR 57: a Bearer request must send `X-App-Version`; a token of a revoked device is refused; a password change revokes the user's other tokens; `auth:sanctum` guards the whole `port.api` group). So P1-C3, P1-C4 and P1-C5 are **built** and are no longer in the "not built" list. P2-E1, P2-E2 and P2-E3 are still plans on a local branch. Everything marked "plan" below was read with `git show <branch>:ATLANTECH/docs/tasks/<file>` from a local branch; plans are intentions, not code.

## 1. What it does

It answers four questions for whoever continues the work:

1. What is a stub, a hook or an empty shell in the code today?
2. Which tasks of the current sprint plan are planned but not implemented, and what do they wait for?
3. Which open decisions and contract changes block them?
4. What did Sprint 2 unblock, and what should be done next?

It does not describe anything that works: the other feature files do (F01 to F14). Section 3.5 lists, for orientation, what became real since 2026-11-04.

## 2. Request or data flow

Not a runtime feature. How this list was produced (repeat it to refresh the file):

1. `git log -1` on `develop` (`9559d35`).
2. `php artisan route:list --except-vendor`: the real routes (section 3.1).
3. `find app/Modules -name '*.php'` split into models, enums and everything else: which modules have code beyond models (section 3.3).
4. `grep` of `app`, `routes`, `config`, `database` for names a plan promises (`UserAdministrationService`, `FirstPortAdminSeeder`, `Queue::`, `Redis::`, `Broadcast`, `Reverb`): none found in code. (On 2026-11-04 the same search also looked for `personal_access_tokens`, `AppVersionGate` and `MinimumAppVersionProvider`; `personal_access_tokens` exists now, and the 426 reader exists under the names `MinAppVersionSource` and `AppVersion`.)
5. `node docs/reference/lookup.mjs <ID>` and `docs/reference/roadmap/roadmap-tasks.json` for each task's team, sprint, dependencies.
6. `git branch -a` and `git show <branch>:ATLANTECH/docs/tasks/<ID>.md` for the plan files that are not on `develop`.

## 3. Every piece of code

Here "piece" means each thing that is **absent or empty**, with the evidence.

### 3.1 The API surface that exists

`route:list --except-vendor` (real, 2026-11-06) shows exactly eight routes: `GET /` (skeleton welcome page), `GET api/v1/audit-logs`, `POST api/v1/auth/change-password`, `POST api/v1/auth/login`, `POST api/v1/auth/logout`, `GET api/v1/auth/me`, `POST api/v1/auth/mobile/login` and `GET api/v1/system/info`. Plus the framework's `/up` and `/sanctum/csrf-cookie`. **There is no other endpoint.** No `users`, `roles`, `devices`, no master-data CRUD, no licence, no berthing request, no BAPLIE, no sync, no SSE, no websocket route. (`system/info` and `auth/mobile/login` were added on 2026-11-05.)

### 3.2 Stubs and hooks (named places with no content)

| Item | File | State | Evidence | Who fills it |
| --- | --- | --- | --- | --- |
| `EnsureLicenseAllowsWrite` (alias `license.write`, in group `port.api`) | `app/Modules/License/Http/Middleware/EnsureLicenseAllowsWrite.php` | pass-through (`handle` returns `$next($request)`) | the file; `LicenseState` is referenced by no other class; `SystemInfoController` returns `LicenseMode::Full->value` as a fixed value | P2-D5 (no plan file) |
| The `License` module beyond `LicenseState` | `app/Modules/License/` | one model `LicenseState`, four enums (`LicenseMode`, `LicenseStatus`, `PortStatus`, `ReadOnlyReason`), the hook middleware and the provider. **No** READ_ONLY/SETUP logic, no check-in client, no `GET /license/state`, no plan-limit service | `ls app/Modules/License/*`; provider comment says "SETUP, FULL and READ_ONLY modes, the check-in client and plan limits (P2, P6)" | P2-D5, P2-D6, P6-Q1..Q3 |
| `ErrorCode::LicenseReadOnly` (423), `PlanLimitExceeded` (422) | `Shared/Errors/ErrorCode.php` | cases exist ("reserved", DEC-54); never thrown | `grep` | P2-D5 |
| `GET /system/info` fields `license_mode` and `token_warning_days_left` | `MasterData/Http/Controllers/SystemInfoController.php` | fixed: `license_mode` is always `FULL`, `token_warning_days_left` is always `null` ("Fixed until the licence work (DEC-54)") | the controller | the licence work (P2-D5/D6, P6-Q) |
| `AiBridge` job publisher, run rows, completion consumer, mock workers | `app/Modules/AiBridge/` | the provider's comment promises them ("job publisher (JSON envelope v1 on an unprefixed Redis connection), run rows, completion consumer and mock workers (P3)"); the module has **no model and no class** except the two commands `CreateWorkerAccounts` and `GenerateContractsSchema`. Nothing in `app/` calls `Queue::`, `Redis::`, `dispatch(` or `ShouldQueue` | `grep -rln "Queue::\|Redis::\|dispatch(\|ShouldQueue\|Broadcast\|Reverb" app routes` is empty | P3-I1 (Sprint 3, "Job publisher: JSON envelope v1 (RPUSH)…") and the P3 chain |
| `Realtime` module | `app/Modules/Realtime/RealtimeServiceProvider.php` | empty provider. `BROADCAST_CONNECTION=log`; no Reverb, no SSE endpoint, no `ResourceChanged` event | `.env.example`; F01 | P5-N2 and the Sprint 3 to 5 realtime tasks (`P2-F7` defines the `ResourceChanged` event) |
| Empty providers: `Environment`, `Berthing`, `Baplie`, `Recommendation`, `Plan`, `Yard`, `Dispatch`, `Execution`, `Learning`, `Realtime` | `app/Modules/<M>/<M>ServiceProvider.php` | empty classes (no routes, gates or commands). **`MasterData` is no longer in this list:** its provider binds `MinAppVersionSource` since P1-C3 | F01 section 3.9 | each module's later tasks |
| Shared kernel promises | `SharedServiceProvider` comment | "the SM-4 transition service, the ResourceChanged event, status enums, terminal-scope filters" | the provider registers only `version.token` and two commands | SM-4 transition service: P3-G2; `ResourceChanged`: P2-F7 |

Removed from this table on 2026-11-06 because they are built: `EnsureMinAppVersion` (real, F11), the mobile guard (`auth:sanctum` in the `port.api` group), `ApiProblemException::appUpdateRequired` (now thrown by `EnsureMinAppVersion`).

### 3.3 Modules that are models and enums only

Counts from `ls` of `app/Modules/<M>/Models/*.php`, `Enums` and every other PHP file (2026-11-06, 233 PHP files under `app/Modules`; 222 at `106b40e`):

| Module | Models | Enums | Other code |
| --- | --- | --- | --- |
| Shared | 2 (`AuditLog`, `AuditLogBuilder`) | 6 | 18 files: audit service, errors, pagination, resources, validation, version token, `AppVersion`, `MinAppVersionSource`, middleware, 2 commands, 1 controller (real feature code) |
| Access | 5 (`OrganizationUnit`, `PersonalAccessToken`, `User`, `UserDevice`, `UserRoleAssignment`) | 5 | 22 files: sign-in, mobile sign-in, password change, abilities, scopes, 6 middleware, 1 command, 2 controllers, requests, resources (real feature code) |
| License | 1 | 4 | the hook middleware only |
| MasterData | 38 | 15 | 3 files: provider (binds `MinAppVersionSource`), `SystemInfoController`, `PortSettingMinAppVersion` |
| Environment | 6 | 5 | provider only |
| Berthing | 3 | 5 | provider only |
| Baplie | 4 | 7 | provider only |
| Recommendation | 15 | 11 | provider only |
| Plan | 2 | 3 | provider only |
| Yard | 12 | 9 | provider only |
| Dispatch | 1 | 4 | provider only |
| Execution | 2 | 2 | provider only |
| Learning | 3 | 4 | provider only |
| AiBridge | 0 | 0 | two commands + provider |
| Realtime | 0 | 0 | provider only |

Correction to the 2026-11-04 table: it gave Access "5 models"; at that time Access had **4** model files plus a `Concerns` folder that the `ls` count included. `ModelLayerTest` skips `PersonalAccessToken` on purpose (it extends Sanctum's model, a framework table, not a CT-17 table).

So the product today is: schema and models for the whole system (F05, F06), the seeders (F13), the API base, web and mobile sign-in, password gate, access control, audit, the write chain and the version token, and `GET system/info`. **No domain endpoint of any later module exists.**

### 3.4 Data that is not seeded

No `license_states` row, no real first Port Admin (only demo users in non-production), no AI reference rows and AI `port_settings` keys (P2-F9), no `port_profiles` row in production (provisioning writes it; until then `GET system/info` answers 503 `SERVICE_UNAVAILABLE`), no `safety_constraints` defaults (they depend on DEC-30/CT-25).

### 3.5 Built since 2026-11-04 (for orientation; details in F07, F08, F11, F13, F14)

| Task | PR | What is real now | Where |
| --- | --- | --- | --- |
| P1-C5 | 54 | `GET /api/v1/system/info`, public, never 426: `port_id` (the profile's `public_id`), `port_code`, `port_name`, `license_mode` (fixed `FULL`), `token_warning_days_left` (fixed `null`), `min_app_version`; 503 while there is no `port_profiles` row 1 or no valid `min_app_version` | `MasterData/Http/Controllers/SystemInfoController.php`, `MasterData/Services/PortSettingMinAppVersion.php`, `Shared/Support/MinAppVersionSource.php`, `AppVersion.php` |
| P1-C3 | 55, 57 | `POST /api/v1/auth/mobile/login` (headers `X-Device-Id`, `X-App-Version`; body `username`, `password`, `platform`); the `personal_access_tokens` migration (`2026_11_05_100000`): Sanctum columns plus `user_device_id` (FK to `user_devices`) and `revoked_at`; model `PersonalAccessToken` (sliding 30 days, `revoke()`, `delete()` refused); `EnsureTokenDevice`, `EnsureMobileHeaders`; a real `EnsureMinAppVersion`; `ErrorCode::DeviceRevoked` (403) | `Access/Http/Controllers/MobileAuthController.php`, `Access/Services/MobileSignInService.php`, `Access/Http/Middleware/*`, `Access/Models/PersonalAccessToken.php` |
| P1-C4 | 56 | `POST /api/v1/auth/logout` revokes the Bearer token (204) and ends the web session for a cookie request | `AuthController::logout` |
| Review fixes | 57 | `X-App-Version` required on every Bearer request; a token whose device was revoked answers 401 and is revoked; a password change revokes the user's other tokens and keeps the current one (decision D-A); a deactivated user's token is revoked; malformed stored `min_app_version` answers 503 | `EnsureTokenDevice`, `PasswordChangeService`, `EnsureAccountUsable`, `PortSettingMinAppVersion` |

## 4. Why this design

Why things are left empty on purpose:

| Choice | Reason | Source |
| --- | --- | --- |
| Hooks first, content later | The order of the write chain had to be fixed before three people filled three steps. Two are filled now (426 and 409); 423 is not. | P2-D2 plan, lead's answer Q3 (2026-11-03), see F11 |
| Licence is only a pass-through | DEC-54 (Closed): licence, SaaS and READ_ONLY are future work. | `split/decisions/DEC-54.md` |
| Empty module providers | The module layout and the layer test (`ModuleArchitectureTest`) are fixed first; a module cannot be added later without placing it. | P1-A1, F01 |
| Plans written but code not written for P2-E | The lead decided to **implement the users, roles and devices endpoints by hand** ("plan only, the lead implements it by hand", scope change 2026-11-04 in the E1/E2/E3 plans). | P2-E1..E3 plans, section 8 |
| The mobile chain merged although CT-04 v1.2 is not yet approved | A device revoked by the Port Admin must be refused at sign-in; the code follows DEC-60 (Closed), the contract text still says "Proposed v1.2". | DEC-60; `P1-C3.md` Q2 |

## 5. How it connects

Dependency picture of the work that is still not built (arrows mean "needs"):

```
P2-E1 users ──► P2-E2 roles
   └──────────► P2-E3 devices ──► needs P1-C3: BUILT (personal_access_tokens, DEVICE_REVOKED)
P2-D5 licence guard + plan limits (no plan) ──► fills EnsureLicenseAllowsWrite (423); P2-E1 plan limit uses a simple check first
P2-D1..D4 (merged) ──► P2-E*, P2-F1..F5, every endpoint
P1-C3/C4/C5 (merged) ──► MB-P52, MB-P53, MB-P58 (Mobile), P5-N2, P2-E3
CT-04 v1.2 (Proposed) ──► Mobile Team's OK, then the contract header changes to Approved
```

## 6. Data

Tables the unbuilt work will touch, and their state:

| Table / column | State | Needed by |
| --- | --- | --- |
| `personal_access_tokens` | **Exists** (migration `2026_11_05_100000_create_personal_access_tokens_table`, 98th migration): `id`, `tokenable` morph, `user_device_id` (NOT NULL, FK `fk_pat_user_device`), `name` (the device id), `token` (unique 64), `abilities`, `last_used_at`, `expires_at` (indexed), `revoked_at`, timestamps. Never deleted. No worker grant. | P2-E3 (list devices, revoke) uses it |
| `user_devices` | exists; written by mobile sign-in (`device_id`, `platform`, `app_version`, `last_seen_at`) and read by `EnsureTokenDevice`; the code **never sets `revoked_at`** itself, so a revoked device can only come from P2-E3 or a manual update | P2-E3 |
| `license_states` (`max_users` and others) | exists (model `LicenseState`); nothing reads it | P2-E1 plan limit, P2-D5, P2-D6, P6-Q |
| `port_settings.min_app_version` | seeded `1.0.0` (F13); **read** by `PortSettingMinAppVersion` | built |
| `port_profiles` id 1 | seeded only with the demo port; **read** by `GET system/info` | built; provisioning for production not built |
| `users`, `user_role_assignments` | used by sign-in and scope; no write endpoints | P2-E1, P2-E2 |

## 7. Tests

There are no tests for unbuilt work, by definition. What exists that you must keep green while you build:

- `RouteAbilityCoverageTest`, `RouteVersionCoverageTest`, `WriteChainOrderTest` (F09, F11, F12): the first new route trips them if it skips `port.api`, `ability:` or `version.token:` (PUT/PATCH). The ability allow-list now has six names (`auth.login`, `auth.mobile.login`, `auth.me`, `auth.change-password`, `auth.logout`, `system.info`).
- `OpenApiDriftTest` (F04): a new route must be added to `openapi/port-api.yaml` (now 1.6.0) in the same PR.
- `ModuleArchitectureTest` (F01): a new class must obey the layer order.
- `SchemaTablesTest`, `SchemaConstraintsTest`, `WorkerGrantsTest`, `ContractsSchemaTest` (F05, F14): they name table counts and the last migration (101 tables, last `2026_11_05_100000_...` today); every new migration needs these updated.
- `TokenPruneNotScheduledTest`: do not schedule `sanctum:prune-expired` (tokens are never deleted).
- `MobileSignInTest`, `MobileTokenTest`, `MobileLogoutTest`, `SystemInfoTest`, `PasswordChangeTest` (81 tests, all passed on 2026-11-06 on an own database): the behaviour P2-E3 will extend.
- The first test of `WriteChainOrderTest` changed when `EnsureMinAppVersion` was filled; it must change again when `EnsureLicenseAllowsWrite` gets a body (F11).
- In tests, `tests/TestCase.php::be()` forgets the auth guards before each `actingAs`, because the `sanctum` guard keeps the first user it resolved (F11).

## 8. Gotchas and invariants

- **A plan is not code.** `docs/tasks/P2-E1.md`, `P2-E2.md` and `P2-E3.md` exist only on the local branch `claude/p2-e-users-404561` (tip `9822b57`, cut from `develop` at `5c6d3d4`), not on `develop` and not on GitHub (`git ls-remote origin "claude/p2-e-users-404561"` printed nothing on 2026-11-06; if the network or access failed silently this is Unverified: re-run it). The C-chain plans are on `develop` now (`P1-C3.md`, `P1-C4.md`, `P1-C5.md`), but their Status is still `In review`. Do not tell another team that E endpoints exist.
- **The E plans are older than the code they build on.** They say "P1-C3 ... not merged", "implementation waits for P1-C3 on `develop`" and "stacked on PR 51". P1-C3 and PR 51 are merged now, so those "waits" are satisfied. Re-read each plan's header, update the Depends/Blocked lines, and check the plan against the real classes before writing code: `PersonalAccessToken` (with `revoke()` and `device()`), `UserDevice`, `EnsureAccountUsable` (already revokes a deactivated user's mobile token), `PasswordChangeService` (already revokes other tokens).
- **`EnsureTokenDevice` and device revocation:** a token of a revoked device is refused with 401 on its next use, but the code that **sets** `user_devices.revoked_at` is P2-E3 (not built). Revoking "all tokens" must set `revoked_at` on the token rows (never delete).
- **Every Bearer request needs `X-Device-Id` and `X-App-Version`** (422 otherwise); a new endpoint for tablets needs nothing special, the group does it.
- **Terminal rules:** every new route with `{terminal}` needs `terminal.scope` (test-enforced); a body or query terminal id needs `TerminalScope::authorize` (F09).
- **Roadmap vs backend task file:** `P2-D5` and `P2-D6` appear in `docs/reference/backend/atlantech_backend_tasks.md:73-74` but are **not in** `roadmap-tasks.json` (`lookup.mjs P2-D5` prints no team or sprint). The roadmap is the authority on sprint and order (README Source of truth rule 2), so D5/D6 have no sprint on the roadmap; ask the lead. Meanwhile Sprint 3 master-data tasks and Sprint 5/6 licence tasks (`P6-Q1`) refer to D5.
- **Sprint 2 is "merged" but not complete:** by the roadmap, Sprint 2 backend tasks still open are P2-E1, P2-E2, P2-E3 and BE-PK2 (section 9.1). P1-C3, P1-C4 and P1-C5 are done. The open ones carry over.
- **`docs/reference` is behind the shared files** (five files `CHANGED` in `sync-check`, no DEC-60 in the repo index): `lookup.mjs DEC-60` prints "not in the index". Run `sync-check --apply` on a branch before planning a task that cites DEC-60 or CT-01 v1.4.

## 9. How to extend it

### 9.1 Status of every planned, unbuilt task (as of `develop` `9559d35`)

Team BE, Sprint 2 unless stated. "Plan" = a plan file on a local unmerged branch. Dependencies are from `lookup.mjs` and the plans.

| Task | What (title) | Sprint / feature | Plan file and status | Waits for | Unblocks |
| --- | --- | --- | --- | --- | --- |
| **P2-E1** | `POST /users` (server-generated one-time password shown once, `PLAN_LIMIT_EXCEEDED` 422), `POST /users/{user}/deactivate` and `/activate` under a row lock (409 `CONFLICT` for self or last Port Admin), `GET /users`, `GET /users/{user}`; `FirstPortAdminSeeder` from `.env`; new `ErrorCode::Conflict` (`CONFLICT`, 409); `UserAdministrationService` | 2 / FT-03, effort 1 (+0.5) | `P2-E1.md` on `claude/p2-e-users-404561`: **Ready** (plan only; **the lead implements it by hand**). Lead's answers: generic `CONFLICT` code; simple plan check now (Shared interface, License implementation); first admin by seeder; password generated by the server; list endpoints included; activate included | P2-D1, P2-D3, P2-D2/D4 (all merged) | P2-E2, P2-E3, FE-045 (P-23, P-24) |
| **P2-E2** | `POST /users/{user}/roles` (assign a role, unit checked; ends the current one in the same transaction, DEC-36) and `POST /users/{user}/roles/end` (`active_to`), both with the user's `version`; role history stays | 2 / FT-03, effort 0.75 | `P2-E2.md`: **Ready** (plan only). Answers: a Port Admin may change or end their own role and assign to a deactivated user; the last-admin rule still applies | P2-E1 code, DEC-36 (Closed) | FE-045 (P-24 role history) |
| **P2-E3** | List a user's devices; revoke one device or all mobile tokens (web sessions are not touched; allowed in READ_ONLY) | 2 / FT-03, effort 0.5 | `P2-E3.md`: **Ready** (plan only). Its text says the implementation "waits for P1-C3 on `develop`": that wait is over | P2-E1; P1-C3 is **built** | FE-046 (P-25 Registered Devices) |
| **BE-PK2** | API readiness pack for FRR 3 (CT-23): endpoints, examples, demo data, live events and known gaps, written to `PortHub/contracts/readiness/FT-xx.md` with skill `api-readiness-pack` | 2 / FT-13 | **No plan file** (BE-PK1 is Done and in `docs/tasks`; PortHub has `readiness/FT-02.md` and the template only) | BE-PK1 (done). The mobile sign-in endpoints it would document are merged; E1..E3 are not | PL-FRR3 (Sprint 3 Feature Readiness Review), BE-PK3 |
| **P2-D5** | Licence guard on `license_states` (READ_ONLY and SETUP allow-lists as route tags, 423) and the plan-limit service (422) | not on the roadmap (`atlantech_backend_tasks.md:73`, effort 1, depends on P2-D2, AIBD-023) | **no plan file** | P2-D2 (merged); DEC-54 treats licence as future work; open integration decision D-11 (weather pulls and Part 3 in READ_ONLY) blocks P6-Q1 | fills `EnsureLicenseAllowsWrite`; P2-D6 |
| **P2-D6** | `GET /license/state` for the banner | not on the roadmap (`:74`, effort 0.25, depends on P2-D5) | no plan | P2-D5 | the web banner |

Done since the first version (no longer "not built"): **P1-C5**, **P1-C3**, **P1-C4** (PRs 54 to 57). Their unblocked Mobile tasks: MB-P53 (port identification), MB-P52 (tablet login), MB-P58 (settings and session), and P5-N2 (mobile WebSocket) per `lookup.mjs`.

### 9.2 What Sprint 2 unblocked for Sprint 3 and later

All from `lookup.mjs`/`roadmap-tasks.json`:

| Task | What | Needs from Sprint 2 | Notes |
| --- | --- | --- | --- |
| **P2-F1** | Port profile, navigation channel (depth on chart datum), waypoints, typed `port_settings` editor (Sprint 3, FT-04) | `depends_on` P2-D2; refs P2-D1, P2-D3 | unblocks FE-047, FE-051. The `min_value`/`max_value` columns (F13) are for its range checks. |
| **P2-F2** | Terminals (creates the TERMINAL unit), berths (max LOA within length), `berth_terminal` (at least one per berth) | P2-D2 | unblocks P2-F6, P2-F7, P3-G1, FE-048/049; DEC-51 (shared berths) |
| **P2-F3** | Tugs, tug masters (one per tug), pilots, rank limits, shifts without overlap, pilot schedules | P2-D2 | unblocks P2-F7, FE-066/068/069/078 |
| **P2-F4** | CT equipment and capabilities; yard zones, blocks, slots with reefer, DG, OOG details | P2-D2 | unblocks FE-071/072 |
| **P2-F5** | Safety constraints (no overlap for same type and scope); tide constituent set activation with datum check | P2-D2 and **DEC-38** | affected by DEC-27, DEC-38; unblocks FE-076. Port-wide defaults wait for **DEC-30** (section 9.3) |
| **P2-F6, P2-F7** | Terminal productivity profiles; `ResourceChanged` domain event | after F2/F3 | |
| **P2-F9** | Seed the AI `port_settings` keys and AI reference rows from the AI-009 spec (Sprint 3, FT-04) | `depends_on` AI-009 and CT-18 (v1.1 Approved 2026-11-04) | uses the `min_value`/`max_value` columns and the CHAMPION `deployed_at` row of DEC-59 (F13) |
| **P3-G1 / P3-G2** | Create a berthing request (vessel by IMO, check digit); the SM-4 transition service: the only code that writes `berthing_requests.status` (status, history row and audit in one transaction) | P3-G2 `depends_on` P3-G1 and **P2-D3** (the audit service, merged) | P3-G2 unblocks P3-G3, P3-I2, P3-I7 |
| **P3-I1** | Job publisher: JSON envelope v1 (RPUSH), builders for C-01 to C-05 (Sprint 3) | CT-08..CT-12, DEC-04 | this is the AiBridge publisher (section 3.2) |
| **P5-O1** | "My tasks" and `GET /sync/changes` (Sprint 5, FT-09) | `depends_on` **P2-D1** (own-task scope, merged) | unblocks P5-O2, MB-F4B, MB-P54 |
| **P5-O2 (mobile sync)** | applies sync events once by `client_event_id` | after O1 | the mobile token guard it needs (P1-C3) is **built** now |

Every one of these must use `port.api`, `ability:`, `terminal.scope` where there is a terminal, `version.token:` on updates, `VersionToken::lockAndAssert`, `AuditService`, then rules, then plan limits (F11, F12).

### 9.3 Open decisions and contracts that block work

| Item | State (shared master files and PortHub `main`, 2026-11-06) | Blocks |
| --- | --- | --- |
| **CT-04 v1.2 (revoked device)** | **Proposed v1.2 (DEC-60), "waiting for Mobile Team approval"**; Approved v1.1 is the last approved version (the same line is in the shared `Code Notes` file and in PortHub). DEC-60 is Closed. `error-codes.md` v1.3 already lists `DEVICE_REVOKED` (introduced by P1-C3); CT-01 is v1.4 and CT-03 v1.1. The Backend wrote a hand-over note for the Mobile Team (`Code Notes/Mobile_Team_CT-04_v1.2_Handoff.md`, a draft "the lead sends"). The P1-C3 plan said not to merge before approval; the code merged anyway (PRs 55, 57). | Nothing in the code. The contract text stays "Proposed" until the Mobile Team agrees. |
| **DEC-30 numeric thresholds register** | Open, owner AI Team, close-by Sprint 2 (overdue), `Code Notes/ATLANTECH_Decisions.md:50` (line was 43 on 2026-11-04; the file grew). Depends on QA-AI1; decides CT-25; affects AI-008, AI-072 | the port-wide default values of `safety_constraints` that the AI seed spec carries (P2-F9, P2-F5 defaults) |
| **DEC-59** | **Closed 2026-11-04** (Option 1); the `docs/reference` copy still says Open (stale, run `sync-check --apply`) | nothing now |
| **DEC-60** | Closed (decision of 2026-11-01); not in the `docs/reference` index yet (stale copy) | nothing now |
| **CT-18 v1.1** | Approved 2026-11-04 | nothing now; the AI rows arrive with `seed-spec.json` |
| **DEC-54** (licence future work) and **D-11** (do weather pulls and Part 3 run in READ_ONLY) | DEC-54 Closed; D-11 open integration decision | P2-D5 content, P6-Q1 |
| **DEC-38** (where to activate the tide constituent set) | Open, owner UI/UX Team, close-by Sprint 3 (`Code Notes/ATLANTECH_Decisions.md`); DEC-27 (tidal constants source and datum, AI Team) is also Open, close-by Sprint 4 | P2-F5 (it lists DEC-38 as a dependency) |
| **P1-C3 defaults D3, D4, D6, D9, D10, D11** | still "Assumed default, lead to confirm at review" in the merged plan (order of 422/426, new sign-in revokes the old token, wrong device 401, office roles on mobile, `last_seen_at` at sign-in only, **426 not audited**) | confirm; D11 touches FR-GL-02 (F11) |

### 9.4 Recommended next steps (this guide's recommendation; the lead decides)

1. **Close out the merged mobile work** (small, no code): set `Status` to Done in `docs/tasks/P1-C3.md`, `P1-C4.md`, `P1-C5.md`; run `node docs/reference/sync-check.mjs --apply` on a branch to bring in the five changed documents (CT-01 v1.4, error-codes v1.3, CT-03 v1.1, Contracts, Decisions with DEC-59 and DEC-60 Closed); push or merge the E plan branch into `develop` so it is not lost on a local machine.
2. **Get the Mobile Team's OK on CT-04 v1.2** (send the hand-over note) and then change the CT-04 header from Proposed to Approved v1.2; confirm the assumed defaults D3 to D11 of P1-C3, especially D11 (audit of 426).
3. **P2-E1, then E2, then E3** (the lead implements these by hand according to the plans). First update each plan's header and facts to the merged state (section 8). E1 first because E2 and E3 reuse its `UserAdministrationService`, `CONFLICT` code and last-Port-Admin rule. E1 is also the first endpoint that exercises the version token: add the two-connection race test the P2-D4 review asked for (F12). E3 needs no more waiting: its table and model exist.
4. **BE-PK2**: write the readiness pack for FRR 3 with the `api-readiness-pack` skill after E1..E3 are merged (or list them as "Known gaps"); the mobile endpoints it can document now.
5. **Decide P2-D5/D6** with the lead (not on the roadmap): whether the 423 guard and plan-limit service are scheduled; P2-E1 uses a simple plan check meanwhile.
6. **Sprint 3 starts** (P2-F1..F5, P2-F9, P3-G1/G2, P3-I1): follow the route checklist in F11 section 9. Close or chase DEC-30 and DEC-38 before P2-F5 and P2-F9 need them.
7. Before Sprint 3 work, the lead should set the things F11 to F14 flag as unowned: how the PortHub pointer PR is handled; whether CI should check published files; who updates the `FT-02` readiness pack (it should now mention the mobile sign-in and the 32 demo users); who keeps the two copies of this guide (`Code Notes/Backend_Guide` and `ATLANTECH/docs/guide`) equal.

### 9.5 How to start any task from here

`node docs/reference/lookup.mjs <ID>`, read `docs/tasks/<ID>.md` (or `git show <branch>:ATLANTECH/docs/tasks/<ID>.md`), run `backend-task-plan` if no plan exists, `backend-task-execute`, `backend-task-review`, `backend-pr-prepare` (F14). One branch per task `feature/<task-id>-<slug>` from `origin/develop`; PRs into `develop`; one PR per repo per task (the team decided not to open separate plan, sync or close-out PRs).

## 10. Try it

All read-only, from `project-backend/ATLANTECH`, run on 2026-11-06.

```powershell
git log --oneline -1
php artisan route:list --except-vendor
```
Real: `9559d35 Merge pull request #57 from Atlantech-project/claude/p1-c-review-fixes-949f5a`; eight routes (section 3.1), including `POST api/v1/auth/mobile/login` and `GET api/v1/system/info`.

```powershell
node docs/reference/lookup.mjs P1-C3 P1-C5 P2-E1 P2-E3 BE-PK2 P2-D5 DEC-60
```
Real (extract, from the repo copy of the index): `P1-C3 … team: BE · sprint: 2 · feature: FT-02`, `← unblocks: P1-C4 · P5-N2 · MB-P52` (the roadmap does not record that the task is done); `P2-E3 … ← unblocks: FE-046`; `BE-PK2 … sprint: 2 feature: FT-13 … ← unblocks: PL-FRR3 · BE-PK3`; `P2-D5 · task · Licence guard … effort: 1` **with no team or sprint**; `DEC-60: not in the index` (stale `docs/reference`).

```powershell
git -C ..\ branch -a
git -C ..\ show claude/p2-e-users-404561:ATLANTECH/docs/tasks/P2-E3.md
```
Real: the local branch `claude/p2-e-users-404561` (tip `9822b57`, plans only: `git diff --stat` against `develop` shows three files, 482 lines); `P2-E3.md` header `Status | Ready (plan only; the lead implements it by hand, scope change 2026-11-04)` and `Depends on … P1-C3 … not merged` (outdated). `claude/p1-c-mobile-signin-097e28` still exists as a local branch; its content is on `develop`.

```powershell
Select-String -Path (Get-ChildItem -Recurse app,routes,config,database -Include *.php).FullName -Pattern "UserAdministrationService|FirstPortAdminSeeder|Queue::|Redis::" | Select-Object -First 5
```
Expected (this exact command was run on 2026-11-04 with a wider pattern list; the grep for `Queue::|Redis::|dispatch(|ShouldQueue|Broadcast|Reverb` over `app` and `routes` was empty on both dates): no match for the user-administration names.

```powershell
$env:DB_DATABASE="atlantech_testing_g4"; $env:DB_SCHEMA_TEST_DATABASE="atlantech_testing_g4"
vendor/bin/pest tests/Feature/Access/MobileSignInTest.php tests/Feature/Access/MobileTokenTest.php tests/Feature/Access/MobileLogoutTest.php tests/Feature/MasterData/SystemInfoTest.php tests/Feature/Access/PasswordChangeTest.php
```
Real: `81 passed (392 assertions)` in about 40 s.

`php artisan test` of the whole suite: not run (instructions forbade it). The P1-C3 plan reports 794 tests passed on its branch before the review fixes; not re-checked here.

## 11. Open points

- **Plan branch is local only:** `git ls-remote origin` for `claude/p2-e-users-404561` printed nothing on 2026-11-06 (empty output; re-run to be sure). Push it or move the plan files to `develop` before the machine is lost.
- **CT-04 v1.2 approval** by the Mobile Team is open; the contract header still says Proposed (section 9.3).
- **Plan statuses not closed out:** `P1-C3.md`, `P1-C4.md`, `P1-C5.md` say `In review`.
- **Unverified:** whether the `docs/reference` copy of the decisions has other stale statuses beyond DEC-59 and the missing DEC-60 (run `sync-check`, then `lookup.mjs` on the IDs you need).
- **Unverified:** whether the E plans assume anything else that changed with PRs 54 to 57 (for example how `EnsureAccountUsable` ends a mobile session now, or the `X-App-Version` requirement for Bearer tests). Read them against `F11` before implementing.
- **Roadmap gap:** `P2-D5`, `P2-D6` are in the backend tasks file but not in `roadmap-tasks.json`; the roadmap's Sprint 3 and Sprint 6 licence tasks (P6-Q1..Q3) depend on them indirectly.
- **Sprint 2 exit:** by the roadmap, Sprint 2 backend scope is not finished (E1..E3, BE-PK2). The lead should say whether they move to Sprint 3 or Sprint 2 is extended; this affects `BE-PK2` and the FRR 3 date.
- **`WriteChainOrderTest` first test** must change when the licence hook is filled (F11).
- **`schema-auditor` agent text** about an integer `version` column is outdated (F14).
- **Two copies of this guide** now exist (`Code Notes/Backend_Guide` and `ATLANTECH/docs/guide`); which is the master is Unverified (F14).
- **Findings from this guide's pass (not fixed, for the lead):** `docs/reference` decisions/contracts copies are stale (F13, F14); no test forces POST action routes that change an existing record to carry `version.token` (F12); no CI check that published contract files are current (F14); `EnsureRecordVersion` reads `version` from any input source, not only the JSON body (F12); `docs/tasks/P2-D2.md` and `P2-D4.md` describe earlier shapes than the final code in places (F11, F12); the 426 and the app-version 422 are not audited (F11). Resolved since 2026-11-04: the published `schema.sql` was behind and has been republished (F13, F14); the `-- DEMO (do not merge)` line in `database/grants/workers.sql` is gone (PR 57, with a test).
