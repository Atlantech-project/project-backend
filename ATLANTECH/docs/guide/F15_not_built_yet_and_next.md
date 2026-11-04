# F15 · What is not built yet, and what comes next

This is the **handover file**. It lists, as verified against the code, what does **not** exist on `develop` at `106b40e` (Sprint 2 merged, checked 2026-11-04), what is only a plan, what blocks what, and a recommended order for the next work. Where a statement could not be checked it says **Unverified** and what to check. Paths are inside `project-backend/ATLANTECH/` unless stated. Everything marked "plan" was read with `git show <branch>:ATLANTECH/docs/tasks/<file>` from local, **unmerged** branches of the backend repo; plans are intentions, not code.

## 1. What it does

It answers four questions for whoever continues the work:

1. What is a stub, a hook or an empty shell in the code today?
2. Which tasks of the current sprint plan are planned but not implemented, and what do they wait for?
3. Which open decisions and contract changes block them?
4. What did Sprint 2 unblock, and what should be done next?

It does not describe anything that works: the other feature files do (F01 to F14).

## 2. Request or data flow

Not a runtime feature. How this list was produced (repeat it to refresh the file):

1. `git log -1` on `develop` (`106b40e`).
2. `php artisan route:list --except-vendor`: the real routes (section 3.1).
3. `find app/Modules -name '*.php'` split into models, enums and everything else: which modules have code beyond models (section 3.3).
4. `grep` of `app`, `routes`, `config`, `database` for names a plan promises (`AppVersionGate`, `MinimumAppVersionProvider`, `UserAdministrationService`, `FirstPortAdminSeeder`, `personal_access_tokens`, `Queue::`, `Redis::`, `Broadcast`): none found in code.
5. `node docs/reference/lookup.mjs <ID>` and `docs/reference/roadmap/roadmap-tasks.json` for each task's team, sprint, dependencies.
6. `git branch -a` and `git show <branch>:ATLANTECH/docs/tasks/<ID>.md` for the plan files that are not on `develop`.

## 3. Every piece of code

Here "piece" means each thing that is **absent or empty**, with the evidence.

### 3.1 The API surface that exists

`route:list --except-vendor` (real, 2026-11-04) shows exactly: `GET /` (skeleton welcome page), `GET api/v1/audit-logs`, `POST api/v1/auth/change-password`, `POST api/v1/auth/login`, `POST api/v1/auth/logout`, `GET api/v1/auth/me`. Plus the framework's `/up` and `/sanctum/csrf-cookie`. **There is no other endpoint.** No `system/info`, no `auth/mobile/login`, no `users`, `roles`, `devices`, no master-data CRUD, no licence, no berthing request, no BAPLIE, no sync, no SSE, no websocket route.

### 3.2 Stubs and hooks (named places with no content)

| Item | File | State | Evidence | Who fills it |
| --- | --- | --- | --- | --- |
| `EnsureMinAppVersion` (alias `app.version`, in group `port.api`) | `app/Modules/Access/Http/Middleware/EnsureMinAppVersion.php` | `handle` returns `$next($request)`; reads no header | the file (25 lines); nothing in `app/` reads `X-App-Version` (`grep`) | P1-C3 (plan), see 4 |
| `EnsureLicenseAllowsWrite` (alias `license.write`, in `port.api`) | `app/Modules/License/Http/Middleware/EnsureLicenseAllowsWrite.php` | pass-through | the file; `LicenseState` is referenced by no other class | P2-D5 (no plan file) |
| The **mobile guard** in the chain | `bootstrap/app.php` group `port.api` | only `auth:web` (session cookie). No Bearer-token guard, no `personal_access_tokens` table | `grep -rl personal_access_tokens database config app` finds nothing | P1-C3 (plan) |
| The `License` module beyond `LicenseState` | `app/Modules/License/` | one model `LicenseState`, four enums (`LicenseMode`, `LicenseStatus`, `PortStatus`, `ReadOnlyReason`), the hook middleware and the provider. **No** READ_ONLY/SETUP logic, no check-in client, no `GET /license/state`, no plan-limit service | `ls app/Modules/License/*`; provider comment says "SETUP, FULL and READ_ONLY modes, the check-in client and plan limits (P2, P6)" | P2-D5, P2-D6, P6-Q1..Q3 |
| `ApiProblemException::appUpdateRequired(string $minAppVersion)` | `Shared/Errors/ApiProblemException.php:60` | exists, adds `min_app_version`; **never thrown** by any class | `grep` | P1-C3 |
| `ErrorCode::LicenseReadOnly` (423), `PlanLimitExceeded` (422) | `Shared/Errors/ErrorCode.php` | cases exist ("reserved", DEC-54); never thrown | `grep` | P2-D5 |
| `AiBridge` job publisher, run rows, completion consumer, mock workers | `app/Modules/AiBridge/` | the provider's comment promises them ("job publisher (JSON envelope v1 on an unprefixed Redis connection), run rows, completion consumer and mock workers (P3)"); the module has **no model and no class** except the two commands `CreateWorkerAccounts` and `GenerateContractsSchema`. Nothing in `app/` calls `Queue::`, `Redis::`, `dispatch(` or `ShouldQueue` | `grep -rln "Queue::\|Redis::\|dispatch(\|ShouldQueue\|Broadcast\|Reverb" app routes` is empty | P3-I1 (Sprint 3, "Job publisher: JSON envelope v1 (RPUSH)…") and the P3 chain |
| `Realtime` module | `app/Modules/Realtime/RealtimeServiceProvider.php` | empty provider. `BROADCAST_CONNECTION=log`; no Reverb, no SSE endpoint, no `ResourceChanged` event | `.env.example`; F01 | P5-N2 and the Sprint 3 to 5 realtime tasks (`P2-F7` defines the `ResourceChanged` event) |
| Empty providers: `MasterData`, `Environment`, `Berthing`, `Baplie`, `Recommendation`, `Plan`, `Yard`, `Dispatch`, `Execution`, `Learning`, `Realtime` | `app/Modules/<M>/<M>ServiceProvider.php` | empty classes (no routes, gates or commands) | F01 section 3.9 | each module's later tasks |
| Shared kernel promises | `SharedServiceProvider` comment | "the SM-4 transition service, the ResourceChanged event, status enums, terminal-scope filters" | the provider registers only `version.token` and two commands | SM-4 transition service: P3-G2; `ResourceChanged`: P2-F7 |

### 3.3 Modules that are models and enums only

Counts from `ls` of `app/Modules/<M>/{Models,Enums}` and every other PHP file (2026-11-04):

| Module | Models | Enums | Other code |
| --- | --- | --- | --- |
| Shared | 2 (`AuditLog`, `AuditLogBuilder`) | 6 | audit service, errors, pagination, resources, validation, version token, middleware, 2 commands, 1 controller (16 files; real feature code) |
| Access | 5 | 5 | sign-in, password change, abilities, scopes, 4 middleware, 1 command, 1 controller (real feature code) |
| License | 1 | 4 | the hook middleware only |
| MasterData | 38 | 15 | **provider only (empty)** |
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

("Other code" counts files that are neither model nor enum; the table shows the kind.) So the product today is: schema and models for the whole system (F05, F06), the seeders (F13), the API base, sign-in, password gate, access control, audit, the write chain and the version token. **No domain endpoint of any later module exists.**

### 3.4 Data that is not seeded

No `license_states` row, no real first Port Admin (only demo users in non-production), no AI reference rows and AI `port_settings` keys (P2-F9), no `port_profiles` row in production (provisioning), no `safety_constraints` defaults (they depend on DEC-30/CT-25).

## 4. Why this design

Why things are left empty on purpose:

| Choice | Reason | Source |
| --- | --- | --- |
| Hooks first, content later | The order of the write chain had to be fixed before three people filled three steps. | P2-D2 plan, lead's answer Q3 (2026-11-03), see F11 |
| Licence is only a pass-through | DEC-54 (Closed): licence, SaaS and READ_ONLY are future work. | `split/decisions/DEC-54.md` |
| Empty module providers | The module layout and the layer test (`ModuleArchitectureTest`) are fixed first; a module cannot be added later without placing it. | P1-A1, F01 |
| Plans written but code not written for P2-E and P1-C | The lead decided to **implement the users, roles, devices and mobile sign-in chain by hand** ("plan only, the lead implements it by hand", scope change 2026-11-04 in the E1/E2/E3 plans). | P2-E1..E3 plans, section 8 |
| The mobile chain waits for a contract change | A device revoked by the Port Admin must be refused at sign-in, and CT-04 has no code for it. | P1-C3 plan Q2 |

## 5. How it connects

Dependency picture of the not-built work (arrows mean "needs"):

```
P2-E1 users ──► P2-E2 roles
   │      └──► P2-E3 devices ──► needs P1-C3 (personal_access_tokens, DEVICE_REVOKED)
P1-C5 /system/info ──► P1-C3 mobile sign-in ──► P1-C4 mobile logout
        │                  │  needs CT-04 change approved (DEVICE_REVOKED) before MERGE
        │                  └─► fills EnsureMinAppVersion (426) in the write chain
P1-B6 seeders (merged #48) ──► P1-C5, P1-C3, P1-C4 (seed row min_app_version, port_profiles id 1)
P2-D5 licence guard + plan limits (no plan) ──► fills EnsureLicenseAllowsWrite (423); P2-E1 plan limit uses a simple check first
P2-D1..D4 (merged) ──► P2-E*, P2-F1..F5, every endpoint
```

## 6. Data

Tables the unbuilt work will touch that do not exist yet or are not used:

| Table / column | State | Needed by |
| --- | --- | --- |
| `personal_access_tokens` (Sanctum, plus `user_device_id`, `revoked_at`) | **No migration on `develop`**. P1-C3's plan creates it in module Access with `user_device_id` (FK to `user_devices`, no cascade) and `revoked_at`; tokens are never deleted. It must not be granted to workers; the plan lists tests that name tables (`SchemaTablesTest`, `SchemaConstraintsTest`, `WorkerGrantsTest`) to update and says `schema.sql` is regenerated. | P1-C3, P1-C4, P2-E3 |
| `user_devices` | exists (model `UserDevice`); no code reads or writes it | P1-C3, P2-E3 |
| `license_states` (`max_users` and others) | exists (model `LicenseState`); nothing reads it | P2-E1 plan limit, P2-D5, P2-D6, P6-Q |
| `port_settings.min_app_version` | seeded `1.0.0` by F13; nothing reads it | P1-C5, P1-C3 |
| `port_profiles` id 1 | seeded only with the demo port; nothing reads it | P1-C5 (`/system/info`) |
| `users`, `user_role_assignments` | used by sign-in and scope; no write endpoints | P2-E1, P2-E2 |

## 7. Tests

There are no tests for unbuilt work, by definition. What exists that you must keep green while you build:

- `RouteAbilityCoverageTest`, `RouteVersionCoverageTest`, `WriteChainOrderTest` (F09, F11, F12): the first new route trips them if it skips `port.api`, `ability:` or `version.token:` (PUT/PATCH).
- `OpenApiDriftTest` (F04): a new route must be added to `openapi/port-api.yaml` in the same PR.
- `ModuleArchitectureTest` (F01): a new class must obey the layer order.
- `SchemaTablesTest`, `SchemaConstraintsTest`, `WorkerGrantsTest`, `ContractsSchemaTest` (F05, F14): they name table counts and the last migration; a new migration (the `personal_access_tokens` one) requires updating them (the P1-C3 plan lists this).
- The expected-pass-through case in `WriteChainOrderTest` must be edited when `EnsureMinAppVersion` or `EnsureLicenseAllowsWrite` gets a body (F11).

## 8. Gotchas and invariants

- **A plan is not code.** `docs/tasks/P2-E1.md` and the others for E and C exist only on local branches `claude/p2-e-users-404561` (tip `9822b57`) and `claude/p1-c-mobile-signin-097e28` (tip `522bdd4`), not on `develop` and not pushed as far as this guide could check (`git ls-remote origin` for both branch names printed nothing on 2026-11-04, so they are not on GitHub; if the network or access failed silently this is Unverified: re-run it). Do not tell another team these endpoints exist.
- **The plans were written when P1-B6 and P2-D2/D4 were not merged**, and say "waits for P1-B6" and "stacked on PR #51". Both are merged now (P1-B6 is PR #48, P2-D2 and P2-D4 are PR #51, all in `develop` `106b40e`), so those "waits" are satisfied. Re-read each plan's header before starting and update the Depends/Blocked lines.
- **P1-C3's plan moves the guard** to `auth:sanctum` "in `routes/api.php`", but the guard now lives in the `port.api` group in `bootstrap/app.php` (note in P2-D2 review): the change goes there (F11).
- **`EnsureAccountUsable::endSession()`** touches the session; with Bearer tokens there is no session, so P1-C3 must guard with `hasSession()` (its plan says so).
- **Terminal rules:** every new route with `{terminal}` needs `terminal.scope` (test-enforced); a body or query terminal id needs `TerminalScope::authorize` (F09).
- **Roadmap vs backend task file:** `P2-D5` and `P2-D6` appear in `docs/reference/backend/atlantech_backend_tasks.md:73-74` but are **not in** `roadmap-tasks.json` (`lookup.mjs P2-D5` prints no team or sprint). The roadmap is the authority on sprint and order (README Source of truth rule 2), so D5/D6 have no sprint on the roadmap; ask the lead. Meanwhile Sprint 3 master-data tasks and Sprint 5/6 licence tasks (`P6-Q1`) refer to D5.
- **Sprint 2 is "merged" but not complete:** by the roadmap, Sprint 2 backend tasks still open are P1-C3, P1-C4, P1-C5, P2-E1, P2-E2, P2-E3 and BE-PK2 (section 9.1). They carry over.

## 9. How to extend it

### 9.1 Status of every planned, unbuilt task (as of `develop` `106b40e`)

Team BE, Sprint 2 unless stated. "Plan" = a plan file on a local unmerged branch. Dependencies are from `lookup.mjs` and the plans.

| Task | What (title) | Sprint / feature | Plan file and status | Waits for | Unblocks |
| --- | --- | --- | --- | --- | --- |
| **P1-C5** | `GET /system/info`: port id and name, licence mode, token warning days, minimum app version. No sign-in, never 426; used as the connectivity check (DEC-58) | 2 / FT-02, effort 0.25 | `P1-C5.md` on `claude/p1-c-mobile-signin-097e28`: **Ready**. Plans a `MinimumAppVersionProvider` interface in Shared, implementation and binding in MasterData | P1-B6 (merged now): needs `min_app_version` and the `port_profiles` row | MB-P53 (port identification), P1-C3 (reuses the reader) |
| **P1-C3** | Mobile sign-in `POST /api/v1/auth/mobile/login`: headers `X-Device-Id`, `X-App-Version`; body `username`, `password`, `platform` (`ANDROID`/`IOS`); registers/updates `user_devices`; 426 below `min_app_version` before the password; opaque Sanctum token, 30-day sliding; signed-in routes also accept the Bearer token; new migration `personal_access_tokens` with `user_device_id` and `revoked_at`; revoked device refused with new code `DEVICE_REVOKED` (403, proposed name) | 2 / FT-02, effort 1 | `P1-C3.md`: **Ready**, "executes only after P1-B6 is merged and P1-C5 is done; **not mergeable** before the CT-04 change for the revoked device is approved". Plans `Shared\Services\AppVersionGate` + `Shared\Contracts\MinimumAppVersionProvider`, `MobileHeaders` middleware, `EnsureTokenDevice`, `MobileSignInService`; fills `EnsureMinAppVersion` | P1-C5, P1-B6 (merged), **CT-04 change (DEVICE_REVOKED) approved by the lead and the Mobile Team** (before merge only) | P1-C4, P2-E3, MB-P52, P5-N2, the 426 step of the write chain |
| **P1-C4** | Logout: web session ended (already built in P1-C2) and the mobile token gets `revoked_at` (204; the same token then 401 everywhere) | 2 / FT-02, effort 0.5 | `P1-C4.md`: **Ready** | P1-C3 code | MB-P58 (settings and session) |
| **P2-E1** | `POST /users` (server-generated one-time password shown once, `PLAN_LIMIT_EXCEEDED` 422), `POST /users/{user}/deactivate` and `/activate` under a row lock (409 `CONFLICT` for self or last Port Admin), `GET /users`, `GET /users/{user}`; `FirstPortAdminSeeder` from `.env`; new `ErrorCode::Conflict` (`CONFLICT`, 409); `UserAdministrationService` | 2 / FT-03, effort 1 (+0.5) | `P2-E1.md` on `claude/p2-e-users-404561`: **Ready** (plan only; **the lead implements it by hand**). Lead's answers: generic `CONFLICT` code; simple plan check now (Shared interface, License implementation); first admin by seeder; password generated by the server; list endpoints included; activate included | P2-D1, P2-D3 (merged), P2-D2/D4 (merged, #51) | P2-E2, P2-E3, FE-045 (P-23, P-24) |
| **P2-E2** | `POST /users/{user}/roles` (assign a role, unit checked; ends the current one in the same transaction, DEC-36) and `POST /users/{user}/roles/end` (`active_to`), both with the user's `version`; role history stays | 2 / FT-03, effort 0.75 | `P2-E2.md`: **Ready** (plan only). Answers: a Port Admin may change or end their own role and assign to a deactivated user; the last-admin rule still applies | P2-E1 code, DEC-36 (Closed) | FE-045 (P-24 role history) |
| **P2-E3** | List a user's devices; revoke one device or all mobile tokens (web sessions are not touched; allowed in READ_ONLY) | 2 / FT-03, effort 0.5 | `P2-E3.md`: **Ready** (plan only). Implementation "waits for P1-C3 on `develop`" (table, `PersonalAccessToken`, `DEVICE_REVOKED`) | P2-E1, **P1-C3** | FE-046 (P-25 Registered Devices) |
| **BE-PK2** | API readiness pack for FRR 3 (CT-23): endpoints, examples, demo data, live events and known gaps, written to `PortHub/contracts/readiness/FT-xx.md` with skill `api-readiness-pack` | 2 / FT-13 | **No plan file** (BE-PK1 is Done and in `docs/tasks`; PortHub has `readiness/FT-02.md` and the template only) | BE-PK1 (done); the pack should list what is merged: after E1..E3 and C3..C5 if they are to be in FRR 3 | PL-FRR3 (Sprint 3 Feature Readiness Review), BE-PK3 |
| **P2-D5** | Licence guard on `license_states` (READ_ONLY and SETUP allow-lists as route tags, 423) and the plan-limit service (422) | not on the roadmap (`atlantech_backend_tasks.md:73`, effort 1, depends on P2-D2, AIBD-023) | **no plan file** | P2-D2 (merged); DEC-54 treats licence as future work; open integration decision D-11 (weather pulls and Part 3 in READ_ONLY) blocks P6-Q1 | fills `EnsureLicenseAllowsWrite`; P2-D6 |
| **P2-D6** | `GET /license/state` for the banner | not on the roadmap (`:74`, effort 0.25, depends on P2-D5) | no plan | P2-D5 | the web banner |

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
| **P5-O2 (mobile sync)** | applies sync events once by `client_event_id` | after O1 | also needs the mobile token guard (P1-C3) |

Every one of these must use `port.api`, `ability:`, `terminal.scope` where there is a terminal, `version.token:` on updates, `VersionToken::lockAndAssert`, `AuditService`, then rules, then plan limits (F11, F12).

### 9.3 Open decisions and contracts that block work

| Item | State (shared master files, 2026-11-04) | Blocks |
| --- | --- | --- |
| **CT-04 change for a revoked device** | Not drafted as a contract change yet: the P1-C3 plan (section 3, "CT-04 change needed for Q2") proposes adding `403 DEVICE_REVOKED` (code, `message_key` `errors.device_revoked`) to the sign-in refusals. It needs the `contract-change` skill, a decision entry, and approval by the lead and the Mobile Team. CT-04 is Approved v1.1 (2026-10-31, DEC-58). | the **merge** of P1-C3 (and so P1-C4, P2-E3's implementation). The code can be written before. |
| **DEC-30 numeric thresholds register** | Open, owner AI Team, close-by Sprint 2 (overdue), status in `Code Notes/ATLANTECH_Decisions.md:43`. Depends on QA-AI1; decides CT-25 (reference data and thresholds inputs); affects AI-008, AI-072 | the port-wide default values of `safety_constraints` that the AI seed spec carries (P2-F9, P2-F5 defaults) |
| **DEC-59** | **Closed 2026-11-04** (Option 1) in the shared file and PortHub `main`; `docs/reference` copy still says Open (stale, run `sync-check --apply`) | nothing now |
| **CT-18 v1.1** | Approved 2026-11-04 | nothing now; the AI rows arrive with `seed-spec.json` |
| **DEC-54** (licence future work) and **D-11** (do weather pulls and Part 3 run in READ_ONLY) | DEC-54 Closed; D-11 open integration decision | P2-D5 content, P6-Q1 |
| **DEC-38** (where to activate the tide constituent set) | Open, owner UI/UX Team, close-by Sprint 3 (`Code Notes/ATLANTECH_Decisions.md:51`); DEC-27 (tidal constants source and datum, AI Team) is also Open, close-by Sprint 4 | P2-F5 (it lists DEC-38 as a dependency) |
| **P1-C3 assumed defaults A3..A14** | "Assumed default, lead to confirm at review" in the plan; question Z ("anything the documents don't say?") **not asked yet** | confirm before executing P1-C3 |

### 9.4 Recommended next steps (this guide's recommendation; the lead decides)

1. **Refresh the references first** (small, no code): run `node docs/reference/sync-check.mjs --apply` on a branch to bring in DEC-59 Closed and CT-18 v1.1; republish `schema.sql` with `composer contracts:schema` on a PortHub branch (it lacks `min_value`/`max_value`, F13, F14); fix the README so it tells how to run `migrate --seed` (F13). Push or merge the two plan branches' plan files into `develop` so they are not lost on a local branch.
2. **P1-C5** (0.25 day): smallest, no contract problem, gives `/system/info` and the reader P1-C3 reuses. Check `min_app_version` behaviour with the seeded `1.0.0`.
3. **Start the CT-04 change** with the `contract-change` skill now (it is on the critical path of the mobile chain and needs another team), in parallel with P1-C5.
4. **P1-C3 then P1-C4**: the mobile sign-in chain; write the code; hold the merge until CT-04 is approved. This fills the 426 step and adds the token guard, then update `WriteChainOrderTest` (F11).
5. **P2-E1, then E2, then E3** (the lead implements these by hand according to the plans). E1 first because E2 and E3 reuse its `UserAdministrationService`, `CONFLICT` code and last-Port-Admin rule; E3 needs P1-C3 merged. E1 is also the first endpoint that exercises the version token: add the two-connection race test the P2-D4 review asked for (F12).
6. **BE-PK2**: write the readiness pack for FRR 3 with the `api-readiness-pack` skill after the above are merged; list the unmerged ones as "Known gaps".
7. **Decide P2-D5/D6** with the lead (not on the roadmap): whether the 423 guard and plan-limit service are scheduled; P2-E1 uses a simple plan check meanwhile.
8. **Sprint 3 starts** (P2-F1..F5, P2-F9, P3-G1/G2, P3-I1): follow the route checklist in F11 section 9. Close or chase DEC-30 and DEC-38 before P2-F5 and P2-F9 need them.
9. Before Sprint 3 work, the lead should set the four things F11 to F14 flag as unowned: how the PortHub pointer PR is handled; whether CI should check published files; who updates `FT-02` readiness for the 32 demo users; who owns README upkeep.

### 9.5 How to start any task from here

`node docs/reference/lookup.mjs <ID>`, read `docs/tasks/<ID>.md` (or `git show <branch>:ATLANTECH/docs/tasks/<ID>.md`), run `backend-task-plan` if no plan exists, `backend-task-execute`, `backend-task-review`, `backend-pr-prepare` (F14). One branch per task `feature/<task-id>-<slug>` from `origin/develop`; PRs into `develop`; one PR per repo per task (the team decided not to open separate plan, sync or close-out PRs).

## 10. Try it

All read-only, from `project-backend/ATLANTECH`, run on 2026-11-04.

```powershell
git log --oneline -1
php artisan route:list --except-vendor
```
Real: `106b40e Merge pull request #51 from Atlantech-project/claude/p2-d2-write-chain-b488bb`; six routes (section 3.1).

```powershell
node docs/reference/lookup.mjs P1-C3 P1-C5 P2-E1 P2-E3 BE-PK2 P2-D5
```
Real (extract): `P1-C3 … team: BE · sprint: 2 · feature: FT-02`, `← unblocks: P1-C4 · P5-N2 · MB-P52`; `P2-E3 … ← unblocks: FE-046`; `BE-PK2 … sprint: 2 feature: FT-13 … ← unblocks: PL-FRR3 · BE-PK3`; `P2-D5 · task · Licence guard … effort: 1` **with no team or sprint** (not in the roadmap).

```powershell
git -C ..\ branch -a
git -C ..\ show claude/p1-c-mobile-signin-097e28:ATLANTECH/docs/tasks/P1-C3.md
```
Real: local branches `claude/p1-c-mobile-signin-097e28` and `claude/p2-e-users-404561` (and the Sprint 2 task branches); `P1-C3.md` header `Status | Ready (executes only after P1-B6 is merged and P1-C5 is done; not mergeable before the CT-04 change for the revoked device is approved, Q2)`.

```powershell
Select-String -Path (Get-ChildItem -Recurse app,routes,config,database -Include *.php).FullName -Pattern "personal_access_tokens|AppVersionGate|MinimumAppVersionProvider|UserAdministrationService|FirstPortAdminSeeder|X-App-Version" | Select-Object -First 5
```
Real: only the **comments** in `EnsureMinAppVersion.php` and `ApiProblemException.php` mention `X-App-Version` and `min_app_version`; no class and no table with those names exists.

`php artisan test` of the whole suite: not run (instructions forbade it). The sprint plans report 704 to 696 tests green on their branches.

## 11. Open points

- **Plan branches are local only:** `git ls-remote origin` found neither branch on 2026-11-04 (empty output; re-run to be sure). Push them or move the plan files to `develop` before the machine is lost.
- **Unverified:** whether the `docs/reference` copy of the decisions has other stale statuses beyond DEC-59 (run `sync-check`, then `lookup.mjs` on the IDs you need).
- **Unverified:** whether P1-C3/C4/C5 plans' "waits for P1-B6" is the only stale dependency. P1-B6 is merged, but the plans may still assume file locations from before PR #51 (for example `routes/api.php` for the guard; see gotchas).
- **Roadmap gap:** `P2-D5`, `P2-D6` are in the backend tasks file but not in `roadmap-tasks.json`; the roadmap's Sprint 3 and Sprint 6 licence tasks (P6-Q1..Q3) depend on them indirectly.
- **Sprint 2 exit:** by the roadmap, Sprint 2 backend scope is not finished (C3..C5, E1..E3, BE-PK2). The lead should say whether they move to Sprint 3 or Sprint 2 is extended; this affects `BE-PK2` and the FRR 3 date.
- **`WriteChainOrderTest` first test** must change when a hook is filled (F11).
- **`schema-auditor` agent text** about an integer `version` column is outdated (F14).
- **Findings from this guide's pass (not fixed, for the lead):** published `schema.sql` is behind (F13, F14); `docs/reference` decisions/contracts copies are stale (F13, F14); no test forces PUT/PATCH-like POST routes to carry `version.token` (F12); no CI check that published contract files are current (F14); `EnsureRecordVersion` reads `version` from any input source, not only the JSON body (F12); `docs/tasks/P2-D2.md` and `P2-D4.md` describe earlier shapes than the final code in places (F11, F12).
