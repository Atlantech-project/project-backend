# F11 · The write chain

Task origin: P2-D2 (Sprint 2, merged in PR #51 together with P2-D4, see F12). Facts checked against `develop` at `106b40e`. Paths are inside `project-backend/ATLANTECH/` unless stated. Plan file: `docs/tasks/P2-D2.md` (answers Q1 to Q4 and R1 of the lead are quoted below).

## 1. What it does

Every request that changes data must pass the **same checks in the same order**, and each check answers with its own status and code (FR-GL-01, CT-01 section 7). This feature builds the **order** and the **named places** for the checks. It does not build the content of every check.

The order, as the contract states it ("401, 403, 426, 423, 409 for the version token, 409 or 422 for business rules, 422 for plan limits"):

| # | Check | Answer | Built by | State on `develop` |
| --- | --- | --- | --- | --- |
| 1 | Authentication | 401 `UNAUTHENTICATED` | Laravel `auth:web` | real |
| 2 | Password gate and account state | 403 `PASSWORD_CHANGE_REQUIRED` / `ACCOUNT_DISABLED` | `EnsureAccountUsable` (F08) | real |
| 3 | Role (ability) | 403 `FORBIDDEN` | `EnsureAbility`, alias `ability:<name>` (F09) | real |
| 4 | Terminal scope | 403 `FORBIDDEN` | `EnsureTerminalScope`, alias `terminal.scope` (F09) | real |
| 5 | Minimum app version (mobile) | 426 `APP_UPDATE_REQUIRED` | `EnsureMinAppVersion`, alias `app.version` | **empty hook** (passes everything) |
| 6 | Licence mode and port status | 423 `LICENSE_READ_ONLY` | `EnsureLicenseAllowsWrite`, alias `license.write` | **empty hook** (passes everything) |
| (route binding) | `{user}` becomes a model, unknown id gives 404 | 404 `NOT_FOUND` | Laravel `SubstituteBindings` | real, placed here on purpose |
| 7 | Record version token | 409 `VERSION_CONFLICT` (422 when `version` is missing or malformed) | `EnsureRecordVersion`, alias `version.token:<param>` (F12) | real, opt-in per route |
| 8 | Business rules | 409 or 422 | inside the endpoint | per endpoint (not middleware) |
| 9 | Plan limits | 422 `PLAN_LIMIT_EXCEEDED` | inside the endpoint, last | not built (P2-D5) |

It gives you:

- one middleware **group** `port.api` that every signed-in route uses;
- one **priority list** that fixes the order whatever order a route lists its middleware in;
- two **hook classes** with their final names, aliases and positions (checks 5 and 6), so the tasks that fill them cannot move them;
- a **proof** (`WriteChainOrderTest`) that each refusal beats every later one.

It does **not** give you: the 426 comparison (task P1-C3, planned, see F15), the licence guard (P2-D5, not planned in detail, see F15), the plan-limit service, or any business rule. Steps 8 and 9 are code inside the endpoint (they need the validated record and the transaction), "rules first, plan limits last" (`docs/tasks/P2-D2.md` section 3).

Which requests each step applies to (lead's answer Q2, 2026-11-03): steps 1 to 4 on every request; step 5 on any request that carries `X-App-Version`; steps 6 and 7 on writes only (POST, PUT, PATCH, DELETE). Today steps 5 and 6 do nothing, and step 7 skips safe methods (GET, HEAD, OPTIONS) by itself.

## 2. Request or data flow

A `PATCH /api/v1/tugboats/5` (an imagined route, see section 9) from a signed-in user:

1. `public/index.php` boots `bootstrap/app.php`, routing under the prefix `api/v1` (`bootstrap/app.php:32`, `apiPrefix: 'api/v1'`). The `api` group runs first (Sanctum's stateful middleware is added by `statefulApi()`, line 35; session and cookie middleware).
2. The router matches the route and gathers its middleware: the `port.api` group is expanded into `auth:web`, `EnsureAccountUsable`, `EnsureMinAppVersion`, `EnsureLicenseAllowsWrite`; the route adds `ability:tug-fleet.manage` and `version.token:tugboat` (`bootstrap/app.php:44-49`).
3. Laravel **sorts** that list with the priority list (`bootstrap/app.php:50-68`). Sorting is by class and ignores parameters (`ability:x` is sorted as `EnsureAbility`). Anything not in the list keeps its place relative to the rest.
4. The pipeline runs, in this sorted order: session and cookies, `auth:web` (no user: 401), `EnsureAccountUsable` (inactive: 403 and the session is ended; `must_change_password` outside the three open routes: 403), `EnsureAbility` (role lacks the ability: 403, audited), `EnsureTerminalScope` (route has `{terminal}` that is not the caller's: 403, audited), `EnsureMinAppVersion` (pass-through), `EnsureLicenseAllowsWrite` (pass-through).
5. **Only now** `SubstituteBindings` turns `{tugboat}` into a model. An unknown id gives 404 here, after all the checks above passed. This is the lead's decision Q4: an unauthorised caller never learns whether a record exists.
6. `EnsureRecordVersion` (alias `version.token:tugboat`) reads the bound model, validates `version` (422) and compares it (409) (see F12 section 2).
7. The controller runs: it opens a transaction, calls `VersionToken::lockAndAssert` (F12), applies the business rules (409/422), then the plan limits (422, last), saves, writes the audit row in the same transaction, commits, and returns the resource with the new `version`.

A refusal at any step throws `ApiProblemException` (or a Laravel exception), and `ProblemRenderer` turns it into the RFC 9457 body (F03). Later steps never run.

## 3. Every piece of code

### 3.1 `bootstrap/app.php` (the chain lives here, nowhere else)

| Part | Lines | What it does and why |
| --- | --- | --- |
| `$middleware->statefulApi()` | 35 | Sanctum SPA cookie support for the API routes (F07). Not part of the chain itself. |
| Doc comment | 37-43 | States the order in one place. If the list and the comment ever disagree, the list wins (it is the code). |
| `$middleware->group('port.api', [...])` | 44-49 | The group every signed-in route uses. Members, in this order: `'auth:web'`, `EnsureAccountUsable::class`, `EnsureMinAppVersion::class`, `EnsureLicenseAllowsWrite::class`. It holds only **parameterless** members; the ones with a parameter (`ability:<name>`, `version.token:<param>`) and `terminal.scope` stay on the route (lead's answer Q1: "priority list plus named group(s)"). |
| `$middleware->priority([...])` | 50-68 | The list that fixes the order. It **replaces** Laravel's default priority list, so it also repeats the framework entries that must keep their relative order: `HandlePrecognitiveRequests`, `EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`, `ShareErrorsFromSession`, `AuthenticatesRequests` (the interface that `auth:web` implements), `ThrottleRequests`, `ThrottleRequestsWithRedis`, `AuthenticatesSessions`; then the project's chain: `EnsureAccountUsable`, `EnsureAbility`, `EnsureTerminalScope`, `EnsureMinAppVersion`, `EnsureLicenseAllowsWrite`; then `SubstituteBindings`, `EnsureRecordVersion`, `Authorize` (the framework's `can:` middleware, last). |
| `$middleware->redirectGuestsTo(...)` | 69-71 | For `api/*` returns `null`, so a guest gets a 401 problem body instead of a redirect to a login route. Other URLs go to `route('login')` (no route named `login` is defined in `routes/`; only a non-API URL could reach it). |
| `withExceptions` | 73-80 (starts at 74) | JSON for `api/*` and for any request that expects JSON; all exceptions go through `ProblemRenderer` (F03). |

Why the group is defined in `bootstrap/app.php` and not in a service provider: the group names classes of **three** modules (Access, License, and the list needs Shared). Access may not import License classes (module layer rule, A4), and `bootstrap/app.php` is outside the modules, so it may import all of them (progress log `docs/tasks/P2-D2.md`, 2026-11-03, "Deviation").

### 3.2 The aliases (where each name is registered)

| Alias | Class | Registered in |
| --- | --- | --- |
| `ability` | `EnsureAbility` | `AccessServiceProvider::boot` (`app/Modules/Access/AccessServiceProvider.php:27`) |
| `terminal.scope` | `EnsureTerminalScope` | same, line 28 |
| `app.version` | `EnsureMinAppVersion` | same, line 29 |
| `license.write` | `EnsureLicenseAllowsWrite` | `LicenseServiceProvider::boot` (`app/Modules/License/LicenseServiceProvider.php:16`) |
| `version.token` | `EnsureRecordVersion` | `SharedServiceProvider::boot` (`app/Modules/Shared/SharedServiceProvider.php:18`) |

The group does not use these aliases; it lists the classes. The aliases exist so a route (or a test) can name a step on its own, for example `app.version` on a route that is not in `port.api`.

### 3.3 `EnsureMinAppVersion` (`app/Modules/Access/Http/Middleware/EnsureMinAppVersion.php`)

- `handle(Request $request, Closure $next): Response` returns `$next($request)`. It is a **pass-through**: today it does nothing, not even read the header.
- The class comment says what it will do: "read `X-App-Version`, compare, throw `ApiProblemException::appUpdateRequired`" (`ApiProblemException::appUpdateRequired(string $minAppVersion)` exists and adds the extension member `min_app_version`; `ApiProblemException.php:60-63`). Owner of the content: task **P1-C3** (the mobile sign-in chain, planned, not merged, see F15). The value to compare comes from the `port_settings` row `min_app_version` (seeded as `1.0.0`, F13); P1-C5 plans a `MinimumAppVersionProvider` for it (plan only).
- Why it sits in Access: the mobile app is part of sign-in and device handling (Access owns devices, F06).
- Its position must not change ("do not move it", class comment). That is what `WriteChainOrderTest` guards.

### 3.4 `EnsureLicenseAllowsWrite` (`app/Modules/License/Http/Middleware/EnsureLicenseAllowsWrite.php`)

- Same shape: `handle` returns `$next($request)`.
- Intended content (class comment): "writes only; reads always pass; the route tags give the READ_ONLY and SETUP allow-lists"; a write the licence mode refuses answers 423 `LICENSE_READ_ONLY`. Owner: task **P2-D5** (named in `docs/tasks/P2-D2.md`; D5 is a row in `docs/reference/backend/atlantech_backend_tasks.md:73`, no plan file yet). DEC-54 (Closed) says licence work is future work, which is why the class stays empty.
- What data it will read: the `license_states` table, model `LicenseState` (module License, the only license model; enums `LicenseMode`, `LicenseStatus`, `PortStatus`, `ReadOnlyReason`). Nothing reads that model yet (`grep` of `app/` finds only the model and enums).

### 3.5 What fills the third step (`EnsureRecordVersion`)

It is real code, covered in F12. This file only fixes its place: after `SubstituteBindings`, before the controller.

### 3.6 Route binding and `terminal.scope` (why order matters here)

`SubstituteBindings` is listed after the scope checks (`bootstrap/app.php:65`). `EnsureTerminalScope` therefore runs **before** `{terminal}` is a model and reads the raw route value: `$request->route('terminal')` may be a string id or, if binding already happened, an object with `getKey()`; the middleware handles both (`EnsureTerminalScope.php:39-45`), and a non-numeric value from a terminal-scoped user is refused 403 (lines 47-53). The plan said the same: "`terminal.scope` must read the raw route id because it now runs before `SubstituteBindings`" (`docs/tasks/P2-D2.md` section 3).

### 3.7 Related existing test helper

`tests/Pest.php` provides `useSchemaDatabase()` (points the default connection at `DB_SCHEMA_TEST_DATABASE`, default `atlantech_testing`, and runs `migrate:fresh` once per run) and `makeUserWithRole(?Role $role, array $attributes = [], ?OrganizationUnit $unit = null): User` (creates a user with one active role in the matching unit). Both are used by the chain tests.

## 4. Why this design

| Decision | Reason | Source |
| --- | --- | --- |
| The order is pinned by the **priority list**, not by the order a route lists its middleware | A developer who writes `->middleware(['version.token:x', 'ability:y'])` must still get the contract order. A rule that depends on every author remembering the order will be broken. | Lead's answer Q1 (2026-11-03); `docs/tasks/P2-D2.md` section 8 |
| A **group** for the parameterless members | `ability:<name>` takes a parameter and cannot sit in a fixed group; the group removes four lines of boilerplate from every route and makes it impossible to forget the 426 and 423 steps. | Q1; plan section 7 |
| **Hooks only** for 426, 423 and the version token in this task | Other people own the content (P1-C3, P2-D5, P2-D4). Creating the names and the order first lets them fill the bodies without a merge fight over the order. | Q3 |
| `SubstituteBindings` **after** `ability` and `terminal.scope` | Laravel's default binds models before authorisation, so a user without rights would get 404 for a missing record and 403 for an existing one, which leaks that the record exists. | Q4 (lead: "after both, so no 404 leaks a record's existence") |
| 401 and the password gate apply to **every** request, version and licence to writes only | Reads must stay possible in READ_ONLY mode and need no version. | Q2 |
| Steps 8 and 9 are **not** middleware | They need the validated record and the transaction (rules, then limits). | plan section 3 |
| The route coverage test checks "ability route runs inside `port.api`" instead of the raw order | `Route::gatherMiddleware()` returns the group **name** unexpanded, so a test that searches it for `EnsureAccountUsable` never finds it. The order itself is proven by behaviour in `WriteChainOrderTest`. | Review R1, lead's choice 2026-11-04 |
| Rejected | A single big middleware that runs all the steps; a provider-defined group (breaks the layer rule); relying on route authors. | plan sections 3 and 7 |

## 5. How it connects

- **Uses:** `EnsureAccountUsable` (F08), `EnsureAbility` and `EnsureTerminalScope` (F09), `EnsureRecordVersion` and `VersionToken` (F12), `ApiProblemException` and `ProblemRenderer` (F03), `AuditService` (F10) through the refusals.
- **Used by:** every signed-in route. Today: `auth/me`, `auth/change-password`, `auth/logout`, `GET audit-logs` (`routes/api.php:19-24`). `auth/login` is outside the group (nobody is signed in yet).
- **Waiting to use it:** P2-E1..E3 (users, roles, devices), P2-F1..F5 (master data CRUD), every later write endpoint (F15).
- **Guard change coming:** P1-C3's plan moves the signed-in guard from `auth:web` to `auth:sanctum`. The guard now lives in the `port.api` group in `bootstrap/app.php`, so that change goes there, not in `routes/api.php` (note in `docs/tasks/P2-D2.md` section 10). A sibling group for the mobile guard was suggested in the plan ("`port.api.mobile` = token guard + the same four members"); it does not exist.
- **Module layer rule:** the group is in `bootstrap/app.php` (outside the modules) for the reason in 3.1. The hook classes are in the module that will own their data.
- **Tests that guard routes:** `RouteAbilityCoverageTest` (F09) requires `ability:` on every API route (except 4 named ones), requires `port.api` on every route that has `ability:`, and `terminal.scope` on every route with a `{terminal}` parameter. `RouteVersionCoverageTest` (F12) requires `version.token:` on every PUT and PATCH route.

## 6. Data

No tables of its own, no migration. Data touched by the steps:

| Step | Reads / writes | Owner |
| --- | --- | --- |
| Authentication | `sessions` (web session), `users` | Access |
| Password gate | `users.is_active`, `users.must_change_password`; writes `audit_logs` (`REJECTED_ATTEMPT`) | Access, Shared |
| Ability, terminal scope | `user_role_assignments`, `organization_units`; writes `audit_logs` on refusal | Access |
| 426 (future) | `port_settings.min_app_version`, `user_devices` | MasterData / Access |
| 423 (future) | `license_states` | License |
| Version token | the bound record's `updated_at DATETIME(6)`; writes `audit_logs` on refusal | the record's module |

## 7. Tests

`tests/Feature/Api/WriteChainOrderTest.php` (5 tests; 27 tests pass together with F12's files, run on `atlantech_testing_g4`, see section 10).

How the test works: the three hook steps are empty or opt-in, so the test cannot prove their order with the real classes. `fakeHookSteps()` (lines 24-43) binds, in the container, a fake class in place of `EnsureMinAppVersion`, `EnsureLicenseAllowsWrite` and `EnsureRecordVersion`. A fake throws its own `ApiProblemException` (426, 423, 409) when the request header `X-Fail` lists its status, otherwise it passes. `beforeEach` (45-71) opens a transaction (rolled back in `afterEach`), sets Sanctum's stateful domain and the `Origin`/`Referer` headers, and registers five throw-away routes under `/api/v1/test/...`:

| Test route | Middleware, in the order written | Purpose |
| --- | --- | --- |
| `POST test/chain` | `api, port.api, ability:users.manage, version.token` | the normal order |
| `POST test/chain-hooks` | `api, port.api, ability:users.manage` | the real (empty) hooks |
| `POST test/chain-reversed` | `api, version.token, ability:users.manage, port.api` | listed in the wrong order |
| `POST test/terminals/{terminal}` | `api, version.token, terminal.scope, app.version, ability:port-state.read, port.api` | also reversed; has a `{terminal}` |
| `GET test/users/{user}` | `api, port.api, ability:users.manage`, closure with `User $user` | route binding order |

| Test (name in the file) | What it proves |
| --- | --- |
| `passes a request through the app version and licence hooks while they are pass-through` | With the real classes, a Port GM request with `X-App-Version: 0.0.1` to `test/chain-hooks` gets 200: today 426 and 423 never fire. When P1-C3 or P2-D5 fills a hook, this test must be changed on purpose (an old version header will then be refused). |
| `answers the first failing step of the chain` (runs for `test/chain` and `test/chain-reversed`) | With the fakes set to fail at 426, 423 and 409 at once: a guest gets 401 `UNAUTHENTICATED`; a user with `must_change_password` gets 403 `PASSWORD_CHANGE_REQUIRED`; a `PORT_STAFF` user (no `users.manage`) gets 403 `FORBIDDEN`; a `PORT_GM` gets 426 `APP_UPDATE_REQUIRED`; with `X-Fail: 423,409` gets 423 `LICENSE_READ_ONLY`; with `X-Fail: 409` gets 409 `VERSION_CONFLICT`; with no failure gets 200. Each case fails **all** later steps too, so it shows the earlier code wins. Running on the reversed route proves the priority list orders the steps, not the route author. |
| `answers 403 before 404 for an unauthorised caller and 404 for an allowed one` | `GET test/users/999999`: `PORT_STAFF` gets 403 `FORBIDDEN`; `PORT_GM` gets 404. Binding runs after `ability`. |
| `refuses another terminal with 403 before 426, 423 and 409, and before any 404` | A `TERMINAL_OFFICER` asking for another terminal's id gets 403; for a **non-existent** terminal id (999999) also 403 (not 404, because `terminal.scope` reads the raw id before binding; note the route's closure takes no model, so no 404 could come from it anyway: the proof of "before binding" for models is the previous test); for its own terminal gets 426 (the first fake that fails); with no failure, 200. |

Run: `vendor/bin/pest tests/Feature/Api/WriteChainOrderTest.php`.

Other tests that touch the chain: `RouteAbilityCoverageTest` (the route rules above), `WebSignInTest`, `ProfileTest`, `PasswordChangeTest` (the `auth/*` routes moved into `port.api` and behave as before), `AbilityMiddlewareTest`, `TerminalScopeTest` (F09).

Not covered by any test: the group's membership as a list (a deleted member would only show if a test covered that step: the 426 and 423 steps are empty, so removing `EnsureMinAppVersion` from the group would not fail anything today); two simultaneous requests; a route that adds `app.version` and also sits in `port.api` (both name the same class; the test `test/terminals/{terminal}` does this and passes, but the test does not count how many times the fake runs).

## 8. Gotchas and invariants

- **Never use `auth:web` + `EnsureAccountUsable` directly on a route.** You would skip the 426 and 423 steps. Use `port.api`. `RouteAbilityCoverageTest` catches a route that has `ability:` but not `port.api`; it does **not** catch a route with neither (a route with no ability must be on its four-name allow-list, which also catches it).
- **Do not reorder the priority list**, and do not delete an entry. The list **replaces** the framework default; a class that is missing from it is sorted by Laravel's fallback rules (it keeps its position relative to others), which is how an order breaks silently. When Laravel upgrades its default list, compare it with this one.
- **A new middleware of the chain must be added to the priority list** (for example a sixth check); otherwise it runs in the route-listed order.
- **`terminal.scope` must stay before `SubstituteBindings`**, and must keep reading the raw id. If someone makes it read a bound model only, the 403-before-404 guarantee for terminals breaks.
- **`throttle:5,1` runs before the password gate** (throttle is earlier in the list). A disabled user's repeated `auth/change-password` attempts count against the throttle before the 403 (review note, `docs/tasks/P2-D2.md` section 10). Harmless.
- **The hooks do not audit.** When P1-C3 and P2-D5 fill them, FR-GL-02 says every refused attempt is audited. P1-C3's plan says a 426 is not audited (the lead to confirm). Decide at that time and use `AuditService::rejectedAttempt` (F10).
- **Pass-through hooks do not hide a bug:** `X-App-Version` is not read anywhere today, so nothing is validated about it.
- **Tests that bind fakes** use `app()->bind(Class, closure)`. Because `EnsureRecordVersion` has a constructor dependency (`AuditService`), the fake replaces the whole class, including its audit; do not copy that fake into a test that needs the audit row.
- **Every test in this file runs inside a transaction that is rolled back.** The audit rows written by `EnsureAccountUsable` and `AccessRefusal` therefore disappear after the test; the tests do not assert them.

## 9. How to extend it

**Write a new signed-in route correctly** (an imagined `PATCH /tugboats/{tugboat}`; compare the real shape in `tests/Feature/Api/VersionTokenTest.php:24-35`):

```php
// routes/api.php, inside Route::middleware('port.api')->group(...)
Route::patch('tugboats/{tugboat}', [TugboatController::class, 'update'])
    ->middleware(['ability:tug-fleet.manage', 'version.token:tugboat'])
    ->name('tugboats.update');
```

Checklist (from the P2-D2 and P2-D4 plans, section 6):

1. The route is inside `Route::middleware('port.api')`. Never add `auth` or `EnsureAccountUsable` yourself.
2. `ability:<name>` with an ability that exists in `Ability` (F09). A typo is caught by `RouteAbilityCoverageTest` ("names only abilities that exist").
3. If the URL has `{terminal}`, add `terminal.scope`. If the terminal id comes in the body or query, call `TerminalScope::authorize` in the endpoint (F09).
4. On an update (PUT/PATCH), add `version.token:<param>`; the parameter name must equal the route parameter and the route must bind a model (the middleware throws `LogicException` otherwise, F12). On another write that changes an existing record (for example `POST users/{user}/deactivate`), add it too; the coverage test does **not** require it for POST (see F12 gotchas).
5. Do not put the middleware in any special order. The priority list orders them.
6. Inside the controller or service: open the transaction, `VersionToken::lockAndAssert`, then business rules (409/422), then plan limits (422), then save and audit in the same transaction (F10, F12).
7. Update `openapi/port-api.yaml` in the same PR (F04); document the 401, 403, 409 and 422 responses with the shared `$ref`s.
8. Test the route: one test for each refusal code it can return and the success path (see `VersionTokenTest` for the shape).

**Fill a hook** (when P1-C3 or P2-D5 starts): write the body in the existing class, keep the class name, namespace and the priority list untouched; throw `ApiProblemException` (426: `ApiProblemException::appUpdateRequired($min)`; 423: `new ApiProblemException(ErrorCode::LicenseReadOnly)`); add the refused attempt to the audit; update the first test of `WriteChainOrderTest` (it relies on the pass-through); add cases to the order test.

**Add a mobile group** (P1-C3): define a second group in `bootstrap/app.php` with the token guard (for example `auth:sanctum`) and the same four members; the priority list already orders them. `AuthenticatesRequests` covers both guards.

**Add a new check to the chain**: add the class to the group (or to the route), add it to the priority list at the right place, add a fake and a case to `WriteChainOrderTest`, and change CT-01 section 7 (an approved contract: use the `contract-change` skill, F14).

## 10. Try it

From `project-backend/ATLANTECH` (PowerShell; `php` comes from Herd). The runs below were done on 2026-11-04 on `develop` `106b40e` with an own empty database (`atlantech_testing_g4`, created for this guide; the lead's `atlantech` database was not touched).

```bash
php artisan route:list --except-vendor
```
Real output: six routes: `GET|HEAD /`, `GET|HEAD api/v1/audit-logs`, `POST api/v1/auth/change-password`, `POST api/v1/auth/login`, `POST api/v1/auth/logout`, `GET|HEAD api/v1/auth/me`.

```bash
php artisan route:list --path=api/v1/audit-logs -v
```
Expected, not run (the `-v` flag lists middleware): the group name `port.api` and `ability:audit.read` are shown unexpanded or expanded depending on the Laravel version; the order shown is the order written, not the executed order. The executed order is only visible through behaviour (the test).

```powershell
$env:DB_DATABASE="atlantech_testing_g4"; $env:DB_SCHEMA_TEST_DATABASE="atlantech_testing_g4"
vendor/bin/pest tests/Feature/Api/WriteChainOrderTest.php
```
Real result (with F12's three files in the same call): `tests: 27, passed: 27, assertions: 114` in about 39 s. The first call builds the schema with `migrate:fresh` on the test database. Needs Docker MySQL running (`docker compose up -d`).

To see the order break (do not commit): in `bootstrap/app.php` move `SubstituteBindings::class` above `EnsureAbility::class` and run the file. Expected, not run: `answers 403 before 404 ...` fails (the staff caller gets 404). Restore the file afterwards.

## 11. Open points

- **Hooks are empty:** 426 (P1-C3, planned, waits for the CT-04 change for a revoked device) and 423 (P2-D5, not planned). See F15.
- **No mobile group yet** and the guard is still `auth:web` only. P1-C3 moves it to `auth:sanctum`, which also needs `EnsureAccountUsable::endSession()` to cope with no session (P1-C3 plan, "Guard move").
- **Group membership is not tested as a list.** Add a test when a hook is filled.
- **`RouteAbilityCoverageTest` file header comment** still says "ability middleware or allow-list" for the first test; the P2-D2 changes are in the fourth test. Cosmetic.
- **Audit of 426 and 423 refusals:** not decided (see 8).
- **Doc vs code:** `docs/tasks/P2-D2.md` section 3 lists a shorter priority list (eight classes). The code has the full list including the framework entries; the code is right.
- **Default Laravel priority list drift:** the replaced list must be re-checked on every Laravel major upgrade (Unverified: no test compares it with the framework's list).
