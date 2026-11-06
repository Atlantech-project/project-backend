# F11 · The write chain

Task origin: P2-D2 (Sprint 2, merged in PR #51 together with P2-D4, see F12), extended by P1-C3 (mobile sign-in, PRs #55 and #57) and by IDEMP-01 (the `Idempotency-Key` of mobile writes, PR #62, section 3.11). Facts checked against `develop` at `58affe4` (updated 2026-11-06 after PR #62; first written at `106b40e`). Paths are inside `project-backend/ATLANTECH/` unless stated. Plan files: `docs/tasks/P2-D2.md` (answers Q1 to Q4 and R1 of the lead are quoted below) and `docs/tasks/P1-C3.md` (decisions D-A, D-B, device rules) and `docs/tasks/IDEMP-01.md` (design, the two security reviews, the lead's answers).

## 1. What it does

Every request that changes data must pass the **same checks in the same order**, and each check answers with its own status and code (FR-GL-01, CT-01 section 7). This feature builds the **order** and the **named places** for the checks; most of the steps are now real code, one (licence) is still an empty hook. Since IDEMP-01 the chain also has three **idempotency steps** for writes made with a mobile Bearer token (rows 0, 1c and 6b).

The order, as the contract states it ("401, 403, 426, 423, 409 for the version token, 409 or 422 for business rules, 422 for plan limits"), with the device step that P1-C3 added in front for Bearer requests and the idempotency steps of IDEMP-01 (rows 0, 1c, 6b):

| # | Check | Answer | Built by | State on `develop` |
| --- | --- | --- | --- | --- |
| 0 | Logout replay: a Bearer write that **revoked its own token** (logout) and was answered 204 is answered again from the store, before the token is checked. Locks the key for every other Bearer write | the stored 204 (or 503 `SERVICE_UNAVAILABLE` + `Retry-After` while the first request with this key still runs) | `IdempotentWrite`, alias `idempotency` (3.11) | real; **Bearer writes only** |
| 1 | Authentication (web session cookie first, then a mobile Bearer token) | 401 `UNAUTHENTICATED` | Laravel `auth:sanctum` (guard list `['web']` in `config/sanctum.php` plus tokens) | real |
| 1b | Device of the Bearer token: `X-Device-Id` and `X-App-Version` present and well-formed (422), device matches the token and is not revoked (401, audited) | 422 `VALIDATION_FAILED` / 401 `UNAUTHENTICATED` | `EnsureTokenDevice` | real; **Bearer requests only**, web requests pass |
| 1c | `Idempotency-Key` present and a UUID v4 | 422 `VALIDATION_FAILED` on the field `Idempotency-Key` | `RequireIdempotencyKey`, alias `idempotency.key` (3.11) | real; **Bearer writes only** |
| 2 | Password gate and account state | 403 `PASSWORD_CHANGE_REQUIRED` / `ACCOUNT_DISABLED` | `EnsureAccountUsable` (F08) | real |
| 3 | Role (ability) | 403 `FORBIDDEN` | `EnsureAbility`, alias `ability:<name>` (F09) | real |
| 4 | Terminal scope | 403 `FORBIDDEN` | `EnsureTerminalScope`, alias `terminal.scope` (F09) | real |
| 5 | Minimum app version | 426 `APP_UPDATE_REQUIRED` (422 for a malformed version, 503 when the port has no valid `min_app_version`) | `EnsureMinAppVersion`, alias `app.version` | **real since P1-C3** |
| 6 | Licence mode and port status | 423 `LICENSE_READ_ONLY` | `EnsureLicenseAllowsWrite`, alias `license.write` | **empty hook** (passes everything) |
| 6b | Replay: a stored answer for this token and key is returned (`Idempotent-Replayed: true`) and the endpoint does not run; the same key with another request is refused | the first answer again, or 422 `IDEMPOTENCY_KEY_REUSED` | `ReplayIdempotentWrite`, alias `idempotency.replay` (3.11) | real; **Bearer writes only** |
| (route binding) | `{user}` becomes a model, unknown id gives 404 | 404 `NOT_FOUND` | Laravel `SubstituteBindings` | real, placed here on purpose |
| 7 | Record version token | 409 `VERSION_CONFLICT` (422 when `version` is missing or malformed) | `EnsureRecordVersion`, alias `version.token:<param>` (F12) | real, opt-in per route |
| 8 | Business rules | 409 or 422 | inside the endpoint | per endpoint (not middleware) |
| 9 | Plan limits | 422 `PLAN_LIMIT_EXCEEDED` | inside the endpoint, last | not built (P2-D5) |

It gives you:

- one middleware **group** `port.api` that every signed-in route uses (web and mobile); it now also gives every Bearer write the idempotency rule;
- one **priority list** that fixes the order whatever order a route lists its middleware in;
- one real hook for the app version (step 5) and one empty hook for the licence (step 6), with their final names, aliases and positions;
- a **proof** (`WriteChainOrderTest`) that each refusal beats every later one.

It does **not** give you: the licence guard (P2-D5, no plan file, see F15), the plan-limit service, or any business rule. Steps 8 and 9 are code inside the endpoint (they need the validated record and the transaction), "rules first, plan limits last" (`docs/tasks/P2-D2.md` section 3).

Which requests each step applies to: steps 1, 2, 3, 4 on every request; step 1b on Bearer requests only; step 5 on **any request that carries `X-App-Version`** (lead's answer Q2 of P2-D2, 2026-11-03) and, for a Bearer request, the header is **required** (decision D-B of P1-C3, 2026-11-05: it replaces Q2 for Bearer requests; CT-01 v1.4); steps 6 and 7 on writes only (POST, PUT, PATCH, DELETE). Today step 6 does nothing, and step 7 skips safe methods (GET, HEAD, OPTIONS) by itself. Rows 0, 1c and 6b apply to **Bearer writes only** (POST, PUT, PATCH, DELETE with `Authorization: Bearer`, any route in `port.api` except the names in `config/idempotency.php`); a web session request, a read and `POST /sync/events` (route name `sync.events.store`) pass untouched.

## 2. Request or data flow

A `PATCH /api/v1/tugboats/5` (an imagined route, see section 9) from a signed-in user, first from the web, then from a tablet:

1. `public/index.php` boots `bootstrap/app.php`, routing under the prefix `api/v1` (`bootstrap/app.php:37`, `apiPrefix: 'api/v1'`). The `api` group runs first (Sanctum's stateful middleware is added by `statefulApi()`, line 40; session and cookie middleware).
2. The router matches the route and gathers its middleware: the `port.api` group is expanded into `IdempotentWrite`, `auth:sanctum`, `EnsureTokenDevice`, `RequireIdempotencyKey`, `EnsureAccountUsable`, `EnsureMinAppVersion`, `EnsureLicenseAllowsWrite`, `ReplayIdempotentWrite`; the route adds `ability:tug-fleet.manage` and `version.token:tugboat` (`bootstrap/app.php:51-60`).
3. Laravel **sorts** that list with the priority list (`bootstrap/app.php:61-84`). Sorting is by class and ignores parameters (`ability:x` is sorted as `EnsureAbility`). Anything not in the list keeps its place relative to the rest.
4. The pipeline runs, in this sorted order:
   - session and cookies; `IdempotentWrite` (a Bearer write with a well-formed key takes the lock for its key; it answers at once only for a retried logout, see 3.11); `auth:sanctum` (no web session and no valid token: 401; a Bearer token that is expired, unknown or revoked is also 401, `AccessServiceProvider` makes Sanctum refuse a revoked token);
   - `EnsureTokenDevice`: **web request** (`currentAccessToken()` is not a `PersonalAccessToken`): passes at once. **Tablet request**: `X-Device-Id` and `X-App-Version` are validated (missing or malformed: 422), the token's device must have the same `device_id` (otherwise 401, audited `device_mismatch`) and must not be revoked (otherwise the token is revoked and 401, audited `device_revoked`); then `markUsed()` stamps `last_used_at` and slides `expires_at` to now + 30 days;
   - `RequireIdempotencyKey` (Bearer write: `Idempotency-Key` missing or not a UUID v4: 422; otherwise the key is recorded on the request);
   - `EnsureAccountUsable` (inactive: 403, and the web session is ended or the mobile token is revoked; `must_change_password` outside the three open routes: 403);
   - `EnsureAbility` (role lacks the ability: 403, audited);
   - `EnsureTerminalScope` (route has `{terminal}` that is not the caller's: 403, audited);
   - `EnsureMinAppVersion` (header present and older than `port_settings.min_app_version`: 426 with `min_app_version` in the body; web requests normally send no header and pass);
   - `EnsureLicenseAllowsWrite` (pass-through);
   - `ReplayIdempotentWrite` (Bearer write: a stored answer for this token and key is returned now, with `Idempotent-Replayed: true`, and the later steps and the endpoint do not run; another request under the same key: 422 `IDEMPOTENCY_KEY_REUSED`; nothing stored: the request goes on and its answer is stored afterwards by `IdempotentWrite`).
5. **Only now** `SubstituteBindings` turns `{tugboat}` into a model. An unknown id gives 404 here, after all the checks above passed. This is the lead's decision Q4: an unauthorised caller never learns whether a record exists.
6. `EnsureRecordVersion` (alias `version.token:tugboat`) reads the bound model, validates `version` (422) and compares it (409) (see F12 section 2).
7. The controller runs: it opens a transaction, calls `VersionToken::lockAndAssert` (F12), applies the business rules (409/422), then the plan limits (422, last), saves, writes the audit row in the same transaction, commits, and returns the resource with the new `version`.

A refusal at any step throws `ApiProblemException` (or a Laravel exception), and `ProblemRenderer` turns it into the RFC 9457 body (F03). Later steps never run.

## 3. Every piece of code

### 3.1 `bootstrap/app.php` (the chain lives here, nowhere else)

| Part | Lines | What it does and why |
| --- | --- | --- |
| `$middleware->statefulApi()` | 40 | Sanctum SPA cookie support for the API routes (F07). Not part of the chain itself. |
| Doc comment | 42-50 | States the order in one place and (line 49) "`auth:sanctum` takes the web session first, then a mobile Bearer token, whose `X-Device-Id` is checked next (P1-C3)". If the list and the comment ever disagree, the list wins (it is the code). |
| `$middleware->group('port.api', [...])` | 51-60 | The group every signed-in route uses. Members, in this order: `IdempotentWrite::class`, `'auth:sanctum'`, `EnsureTokenDevice::class`, `RequireIdempotencyKey::class`, `EnsureAccountUsable::class`, `EnsureMinAppVersion::class`, `EnsureLicenseAllowsWrite::class`, `ReplayIdempotentWrite::class`. It holds only **parameterless** members; the ones with a parameter (`ability:<name>`, `version.token:<param>`) and `terminal.scope` stay on the route (lead's answer Q1: "priority list plus named group(s)"). One group serves web and mobile: the guard is `sanctum`, which tries the web session first and then the Bearer token. |
| `$middleware->priority([...])` | 61-84 | The list that fixes the order. It **replaces** Laravel's default priority list, so it also repeats the framework entries that must keep their relative order: `HandlePrecognitiveRequests`, `EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`, `ShareErrorsFromSession`, then `IdempotentWrite` (it must come before authentication), `AuthenticatesRequests` (the interface that `auth:sanctum` implements), `ThrottleRequests`, `ThrottleRequestsWithRedis`, `AuthenticatesSessions`; then the project's chain: `EnsureTokenDevice`, `RequireIdempotencyKey`, `EnsureMobileHeaders`, `EnsureAccountUsable`, `EnsureAbility`, `EnsureTerminalScope`, `EnsureMinAppVersion`, `EnsureLicenseAllowsWrite`, `ReplayIdempotentWrite`; then `SubstituteBindings`, `EnsureRecordVersion`, `Authorize` (the framework's `can:` middleware, last). `EnsureMobileHeaders` is in the list but is **not** in the group: it guards only `auth/mobile/login` (3.5). |
| `$middleware->redirectGuestsTo(...)` | 85-87 | For `api/*` returns `null`, so a guest gets a 401 problem body instead of a redirect to a login route. Other URLs go to `route('login')` (no route named `login` is defined in `routes/`; only a non-API URL could reach it). |
| `withExceptions` | 89-96 | JSON for `api/*` and for any request that expects JSON; all exceptions go through `ProblemRenderer` (F03). |

Why the group is defined in `bootstrap/app.php` and not in a service provider: the group names classes of **three** modules (Access, License and Shared). Access may not import License classes (module layer rule, A4), and `bootstrap/app.php` is outside the modules, so it may import all of them (progress log `docs/tasks/P2-D2.md`, 2026-11-03, "Deviation"). The P2-D2 review noted that P1-C3's guard change must go here and not into `routes/api.php`; it did (the group now says `auth:sanctum`).

### 3.2 The aliases (where each name is registered)

| Alias | Class | Registered in |
| --- | --- | --- |
| `ability` | `EnsureAbility` | `AccessServiceProvider::boot` (`app/Modules/Access/AccessServiceProvider.php:29`) |
| `terminal.scope` | `EnsureTerminalScope` | same, line 30 |
| `app.version` | `EnsureMinAppVersion` | same, line 31 |
| `license.write` | `EnsureLicenseAllowsWrite` | `LicenseServiceProvider::boot` (`app/Modules/License/LicenseServiceProvider.php:16`) |
| `version.token` | `EnsureRecordVersion` | `SharedServiceProvider::boot` (`app/Modules/Shared/SharedServiceProvider.php:21`) |
| `idempotency` | `IdempotentWrite` | same, line 22 |
| `idempotency.key` | `RequireIdempotencyKey` | same, line 23 |
| `idempotency.replay` | `ReplayIdempotentWrite` | same, line 24 |

`EnsureTokenDevice` and `EnsureMobileHeaders` have **no alias**: they are named by class (the group, and `routes/api.php:22`). The group lists classes, not aliases. The aliases exist so a route (or a test) can name a step on its own: `routes/api.php:22` uses `'app.version'` on `auth/mobile/login`, which is not in `port.api`.

`AccessServiceProvider::boot` (lines 33-38) also registers the Sanctum token model: `Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class)` and `Sanctum::authenticateAccessTokensUsing(fn ($token, $isValid) => $isValid && ! $token->isRevoked())`, so a revoked token answers 401 like an expired one.

### 3.3 `EnsureMinAppVersion` (`app/Modules/Access/Http/Middleware/EnsureMinAppVersion.php`)

- Constructor: `MinAppVersionSource $versions` (interface in Shared, bound to `MasterData\Services\PortSettingMinAppVersion` by `MasterDataServiceProvider::register`; see 3.6).
- `handle(Request $request, Closure $next): Response`:
  1. `$version = $request->header('X-App-Version')`; **null: `return $next($request)`** (web requests, and any request that sends no header; a Bearer request has already been refused with 422 by `EnsureTokenDevice`).
  2. `EnsureMobileHeaders::validateHeaders(['X-App-Version' => $version], ['X-App-Version' => AppVersion::rules()])`: malformed gives 422 `VALIDATION_FAILED` (`errors.X-App-Version`).
  3. `$minimum = $this->versions->minAppVersion()` (503 `SERVICE_UNAVAILABLE` if the setting is missing or not `x.y.z`).
  4. `AppVersion::isOlderThan($version, $minimum)` (`version_compare(...,'<')`): throws `ApiProblemException::appUpdateRequired($minimum)`, 426 `APP_UPDATE_REQUIRED` with the extension member `min_app_version`.
  5. Otherwise `$next`.
- Not audited: no call to `AuditService` (the P1-C3 plan's default D11 "426 audited: no", marked "assumed default, lead to confirm at review").
- Why it sits in Access: the mobile app is part of sign-in and device handling. Why it reads through an interface: Access may not use MasterData tables; the interface is in Shared (`MinAppVersionSource`) and MasterData binds it.
- Its position must not change ("do not move it", class comment). That is what `WriteChainOrderTest` guards.

### 3.4 `EnsureTokenDevice` (`app/Modules/Access/Http/Middleware/EnsureTokenDevice.php`)

- Constructor: `AuditService $audit`. Runs in the group right after authentication and before the password gate.
- `handle(Request $request, Closure $next): Response`:
  1. `$token = $request->user()?->currentAccessToken()`; if it is not a `PersonalAccessToken` (web session, or a guest): `return $next($request)`.
  2. `EnsureMobileHeaders::validateHeaders` for `X-Device-Id` (`EnsureMobileHeaders::deviceIdRules()`: required string max 100) and `X-App-Version` (`AppVersion::rules()`): missing or malformed gives 422 (D-B).
  3. `$token->device?->device_id !== $deviceId` gives `refuse($request, 'device_mismatch')`: audit and 401.
  4. `$token->device->revoked_at !== null` gives `$token->revoke()` then `refuse($request, 'device_revoked')`: the token is revoked too and the answer is 401 (review 3 item 1; the sign-in, by contrast, answers 403 `DEVICE_REVOKED`, see F07/F15).
  5. `$token->markUsed()`: stamps `last_used_at` and moves `expires_at` to now + 30 days in one write. Only a matching request counts as a use; `config/sanctum.php` has `'last_used_at' => false` so Sanctum's own stamp (which would run before the device check) is off.
- `refuse(Request, string $reason): never` (private): `AuditService::rejectedAttempt(route, ErrorCode::Unauthenticated, $reason, $user->id, $user->username, 'users', $user->id)`, then `throw new ApiProblemException(ErrorCode::Unauthenticated)`.

### 3.5 `EnsureMobileHeaders` (`app/Modules/Access/Http/Middleware/EnsureMobileHeaders.php`)

- Used **only** on `POST auth/mobile/login` (`routes/api.php:22`, together with `'app.version'`). It requires both headers before the 426 and before the body, so an old app learns it must update even when its body is outdated (lead's default D3, 2026-11-01).
- `handle(Request, Closure)`: `validateHeaders(['X-App-Version' => ..., 'X-Device-Id' => ...], [rules])`; 422 on a missing or malformed header.
- `static deviceIdRules(): array` returns `['required', 'string', 'max:100']` (the `user_devices.device_id` size).
- `static validateHeaders(array $headers, array $rules): void` builds a `Validator` whose attribute names are the header names (so the message reads "The X-Device-Id field is required."); throws `ValidationException` (422 `VALIDATION_FAILED`). Also called by `EnsureTokenDevice` and `EnsureMinAppVersion`.

### 3.6 Supporting types

| Type | File | What it does |
| --- | --- | --- |
| `AppVersion` | `app/Modules/Shared/Support/AppVersion.php` | `PATTERN = '/^\d+\.\d+\.\d+$/'` (`x.y.z` only, lead 2026-11-05); `rules()` returns `['required','string','max:20','regex:...']` (`user_devices.app_version` holds 20 characters); `isOlderThan(string $version, string $minimum): bool` uses `version_compare`, so `1.10.0` is newer than `1.9.0`. |
| `MinAppVersionSource` | `app/Modules/Shared/Support/MinAppVersionSource.php` | Interface with `minAppVersion(): string` (throws 503 when missing). Defined in Shared because the check lives in Access, which may not use MasterData. |
| `PortSettingMinAppVersion` | `app/Modules/MasterData/Services/PortSettingMinAppVersion.php` | Implements it: reads `port_settings.setting_value` where `setting_key = 'min_app_version'`; a missing row or a value that is not `x.y.z` throws `ApiProblemException(ErrorCode::ServiceUnavailable)` (503). Constant `SETTING_KEY`. Bound in `MasterDataServiceProvider::register()` (`bind(MinAppVersionSource::class, PortSettingMinAppVersion::class)`). The same reader serves `GET system/info`. |

### 3.7 `EnsureLicenseAllowsWrite` (`app/Modules/License/Http/Middleware/EnsureLicenseAllowsWrite.php`)

- `handle` returns `$next($request)`: **still a pass-through**.
- Intended content (class comment): "writes only; reads always pass; the route tags give the READ_ONLY and SETUP allow-lists"; a write the licence mode refuses answers 423 `LICENSE_READ_ONLY`. Owner: task **P2-D5** (a row in `docs/reference/backend/atlantech_backend_tasks.md:73`, no plan file, not on the roadmap). DEC-54 (Closed) says licence work is future work.
- What data it will read: the `license_states` table, model `LicenseState` (module License, the only license model; enums `LicenseMode`, `LicenseStatus`, `PortStatus`, `ReadOnlyReason`). `SystemInfoController` returns `LicenseMode::Full->value` as a fixed value; no code reads the `LicenseState` model.

### 3.8 What fills step 7 (`EnsureRecordVersion`)

It is real code, covered in F12. This file only fixes its place: after `SubstituteBindings`, before the controller.

### 3.9 Route binding and `terminal.scope` (why order matters here)

`SubstituteBindings` is listed after the scope checks (`bootstrap/app.php:71`). `EnsureTerminalScope` therefore runs **before** `{terminal}` is a model and reads the raw route value: `$request->route('terminal')` may be a string id or, if binding already happened, an object with `getKey()`; the middleware handles both (`EnsureTerminalScope.php:39-45`), and a non-numeric value from a terminal-scoped user is refused 403 (lines 47-53). The plan said the same: "`terminal.scope` must read the raw route id because it now runs before `SubstituteBindings`" (`docs/tasks/P2-D2.md` section 3).

### 3.10 Related existing test helpers

`tests/Pest.php` provides `useSchemaDatabase()` (points the default connection at `DB_SCHEMA_TEST_DATABASE`, default `atlantech_testing`, and runs `migrate:fresh` once per run) and `makeUserWithRole(?Role $role, array $attributes = [], ?OrganizationUnit $unit = null): User`. `tests/TestCase.php` now overrides `be(Authenticatable $user, $guard = null)` to call `$this->app['auth']->forgetGuards()` first: the `sanctum` guard keeps the first user it resolved for the life of the application, so a test that signs in a second user would still be answered as the first. Tests that send two requests as different users must also call `app('auth')->forgetGuards()` (the mobile tests do, see `withTabletToken`).

### 3.11 The idempotency steps (IDEMP-01, CT-01 section 4)

**What problem it solves.** A mobile client that loses the answer to a write sends it again. Without a rule the retry of a **logout** is 401 (the first request revoked the token) and the retry of a **change-password** is 422 (the current password already changed), although both writes worked. With the rule the retry gets the first answer again and the write runs once. The Mobile Team found the gap in its reply of 2026-11-06 (P1-C3 progress log); the lead decided the rule applies to every Bearer write, through one feature. The plan, with both security reviews, is `docs/tasks/IDEMP-01.md`; the contract text is CT-01 v1.5 section 4.

**The rule, as built.** A POST, PUT, PATCH or DELETE made with `Authorization: Bearer` carries `Idempotency-Key`: a UUID v4, one per logical write, reused on every retry.

- Same key, same request (method, path with its query, body): the first answer is returned with its status, its body and its content type, plus `Idempotent-Replayed: true`; the write does not run again.
- Same key, another request (another body, path, query or method): 422 `IDEMPOTENCY_KEY_REUSED`.
- Key missing or not a UUID v4: 422 `VALIDATION_FAILED` on the field `Idempotency-Key`, after authentication (a bad token is 401 first).
- A key belongs to the **token**: the same key sent with another token, device or user is a different request.
- An entry lives 24 hours. Two requests with the same key at the same time run the write once: the second waits up to 5 seconds for the lock and then replays; if the first is still running it answers 503 `SERVICE_UNAVAILABLE` with `Retry-After: 1` (the lead's answer, 2026-11-06).
- **Stored:** every answer the write produced that is final: 2xx, and 4xx such as 422, 409 or 404 that the endpoint or the binding / version steps produce. **Not stored:** 5xx, 401, 408, 423, 426 and 429 (they pass with time, so the retry runs the write again; the lead's answer), and every refusal from a step **before** the replay step (key 422, password gate, account state, ability, terminal scope, app version, licence: those are returned fresh every time).
- **Not applied to:** web session requests (they never send the header and are never refused for its absence, even with a stray `Authorization` header), reads, `POST /api/v1/auth/mobile/login` and `POST /api/v1/broadcasting/auth` (outside `port.api`), and the routes named in `config('idempotency.exempt_routes')`, today `sync.events.store` (the name `POST /sync/events` must take, CT-07 uses `client_event_id`).

**The three middleware** (`app/Modules/Shared/Http/Middleware/`, all in the shared kernel; the aliases are in `SharedServiceProvider`):

| Class (alias) | Position | What it does |
| --- | --- | --- |
| `IdempotentWrite` (`idempotency`) | first in the group; before `AuthenticatesRequests` in the priority list | Applies when the request has a Bearer token, a write method, a route not in the exempt list and a **well-formed** key (otherwise it passes and step 2 refuses). Computes `scope = sha256(Bearer token)` and the request hash. If a stored entry is flagged `preauth` it replays it now (or 422 if the request differs). Then it takes the cache lock for the key (503 + `Retry-After` on timeout), looks again (the winner may have stored while this request waited), runs the rest of the chain, and afterwards stores the answer when `ReplayIdempotentWrite` marked the request storable and the status may be stored. The lock is released in `finally`, after the answer is stored. |
| `RequireIdempotencyKey` (`idempotency.key`) | right after `EnsureTokenDevice` | Applies when the caller is authenticated by a Sanctum personal access token (`currentAccessToken()` is a Sanctum `PersonalAccessToken`; a web session or a `TransientToken` is not), a write, a non-exempt route. Validates the header (field name `Idempotency-Key`, rules `required`, `string`, `uuid:4`; 422 otherwise) and records the lower-case key on the request. |
| `ReplayIdempotentWrite` (`idempotency.replay`) | after `EnsureLicenseAllowsWrite`, before `SubstituteBindings` and `EnsureRecordVersion` | Looks for a stored entry of this token and key: found with the same hash: returns it (`IdempotencyStore::replayFor`); found with another hash: `ApiProblemException(IdempotencyKeyReused)`; not found: sets the request attribute that lets `IdempotentWrite` store the answer. It sits **before** the version token on purpose: the first write moved the record's `version`, so the retry carries a stale one and would get 409 instead of the replay. |

`IdempotencyStore` (`app/Modules/Shared/Support/IdempotencyStore.php`) holds the keys and the storage; `config/idempotency.php` holds the numbers:

| Part | What it is |
| --- | --- |
| Cache store | `config('idempotency.store')` (env `IDEMPOTENCY_CACHE_STORE`, default: the application's cache store; Redis in production, `array` in tests). **No table, no migration.** |
| Entry key | `idempotency:{sha256(Bearer token)}:{key}`; the token itself is never stored. Lock key `idempotency-lock:{same scope}`. |
| Entry value | JSON with `request_hash`, `status`, `content_type`, `body`, `preauth`, **encrypted** with the application key (`Crypt`), because a body can hold a one-time secret (for example the one-time password that P2-E1 returns). A value that cannot be decrypted counts as absent. |
| Request hash | SHA-256 of the method, the request URI (path and query) and the body: the raw content, or for a multipart request (PHP leaves the raw content empty) the key-sorted fields plus the name, size and SHA-256 of each uploaded file. |
| `ttl_hours` | 24 |
| `lock_seconds` / `lock_wait_seconds` | 120 / 5. The lock must not be shorter than the longest a request may run (for example PHP-FPM `request_terminate_timeout`); if it expired first, a retry could run the write twice. |
| `exempt_routes` | route names or patterns (`Str::is`), default `['sync.events.store']` |
| `unstored_statuses` | `[401, 408, 423, 426, 429]` besides every 5xx |

**Why a retried logout still works, and why only logout is answered before authentication.** The logout retry carries a token the first request revoked, so `auth:sanctum` would refuse it. So `IdempotentWrite` marks an entry `preauth` only when the endpoint revoked the very token the request came with **and** answered with an empty body (204). Only such an entry is replayed before authentication; finding it proves the caller sent both the exact token (the key is its hash) and the secret UUID. Every other replay waits for `ReplayIdempotentWrite`, after authentication, the device check, the password gate, the account state, the ability, the scope, the app version and the licence, so a token or a device revoked later, a user deactivated later or a role lost later never receives a stored body. Both rules came from the two automated security reviews of 2026-11-06 (plan, section 7, C4 and C11) and are covered by tests.

**Two flows, step by step.**

1. *Logout, answer lost.* First `POST /auth/logout` with key K: lock, authentication, device check, key valid, nothing stored, endpoint revokes the token and answers 204; `IdempotentWrite` stores `204` with `preauth = true` and releases the lock. The tablet sends it again with K: `IdempotentWrite` finds the entry before `auth:sanctum`, same hash, returns 204 with `Idempotent-Replayed: true`. A **new** key on the same token is 401, as before.
2. *Change-password, answer lost.* First request: runs, 200 with the profile, stored (`preauth = false`). The retry: authentication still passes (this token stays valid after a password change), the key is valid, `ReplayIdempotentWrite` finds the entry and returns the same 200; the password is not changed again and there is no 422.

**Stays outside the rule (known limits, plan section 7, C13):** a 5xx raised after the endpoint's transaction committed is not stored, so a retry runs the write again; a worker killed mid-request keeps the lock until it expires (twins get 503 meanwhile); a stored body has no size cap; changing `APP_KEY` without `previous_keys` makes every entry unreadable (retried writes then run again); only `Content-Type` is replayed (not `Location` or `Cache-Control`); a replay does not repeat checks that run inside the endpoint (a terminal id in the body, in-endpoint policies); `throttle:5,1` on change-password sorts before the replay, so a replay counts as an attempt.

## 4. Why this design

| Decision | Reason | Source |
| --- | --- | --- |
| The order is pinned by the **priority list**, not by the order a route lists its middleware | A developer who writes `->middleware(['version.token:x', 'ability:y'])` must still get the contract order. A rule that depends on every author remembering the order will be broken. | Lead's answer Q1 (2026-11-03); `docs/tasks/P2-D2.md` section 8 |
| A **group** for the parameterless members | `ability:<name>` takes a parameter and cannot sit in a fixed group; the group removes boilerplate from every route and makes it impossible to forget the device, 426 and 423 steps. | Q1; plan section 7 |
| **Hooks first** for 426, 423 and the version token | Other people owned the content (P1-C3, P2-D5, P2-D4). Creating the names and the order first let them fill the bodies without a merge fight over the order. 426 and 409 are filled; 423 is not. | Q3 |
| One group and `auth:sanctum` for web and mobile, not a sibling `port.api.mobile` group | The P2-D2 plan suggested a second group; P1-C3 found the single guard `sanctum` (session first, then token) enough, and a second group would need a copy of the whole member list. | `docs/tasks/P1-C3.md`; `bootstrap/app.php:47` |
| `EnsureTokenDevice` **before** the password gate | A token of another device must never reach any later check; a refused device is a 401, not a 403. | P1-C3 default D6 (2026-11-01), review 3 |
| `X-App-Version` **required** on Bearer requests, optional on web | CT-01 said "every request"; P2-D2 Q2 said "only when present". The lead chose: Bearer requests must send it, web requests may omit it. | D-B (2026-11-05); CT-01 v1.4 |
| Device refused at **sign-in** with 403 `DEVICE_REVOKED`, but at a later request with 401 | A revoked device cannot sign in (nobody is signed in yet, the answer says why); a token of a revoked device is simply invalid. | DEC-60 (Closed), CT-04 v1.2 |
| Sanctum's `last_used_at` off; `EnsureTokenDevice` stamps and slides | A refused request (wrong device) must not extend a token. | P1-C3 review 2 |
| `SubstituteBindings` **after** `ability` and `terminal.scope` | Laravel's default binds models before authorisation, so a user without rights would get 404 for a missing record and 403 for an existing one, which leaks that the record exists. | Q4 (lead: "after both, so no 404 leaks a record's existence") |
| 401 and the password gate apply to **every** request, version and licence to writes only | Reads must stay possible in READ_ONLY mode and need no version. | Q2 |
| Steps 8 and 9 are **not** middleware | They need the validated record and the transaction (rules, then limits). | plan section 3 |
| The route coverage test checks "ability route runs inside `port.api`" instead of the raw order | `Route::gatherMiddleware()` returns the group **name** unexpanded, so a test that searches it for `EnsureAccountUsable` never finds it. The order itself is proven by behaviour in `WriteChainOrderTest`. | Review R1, lead's choice 2026-11-04 |
| Idempotency in **three** steps, not one | Three places in the chain need it: before authentication (only a retried logout), right after the device check (the key is checked once the caller is known to be a mobile one, so a bad token is 401 first) and after the licence step (the replay is only given to a caller who passes every check that depends on who they are today). Putting the replay earlier was found unsafe by the two security reviews of IDEMP-01. | `docs/tasks/IDEMP-01.md` sections 3 and 7 |
| The replay sits **before** the version token | The first write changed the record's `version`; the retry still sends the old one and would be refused with 409. | IDEMP-01 |
| Storage in the cache store, encrypted, no table | The lead's choice (no migration, so no `ai-impact` and no CT-17 bump); entries live 24 hours; a body may contain a one-time secret. | IDEMP-01 section 8, Q4 |
| 2xx and final 4xx stored; 5xx, 401, 408, 423, 426, 429 not | Transient refusals must not be replayed for 24 hours; the lead refined his own "non-5xx" rule. | IDEMP-01 Q1 (2026-11-06) |
| 503 + `Retry-After` while the first request still runs | No new error code and no CT-01 bump; the client sends the same request again. | IDEMP-01 Q2 (2026-11-06) |
| Rejected | A single big middleware that runs all the steps; a provider-defined group (breaks the layer rule); relying on route authors; a separate mobile group. | plan sections 3 and 7 |

## 5. How it connects

- **Uses:** `IdempotencyStore` and `config/idempotency.php` (3.11), `EnsureAccountUsable` (F08), `EnsureAbility` and `EnsureTerminalScope` (F09), `EnsureRecordVersion` and `VersionToken` (F12), `ApiProblemException` and `ProblemRenderer` (F03), `AuditService` (F10) through the refusals, `PersonalAccessToken` and `UserDevice` (Access models), `MinAppVersionSource` (Shared, bound by MasterData).
- **Used by:** every signed-in route. Today: `auth/me`, `auth/change-password`, `auth/logout`, `GET audit-logs` (`routes/api.php:26-32`). Of these, `auth/change-password` and `auth/logout` are the Bearer writes that need `Idempotency-Key`. **Outside the group:** `auth/login` (web), `auth/mobile/login` (nobody is signed in yet; it carries `EnsureMobileHeaders` and `app.version` itself) and `GET system/info` (public, never 426).
- **Waiting to use it:** P2-E1..E3 (users, roles, devices), P2-F1..F5 (master data CRUD), every later write endpoint (F15).
- **Module layer rule:** the group is in `bootstrap/app.php` (outside the modules) for the reason in 3.1. The hook classes are in the module that owns their data; the 426 reader crosses Access and MasterData through an interface in Shared.
- **Tests that guard routes:** `RouteAbilityCoverageTest` (F09) requires `ability:` on every API route except six named ones (`auth.login`, `auth.mobile.login`, `auth.me`, `auth.change-password`, `auth.logout`, `system.info`), requires `port.api` on every route that has `ability:`, and `terminal.scope` on every route with a `{terminal}` parameter. `RouteVersionCoverageTest` (F12) requires `version.token:` on every PUT and PATCH route.

## 6. Data

No tables of its own. Data touched by the steps:

| Step | Reads / writes | Owner |
| --- | --- | --- |
| Authentication | `sessions` (web session), `personal_access_tokens` (Bearer; slide writes `last_used_at`, `expires_at`; revocation writes `revoked_at`), `users` | Access |
| Device step | `user_devices` (`device_id`, `revoked_at`) through the token's `device` relation; writes `audit_logs` (`REJECTED_ATTEMPT`) on a refusal | Access, Shared |
| Password gate | `users.is_active`, `users.must_change_password`; writes `audit_logs` (`REJECTED_ATTEMPT`) | Access, Shared |
| Ability, terminal scope | `user_role_assignments`, `organization_units`; writes `audit_logs` on refusal | Access |
| 426 | `port_settings.min_app_version` (seeded `1.0.0`, F13) | MasterData |
| 423 (future) | `license_states` | License |
| Version token | the bound record's `updated_at DATETIME(6)`; writes `audit_logs` on refusal | the record's module |
| Idempotency steps | the cache store only (entries and locks, 24 h): no table, no audit row; the refusals of these steps (422, 503) are not audited because there is no known user before authentication | Shared |

## 7. Tests

`tests/Feature/Api/WriteChainOrderTest.php` (5 tests). 32 tests pass together with F12's files and `RouteAbilityCoverageTest` (real run, 2026-11-06 on `58affe4`, section 10). The idempotency steps have their own files (below); `WriteChainOrderTest` is a web test and does not cover them.

How the test works: `EnsureMinAppVersion` is now real, `EnsureLicenseAllowsWrite` is empty and `EnsureRecordVersion` is opt-in, so the test cannot prove their order with the real classes. `fakeHookSteps()` (lines 26-45) binds, in the container, a fake class in place of the three. A fake throws its own `ApiProblemException` (426, 423, 409) when the request header `X-Fail` lists its status, otherwise it passes. `beforeEach` (47-73) opens a transaction (rolled back in `afterEach`), sets Sanctum's stateful domain and the `Origin`/`Referer` headers, and registers five throw-away routes under `/api/v1/test/...`:

| Test route | Middleware, in the order written | Purpose |
| --- | --- | --- |
| `POST test/chain` | `api, port.api, ability:users.manage, version.token` | the normal order |
| `POST test/chain-hooks` | `api, port.api, ability:users.manage` | the real hooks |
| `POST test/chain-reversed` | `api, version.token, ability:users.manage, port.api` | listed in the wrong order |
| `POST test/terminals/{terminal}` | `api, version.token, terminal.scope, app.version, ability:port-state.read, port.api` | also reversed; has a `{terminal}` |
| `GET test/users/{user}` | `api, port.api, ability:users.manage`, closure with `User $user` | route binding order |

All requests in this file are made as a **web** user (`actingAs($user, 'web')`), so `EnsureTokenDevice` passes them.

| Test (name in the file) | What it proves |
| --- | --- |
| `passes a request with a supported app version through the app version and licence hooks` (line 80) | With the real classes, a Port GM request with `X-App-Version: 1.0.0` to `test/chain-hooks` gets 200 when a `min_app_version` setting of `1.0.0` exists (the test creates it with `PortSetting::factory()`). The previous version of this test sent `0.0.1` and expected 200 while the hook was empty; it changed on purpose when P1-C3 filled the hook. The real 426 cases are in `MobileTokenTest` and `MobileSignInTest` (F07/F15). |
| `answers the first failing step of the chain` (line 90; runs for `test/chain` and `test/chain-reversed`) | With the fakes set to fail at 426, 423 and 409 at once: a guest gets 401 `UNAUTHENTICATED`; a user with `must_change_password` gets 403 `PASSWORD_CHANGE_REQUIRED`; a `PORT_STAFF` user (no `users.manage`) gets 403 `FORBIDDEN`; a `PORT_GM` gets 426 `APP_UPDATE_REQUIRED`; with `X-Fail: 423,409` gets 423 `LICENSE_READ_ONLY`; with `X-Fail: 409` gets 409 `VERSION_CONFLICT`; with no failure gets 200. Each case fails **all** later steps too, so it shows the earlier code wins. Running on the reversed route proves the priority list orders the steps, not the route author. |
| `answers 403 before 404 for an unauthorised caller and 404 for an allowed one` (line 118) | `GET test/users/999999`: `PORT_STAFF` gets 403 `FORBIDDEN`; `PORT_GM` gets 404. Binding runs after `ability`. |
| `refuses another terminal with 403 before 426, 423 and 409, and before any 404` (line 128) | A `TERMINAL_OFFICER` asking for another terminal's id gets 403; for a **non-existent** terminal id (999999) also 403; for its own terminal gets 426 (the first fake that fails); with no failure, 200. The route's closure takes no model, so no 404 could come from it; the proof of "before binding" for models is the previous test. |

Run: `vendor/bin/pest tests/Feature/Api/WriteChainOrderTest.php`.

Tests of the Bearer part of the chain (in `tests/Feature/Access/`, 86 tests with `PasswordChangeTest` and `SystemInfoTest`, all passed on 2026-11-06 on `58affe4`):

| File | What it proves for the chain |
| --- | --- |
| `MobileTokenTest.php` (12 tests) | A Bearer token reads the profile; 422 without `X-Device-Id` and 401 for another device (audited, token not extended); expiry moves to 30 days after each use; a clean 401 for an expired, a revoked and an unknown token; 426 to a signed-in tablet below the minimum; the password gate applies to the token and the password change works with it; 422 for a wrong current password on the token route; 403 `ACCOUNT_DISABLED` revokes the token; 422 to a Bearer request without a well-formed `X-App-Version` while a web request without it passes; a token whose device was revoked: 401 `device_revoked`, token revoked; other tokens revoked after a password change from a tablet; a retried change-password with the same `Idempotency-Key` returns the same 200 and changes the password once, and the same key with another body is `IDEMPOTENCY_KEY_REUSED`. |
| `MobileSignInTest.php` (16 tests) | The sign-in step (F07/F15). Chain-relevant: 422 for a missing or malformed device header; 426 with `min_app_version` before the body and the password; 503 while `min_app_version` is missing or not `x.y.z`; versions compared as numbers. |
| `MobileLogoutTest.php` (7: the three logout cases plus a retried logout replayed as 204, another user's token not answered from the first entry, a logout without a key is 422 and keeps the token, a reused key with another body is refused before authentication), `SystemInfoTest.php` (4 tests with data sets; a guest gets the port data and is never refused with 426) | logout revokes the token; `system/info` is outside the chain. |
| `RouteAbilityCoverageTest`, `WebSignInTest`, `ProfileTest`, `PasswordChangeTest`, `AbilityMiddlewareTest`, `TerminalScopeTest` | the routes moved into `port.api` and behave as before. |

Tests of the idempotency steps (IDEMP-01; the Bearer requests sign in through the real endpoint, the probe routes count how many times their body really ran):

| File | What it proves |
| --- | --- |
| `tests/Feature/Api/IdempotencyTest.php` (21 tests) | Key missing or malformed: 422 on the `Idempotency-Key` field, 401 first for a bad token, 422 before the password gate (403) and the ability check (403), also on a route that lists its steps in reverse; first request runs and the same key and request replay status, body and content type for POST, PUT, PATCH and DELETE, with the key in either case; another body, path, query or method under the key: `IDEMPOTENCY_KEY_REUSED`; another token, device or user never replays; a stolen token with a stored key and another device is 401; stored: 201, 403, 404, 409, 422, not stored: 401, 408, 423, 426, 429, 500, 503; a stored 4xx replays as `application/problem+json`; an exception stores nothing and releases the lock; web session requests untouched (also with a stray `Authorization` header); reads untouched; the exempt route `sync.events.store` runs without a key and is never replayed, and the exemption follows the config list; a replay is refused after the device is revoked, and after the password change is required, the role is lost or the user is deactivated; the replay still comes after the ability check on the reversed route. |
| `tests/Feature/Api/IdempotencyConcurrencyTest.php` (3 tests) | A lock held by a first request: 503 with `Retry-After` and the write never runs, then it runs once the lock is free; the answer stored while a request waited for the lock is replayed; two sequential requests run the write once. |
| `tests/Feature/Api/IdempotencyStoreTest.php` (10 tests) | The entry is keyed by the token hash and encrypted (neither the token nor the body appears in the cache value); not found under another token or key; expires after 24 hours; an undecryptable value counts as absent; the request hash covers method, path, query and body, and a multipart request by its fields; which statuses are storable; the exempt names; UUID v4 rules; the lock. |
| `tests/Feature/Api/OpenApiDriftTest.php` (7 tests) | Every write that accepts a Bearer token in the OpenAPI file lists the `IdempotencyKey` parameter and a 422 (F04). |

Not covered by any test: the group's membership as a list (removing `EnsureLicenseAllowsWrite` from the group would not fail anything while it is empty); two simultaneous requests; the full middleware order of the idempotency steps as one list (it is proven by behaviour, and was checked once by hand with the router); a route that adds `app.version` and also sits in `port.api` (both name the same class; the test `test/terminals/{terminal}` does this and passes, but the test does not count how many times the fake runs); that the Bearer device step runs before the password gate (the Bearer tests cover each refusal on its own, not the order between them).

## 8. Gotchas and invariants

- **Never build a route from `auth:*` + `EnsureAccountUsable` by hand.** You would skip the device, 426 and 423 steps. Use `port.api`. `RouteAbilityCoverageTest` catches a route that has `ability:` but not `port.api`; a route with no ability must be on its six-name allow-list, which also catches it.
- **Do not reorder the priority list**, and do not delete an entry. The list **replaces** the framework default; a class that is missing from it is sorted by Laravel's fallback rules, which is how an order breaks silently. When Laravel upgrades its default list, compare it with this one.
- **A new middleware of the chain must be added to the priority list**; otherwise it runs in the route-listed order.
- **`terminal.scope` must stay before `SubstituteBindings`**, and must keep reading the raw id.
- **`throttle:5,1` runs before the password gate** (throttle is earlier in the list). A disabled user's repeated `auth/change-password` attempts count against the throttle before the 403 (review note, `docs/tasks/P2-D2.md` section 10). Harmless.
- **The 426 and the 422 for the app version are not audited.** FR-GL-02 says every refused attempt is audited; the P1-C3 default D11 ("426 audited: no") is marked "assumed default, lead to confirm at review". The device 401s and the 403s are audited. The hook for 423 will need the same decision.
- **A Bearer request needs both headers on every call** (`X-Device-Id`, `X-App-Version`), including `auth/me`, `auth/logout` and `auth/change-password`. A client that omits one gets 422, not 401.
- **`sanctum` keeps the first user it resolved** for the life of the application object: in tests call `app('auth')->forgetGuards()` between requests as different users (the `be()` override does it for `actingAs`).
- **A web session and a Bearer header together:** `auth:sanctum` tries the web session first; then `currentAccessToken()` is not a token and the device step and the idempotency key are skipped. Covered for the idempotency steps by a test with a stray `Authorization` header; the device step alone is unverified.
- **Every Bearer write needs an `Idempotency-Key`**, including the ones in tests: a Bearer `POST`, `PUT`, `PATCH` or `DELETE` without a UUID v4 is 422. The test helpers `withTabletToken` (`MobileTokenTest`) and `logoutTestRequest` (`MobileLogoutTest`) send a fresh key for each call; send a fixed key only when the test is about the retry.
- **A new middleware of the chain must be added to the priority list**, and the three idempotency steps must keep their relative places (before authentication, after the device step, after the licence step and before binding). A route that lists them in any order is sorted by the list; do not add them to a route by hand, they come with `port.api`.
- **`POST /sync/events` must be named `sync.events.store`** (or added to `config('idempotency.exempt_routes')`), otherwise it would demand `Idempotency-Key` and store its answers (CT-07 uses `client_event_id`).
- **The idempotency lock must outlive the request:** `lock_seconds` (120) is not shorter than the web server's hard limit; if a request can run longer, raise it first.
- **The test suite needs more than PHP's default 128M.** `origin/develop` already peaked at 128 MB; IDEMP-01 added tests, so `phpunit.xml` sets `<ini name="memory_limit" value="512M"/>` (lead's decision, PR #62). Without it `php artisan test` stops with "Allowed memory size exhausted".
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

1. The route is inside `Route::middleware('port.api')`. Never add `auth` or `EnsureAccountUsable` yourself. The same route serves the web and a tablet; do not write a second route for mobile.
2. `ability:<name>` with an ability that exists in `Ability` (F09). A typo is caught by `RouteAbilityCoverageTest` ("names only abilities that exist").
3. If the URL has `{terminal}`, add `terminal.scope`. If the terminal id comes in the body or query, call `TerminalScope::authorize` in the endpoint (F09).
4. On an update (PUT/PATCH), add `version.token:<param>`; the parameter name must equal the route parameter and the route must bind a model (the middleware throws `LogicException` otherwise, F12). On another write that changes an existing record (for example `POST users/{user}/deactivate`), add it too; the coverage test does **not** require it for POST (see F12 gotchas).
5. Do not put the middleware in any special order. The priority list orders them.
6. Inside the controller or service: open the transaction, `VersionToken::lockAndAssert`, then business rules (409/422), then plan limits (422), then save and audit in the same transaction (F10, F12).
7. Update `openapi/port-api.yaml` in the same PR (F04); document the 401, 403, 409 and 422 responses with the shared `$ref`s, and the `X-App-Version`/`X-Device-Id` headers for the Bearer security scheme (the file is at 1.8.0 and already describes them for the signed-in paths). For a write that accepts a Bearer token, also list the `IdempotencyKey` parameter and a 422 that covers `VALIDATION_FAILED` and `IDEMPOTENCY_KEY_REUSED`; `OpenApiDriftTest` fails without them (F04).
8. Test the route: one test for each refusal code it can return and the success path (see `VersionTokenTest` for the shape). For a route that a tablet will call, also send an `Idempotency-Key` in the Bearer tests and add the retry case (same key, same body: the endpoint ran once).

**Fill the licence hook** (P2-D5): write the body in `EnsureLicenseAllowsWrite`, keep the class name, namespace and the priority list untouched; throw `new ApiProblemException(ErrorCode::LicenseReadOnly)`; decide whether the refusal is audited and use `AuditService::rejectedAttempt` (F10); add a case to `WriteChainOrderTest` with the real class (and make its fake unnecessary only if the real class is testable with a licence row).

**Change the idempotency rule:** the three classes in `app/Modules/Shared/Http/Middleware`, `IdempotencyStore` and `config/idempotency.php` (3.11). To exempt a route add its name to `exempt_routes` and add a test; to keep an answer out of the store add its status to `unstored_statuses`. A change that stores or replays more is a contract change (CT-01 section 4, `contract-change` skill, F14).

**Change how a mobile request is recognised or checked:** all of it is in `EnsureTokenDevice` (device) and `EnsureMinAppVersion` (version); the sign-in side is `MobileSignInService` and `MobileAuthController` (F07, F15). Keep `AppVersion::PATTERN` and `CT-01` section 3 in step.

**Add a new check to the chain**: add the class to the group (or to the route), add it to the priority list at the right place, add a fake and a case to `WriteChainOrderTest`, and change CT-01 section 7 (an approved contract: use the `contract-change` skill, F14).

## 10. Try it

From `project-backend/ATLANTECH` (PowerShell; `php` comes from Herd). The runs below were done on 2026-11-06 on `develop` `58affe4` (after PR #62) with an own empty database (`atlantech_testing_g5`; the lead's `atlantech` database was not touched).

```bash
php artisan route:list --except-vendor
```
Real output: eight routes: `GET|HEAD /`, `GET|HEAD api/v1/audit-logs`, `POST api/v1/auth/change-password`, `POST api/v1/auth/login`, `POST api/v1/auth/logout`, `GET|HEAD api/v1/auth/me`, `POST api/v1/auth/mobile/login`, `GET|HEAD api/v1/system/info`.

```bash
php artisan route:list --path=api/v1/audit-logs -v
```
Expected, not run (the `-v` flag lists middleware): the group name `port.api` and `ability:audit.read` are shown; the order shown is the order written, not the executed order. The executed order is only visible through behaviour (the test).

```powershell
$env:DB_DATABASE="atlantech_testing_g5"; $env:DB_SCHEMA_TEST_DATABASE="atlantech_testing_g5"
vendor/bin/pest tests/Feature/Api/WriteChainOrderTest.php tests/Feature/Api/VersionTokenHelperTest.php tests/Feature/Api/VersionTokenTest.php tests/Feature/Api/RouteVersionCoverageTest.php tests/Feature/Api/RouteAbilityCoverageTest.php
```
Real result: `tests: 32, passed: 32, assertions: 119` in about 30 s. The first call builds the schema with `migrate:fresh` on the test database. Needs Docker MySQL running (`docker compose up -d`).

```powershell
vendor/bin/pest tests/Feature/Access/MobileSignInTest.php tests/Feature/Access/MobileTokenTest.php tests/Feature/Access/MobileLogoutTest.php tests/Feature/MasterData/SystemInfoTest.php tests/Feature/Access/PasswordChangeTest.php
```
Real result: `tests: 86, passed: 86, assertions: 430` in about 36 s.

```powershell
vendor/bin/pest tests/Feature/Api/IdempotencyTest.php tests/Feature/Api/IdempotencyConcurrencyTest.php tests/Feature/Api/IdempotencyStoreTest.php tests/Feature/Api/OpenApiDriftTest.php
```
Real result: `tests: 41, passed: 41, assertions: 410` in about 35 s.

To see the resulting order of the steps for a route (read-only):

```powershell
php artisan route:list --path=api/v1/auth/logout -v
```
Expected, not run: the middleware column lists the group name `port.api` and the route's own; the executed order is the one in section 1 (the idempotency plan, `docs/tasks/IDEMP-01.md`, records the order that was resolved with the router: `IdempotentWrite`, `auth:sanctum`, throttle, `EnsureTokenDevice`, `RequireIdempotencyKey`, `EnsureAccountUsable`, ability, terminal scope, app version, licence, `ReplayIdempotentWrite`, binding, version token).

To see the order break (do not commit): in `bootstrap/app.php` move `SubstituteBindings::class` above `EnsureAbility::class` and run the first file. Expected, not run: `answers 403 before 404 ...` fails (the staff caller gets 404). Restore the file afterwards.

## 11. Open points

- **The licence hook is still empty:** 423 (P2-D5, no plan file, not on the roadmap). See F15.
- **The refusals of the idempotency steps (422, 503) are not audited** (no known user before authentication, no state change); decide with FR-GL-02 together with the 426.
- **Idempotency known limits** (3.11, last paragraph): a 5xx after the endpoint's commit is not stored; no size cap on stored bodies; an `APP_KEY` change without `previous_keys` makes entries unreadable.
- **The Mobile Team must send the key** on every Bearer write from now on; a client that does not gets 422 on logout and change-password.
- **426 and the app-version 422 are not audited** (default D11, to confirm). Decide with FR-GL-02.
- **Group membership is not tested as a list.** Add a test when the licence hook is filled.
- **Plan statuses are stale:** `docs/tasks/P1-C3.md`, `P1-C4.md` and `P1-C5.md` still say `In review` although PRs #54 to #57 are merged (`grep "^| Status"`). The P2-D2 plan section 3 also still describes the earlier hook design. The code is right.
- **`RouteAbilityCoverageTest` file header comment** still says "ability middleware or allow-list" for the first test; the P2-D2 changes are in the fourth test. Cosmetic.
- **Default Laravel priority list drift:** the replaced list must be re-checked on every Laravel major upgrade (Unverified: no test compares it with the framework's list).
- **Web session plus Bearer header together** is not covered by a test (see gotchas).
