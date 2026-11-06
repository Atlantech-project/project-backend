# F07 · Sign-in and session (web and mobile)

Task origin: P1-C1 (web sign-in, PR #29), P1-C3 (mobile sign-in, PR #55), P1-C4 (logout for both channels, PR #56), P1-C5 (`GET /system/info`, PR #54) and the review fixes of the mobile stack (PR #57); the profile shape is reused by P1-C2 and P2-D1. Facts re-checked against `develop` at `9559d35` (first written at `106b40e`; the mobile stack was added on 2026-11-06). Paths are inside `project-backend/ATLANTECH/` unless stated. The password gate and the password-change endpoint are F08; roles, abilities and scope are F09; the audit writer is F10; the order of the checks is F11.

## 1. What it does

Users sign in through **two channels**. Both check the same things (throttle, password, active account) with the same service, `SignInService`, and both answer with the same profile.

| Channel | Who | How the user stays signed in |
| --- | --- | --- |
| **Web** | office staff on the Vue dashboard | a **cookie session** (Laravel Sanctum "SPA mode"), 120 minutes idle |
| **Mobile** | pilots, tug masters, terminal supervisors (and any role) on the Flutter tablet app | an **opaque Bearer token** (Sanctum personal access token) tied to one device, valid 30 days from its last use |

Endpoints:

| Endpoint | Who | Answer |
| --- | --- | --- |
| `GET /sanctum/csrf-cookie` (Sanctum, outside `/api/v1`) | web, anyone | 204 and an `XSRF-TOKEN` cookie |
| `POST /api/v1/auth/login` | web guest | 200 profile and a new session id |
| `POST /api/v1/auth/mobile/login` | mobile guest | 200 `{token, expires_at, user}` and a device row |
| `GET /api/v1/auth/me` | signed-in user (session **or** Bearer token) | 200 profile (same shape), used after a page reload |
| `POST /api/v1/auth/logout` | signed-in user (session or token) | 204; the session ends, or the token is revoked |
| `GET /api/v1/system/info` | anyone (no sign-in) | 200 port facts and `min_app_version`; the app's connectivity check |

(`POST auth/change-password` is F08.) The profile is `{ id, name, role, terminal_ids, must_change_password }`. `role` is the SQL value of the user's **one active role** (for example `PORT_STAFF`), or `null` if the user has none. `terminal_ids` is `[terminal_id]` for a terminal user and `[]` for everyone else.

What it does **not** do:

- No lockout of an account (post-MVP, FW-09). Only a throttle (section 3.3), shared by both channels.
- No audit row for a **successful** sign-in (lead decision Q10). Only refusals are audited.
- No password reset, no user creation, no revoking of a device by the Port Admin (P2-E1, P2-E3). A device is revoked by setting `user_devices.revoked_at`; nothing in the API does that yet, but the sign-in and token checks already honour it.
- No push-token handling and no sync (later phases).
- No check of role or ability at sign-in: it works for every active user, even one with no role. Roles are F09.
- Tokens are never deleted (FR-GL-03); `sanctum:prune-expired` must not be scheduled (section 8).

## 2. Request or data flow

### 2.1 Sign-in (browser, Sanctum SPA flow)

1. The SPA calls `GET /sanctum/csrf-cookie` from its own origin (`Origin`/`Referer` must match `SANCTUM_STATEFUL_DOMAINS`, F01 3.5). Sanctum answers 204 and sets `XSRF-TOKEN`. Test: `WebSignInTest` "hands the web app an XSRF-TOKEN cookie first".
2. The SPA calls `POST /api/v1/auth/login` with header `X-XSRF-TOKEN` (the cookie value) and body `{username, password}`. Route: `routes/api.php:20`, **outside** the `port.api` group (a guest must reach it).
3. Because `bootstrap/app.php:37` calls `statefulApi()`, Sanctum's `EnsureFrontendRequestsAreStateful` sees the trusted origin and runs the cookie, session and CSRF middleware for this request (`vendor/laravel/sanctum/src/Http/Middleware/EnsureFrontendRequestsAreStateful.php:48-56`). A missing or stale CSRF token ends here with **419 `SESSION_EXPIRED`** (F03).
4. `LoginRequest` validates and normalises (`LoginRequest.php:16-29`): `username` is trimmed and lower-cased **before** validation (`prepareForValidation`), then `required|string|min:3|max:50`; `password` is `required|string|max:255`. A failure is **422 `VALIDATION_FAILED`** with `errors` per field. Nothing is audited or throttled for 422.
5. `AuthController::login` (`AuthController.php:31-48`) first checks `$request->hasSession()`. A request from an origin that is not stateful has no session, so it answers **400 `BAD_REQUEST`** at once: no throttle hit, no audit row (`WebSignInTest` "answers 400 BAD_REQUEST, without throttling or audit...").
6. `SignInService::authenticate(username, password, ip, route)` runs (details in 3.3):
   1. throttle check on two keys, else **429 `TOO_MANY_ATTEMPTS`** with `Retry-After`;
   2. look the user up by `username`;
   3. unknown user: one `Hash::make` for timing, then refuse; known user with a wrong password: refuse; both give the **same 401 `UNAUTHENTICATED`** with detail "The username or password is incorrect.";
   4. right password but `is_active = 0`: **403 `ACCOUNT_DISABLED`**;
   5. success: clear the username throttle key and return the `User`.
7. Every refusal in step 6 counts one hit on both throttle keys and writes one `REJECTED_ATTEMPT` audit row (F10).
8. Back in the controller: `Auth::guard('web')->login($user)` (`:43`) puts the user in the session, then `$request->session()->regenerate()` (`:44`) gives the session a **new id** (this is the protection against session fixation; test "gives the session a new id on sign-in").
9. The controller loads `activeRoleAssignment.organizationUnit` and returns `UserProfileResource` (200). The session cookie `atlantech_session` is set by the framework.

### 2.2 Profile after a reload

1. `GET /api/v1/auth/me` is inside `port.api` (`routes/api.php`): `auth:sanctum` first (a valid session cookie **or** a valid Bearer token, else **401 `UNAUTHENTICATED`**), then `EnsureTokenDevice` (Bearer requests only, section 2.5), then `EnsureAccountUsable` (F08: a deactivated user gets 403 and is signed out, or has the token revoked; `must_change_password` does not block this route), then `EnsureMinAppVersion` (426 check, only when `X-App-Version` is sent) and the licence hook (pass-through).
2. `AuthController::me` (`:53-56`) reloads the relation and returns the same resource as login. `ProfileTest` proves the body is `assertExactJson` equal to the sign-in answer.

### 2.3 Logout (web and mobile)

1. `POST /api/v1/auth/logout` is inside `port.api`, so it needs a session (with a CSRF token, else 419) or a Bearer token (with the device headers), and it stays open while a password change is pending.
2. `AuthController::logout` reads `$request->user()->currentAccessToken()`.
   - **Mobile (a `PersonalAccessToken`):** `$token->revoke()` sets `revoked_at` (the row stays, FR-GL-03) and the answer is 204. The user's **other devices keep their tokens**.
   - **Web:** `Auth::guard('web')->logout()`, then, if there is a session, `invalidate()` (drops all data, new id) and `regenerateToken()` (new CSRF token), then 204.
3. The next request with that session or token is 401 (`PasswordChangeTest` "ends the session, so the next request is 401"; `MobileLogoutTest` "revokes the token on logout, keeps the row, and refuses the token everywhere afterwards").

### 2.4 Mobile sign-in: `POST /api/v1/auth/mobile/login`

The tablet sends no `Origin`, so Sanctum treats the request as **stateless**: no session, no CSRF. Headers `X-App-Version` and `X-Device-Id`, body `{username, password, platform}`.

1. Route middleware, in this order (`routes/api.php` and the priority list in `bootstrap/app.php`): `EnsureMobileHeaders`, then `app.version` (`EnsureMinAppVersion`). The route is outside `port.api`.
2. **`EnsureMobileHeaders`**: both headers are validated first. `X-App-Version` must match `x.y.z` (max 20 characters), `X-Device-Id` is required, a string, max 100. A missing or malformed one is **422 `VALIDATION_FAILED`** with the header name as the key in `errors` (for example `errors."X-Device-Id"`).
3. **`EnsureMinAppVersion`**: the version is compared with `port_settings.min_app_version` as a version number (`1.10.0` is newer than `1.9.0`). Older gives **426 `APP_UPDATE_REQUIRED`** with `min_app_version` in the body, **before** the body or the password is looked at, so an old app learns it must update even when its body is outdated, and no token is issued. A missing or malformed setting gives **503 `SERVICE_UNAVAILABLE`**.
4. `MobileLoginRequest` (extends `LoginRequest`): same username/password rules, plus `platform` required and one of `ANDROID`, `IOS` (**422** otherwise).
5. `MobileAuthController::login` calls `SignInService::authenticate(...)`, the same call as the web: throttle (429), unknown user or wrong password (the same 401), inactive (403 `ACCOUNT_DISABLED`). The throttle counters are **shared with the web**.
6. `MobileSignInService::issueToken($user, $deviceId, $platform, $appVersion, $route)` runs in one `DB::transaction`:
   1. read the `user_devices` row for this user and device id with `lockForUpdate()`;
   2. if the device exists and `revoked_at` is set: leave the transaction (returning the row);
   3. if it does not exist: create it (a race with a second first sign-in is handled: the loser hits the unique key `uq_user_device` and takes the winner's row);
   4. update the row (`platform`, `app_version`, `last_seen_at = now`);
   5. set `revoked_at = now` on every still-live token of that device (**one live token per device**);
   6. create a new token: `name` = device id, `token` = SHA-256 of a random string, `abilities ['*']`, `expires_at = now + 30 days`, `user_device_id`.
7. If step 6.2 happened, **after** the transaction: `AuditService::rejectedAttempt(route, DeviceRevoked, 'device_revoked', user id, username, 'user_devices', device id)` and **403 `DEVICE_REVOKED`**, no token. (It is audited after the transaction so the row is not rolled back with it, see F10.)
8. `MobileSignInResource` answers 200: `{ "token": "<id>|<secret>", "expires_at": <ISO UTC>, "user": <UserProfileResource> }`. `expires_at` is the value at sign-in; it then slides with each use. A successful sign-in writes no audit row.

### 2.5 A mobile request with the token (every `port.api` route)

1. `auth:sanctum` finds the user from the `Authorization: Bearer <id>|<secret>` header. Unknown, expired or revoked tokens give **401** (`AccessServiceProvider` tells Sanctum to reject a token whose `revoked_at` is set; Sanctum checks `expires_at`).
2. **`EnsureTokenDevice`** (only when the principal's token is a `PersonalAccessToken`; a web session passes untouched):
   1. `X-Device-Id` and `X-App-Version` must be present and well-formed, else **422** (lead decision D-B, 2026-11-05: a Bearer request must always send the version);
   2. the token's device row must have the same device id, else **401** and an audit row `device_mismatch`;
   3. if the device row has `revoked_at`: the token is revoked too, **401**, audit row `device_revoked` (a revoked device cannot keep working until its token expires);
   4. otherwise `markUsed()`: stamp `last_used_at` and move `expires_at` to **30 days from now** (the sliding expiry, FR-AC-06). Sanctum's own stamp is switched off (`config/sanctum.php` `last_used_at => false`) so a refused request can never extend a token.
3. `EnsureAccountUsable` (F08), `EnsureAbility` / `EnsureTerminalScope` (F09), `EnsureMinAppVersion` (**426** if the version is below the minimum), the licence hook, route binding, the version token (F11, F12).

### 2.6 `GET /api/v1/system/info`

Public (no `auth`, not in `port.api`), never refused with 426 (it is where the app reads the minimum version). `SystemInfoController::__invoke` reads `PortProfile` id 1 (none: **503 `SERVICE_UNAVAILABLE`**), then returns `{port_id (= public_id), port_code, port_name, license_mode: "FULL", token_warning_days_left: null, min_app_version}`. `min_app_version` comes from `MinAppVersionSource` (3.13); a missing or malformed setting is 503 too. `license_mode` and `token_warning_days_left` are fixed until the licence work (DEC-54). It is also the app's connectivity check (DEC-58), so there is no `/system/ping`. The controller lives in **MasterData** (it reads the port profile), not in Access; see section 5.

## 3. Every piece of code

### 3.1 Routes (`routes/api.php`)

Real `route:list --except-vendor` (8 routes) and the middleware of each:

| Route | Name | Middleware |
| --- | --- | --- |
| `POST auth/login` → `AuthController@login` | `auth.login` | `api` only |
| `POST auth/mobile/login` → `MobileAuthController@login` | `auth.mobile.login` | `api`, `EnsureMobileHeaders`, `EnsureMinAppVersion` (alias `app.version`) |
| `GET system/info` → `SystemInfoController` (invokable) | `system.info` | `api` only |
| `GET auth/me` → `AuthController@me` | `auth.me` | `api`, `port.api` |
| `POST auth/change-password` → `@changePassword` (F08) | `auth.change-password` | `api`, `port.api`, `throttle:5,1` |
| `POST auth/logout` → `@logout` | `auth.logout` | `api`, `port.api` |
| `GET audit-logs` → `AuditLogController@index` (F10) | `audit-logs.index` | `api`, `port.api`, `ability:audit.read` |

The `/api/v1` prefix comes from `bootstrap/app.php` (`apiPrefix`). `port.api` is now the group `auth:sanctum`, `EnsureTokenDevice`, `EnsureAccountUsable`, `EnsureMinAppVersion`, `EnsureLicenseAllowsWrite` (`bootstrap/app.php`, F11). `auth:sanctum` accepts the web session first and then a Bearer token (the Sanctum guard checks the `web` guard from `config('sanctum.guard')` before the token). The route **names** matter: `EnsureAccountUsable` and `RouteAbilityCoverageTest` use them. Routes without an `ability:` are on the allow-list of `RouteAbilityCoverageTest` (F09): `auth.login`, `auth.mobile.login`, `auth.me`, `auth.change-password`, `auth.logout` and `system.info`.

### 3.2 `AuthController` (`app/Modules/Access/Http/Controllers/AuthController.php`)

Constructor takes `SignInService` (injected). Methods:

| Method | Input | Output | Notes |
| --- | --- | --- | --- |
| `login(LoginRequest)` | validated body, request IP, `"POST /api/v1/auth/login"` (built from `method()` and `path()`) | `UserProfileResource` (200) | Throws `ApiProblemException` 400 without a session. Passes the IP and the route string to the service so the audit row can name them. **Web only**; mobile has its own controller. |
| `me(Request)` | session user or token user | `UserProfileResource` | `$request->user()` is non-null because the route is behind `auth:sanctum`. |
| `logout(Request)` | session or token | 204 `Response` | Revokes the Bearer token if the request has one, else ends the web session (2.3). |
| `changePassword(ChangePasswordRequest, PasswordChangeService)` | | | **F08.** |

Why `Auth::guard('web')->login()` and not `attempt()`: the service already checked throttle, password and `is_active` in the order the lead wanted, so the controller only starts the session (the service's own docblock: "starting the session or issuing a token is the caller's job"; the mobile controller issues a token instead).

### 3.3 `SignInService` (`app/Modules/Access/Services/SignInService.php`)

Constants (`:22-26`):

| Constant | Value | Meaning |
| --- | --- | --- |
| `MAX_ATTEMPTS_PER_USERNAME` | 5 | failed attempts allowed per minute for one username **from one IP** |
| `MAX_ATTEMPTS_PER_IP` | 20 | failed attempts allowed per minute for one IP across all usernames |
| `DECAY_SECONDS` | 60 | window length passed to `RateLimiter::hit` |

Throttle keys (`:109-116`), stored by Laravel's `RateLimiter` in the default cache store (Redis in `.env`):

- username key: `'sign-in:username:'.sha1($username.'|'.$ip)`
- IP key: `'sign-in:ip:'.$ip`

Methods:

| Method | What it does |
| --- | --- |
| `authenticate(string $username, string $password, string $ip, string $route): User` | The whole check, in this fixed order: `ensureNotThrottled`, then `retrieveByCredentials(['username' => ...])` through the `web` guard's user provider (`:45-46`), unknown user → `Hash::make($password)` then `refuse(... Unauthenticated, 'invalid_credentials', ..., detail "The username or password is incorrect.")`; `validateCredentials` false → the same refusal; `! $user->is_active` → `refuse(... AccountDisabled, 'account_disabled')`; success → `RateLimiter::clear($usernameKey)` and return the user. |
| `recordRejectedAttempt(route, ErrorCode, reason, username, ?User $actor = null)` | A thin delegator: calls `AuditService::rejectedAttempt($route, $code, $reason, $actor?->id, $username, 'users', $actor?->id)` (`:72-75`). Still used by `EnsureAccountUsable` and `ChangePasswordRequest` (F08). The P2-D3 plan said to delete it once everyone calls the audit service directly (open point). |
| `ensureNotThrottled(usernameKey, ipKey)` (private) | Collects the keys that are over their limit (`RateLimiter::tooManyAttempts`), and if any, throws `ApiProblemException(ErrorCode::TooManyAttempts, headers: ['Retry-After' => max(1, longest availableIn)])`. Never `abort(429)` (lead answer Q3). |
| `refuse(...)`: `never` (private) | Counts one hit on **both** keys, writes the audit row, throws `ApiProblemException($code, $detail)`. |
| `usernameKey`, `ipKey` (private) | The keys above. |

Behaviours worth knowing:

- **Same answer for unknown user and wrong password.** Both throw `ErrorCode::Unauthenticated` with the same detail string (`lang/en/errors.php:35`) and the same `reason` `invalid_credentials`. The body's `message_key` stays `errors.unauthenticated` (F03).
- **Timing.** For an unknown username the service calls `Hash::make($password)` once, which costs about the same as checking a real hash. The first design used a fixed dummy hash built per request, which was slower (about 0.50 s versus 0.30 s) and leaked which usernames exist; review 3 replaced it with one `Hash::make` (live measurement about 0.29 s for both). `WebSignInTest` "spends one password hash on an unknown username" spies on `Hash` to assert `make` is called once and `check` never. Timing itself is not tested (not reliable in CI).
- **Credentials before `is_active`.** A disabled account is only revealed to someone who knows the password (so 403 never leaks which usernames exist).
- **Throttle counts failures only, includes 403.** The 5th wrong attempt answers 401; the 6th (even with the right password) answers 429. A **successful** sign-in clears only the username key, not the IP key (`:62`). 422 (bad shape), 400 (no session) and 429 itself do not count and are not audited.
- **What is audited.** On 401 and 403 only: `AuditService::rejectedAttempt` writes `audit_logs` with `action = REJECTED_ATTEMPT`, `entity_type = 'users'`, `entity_id = NULL`, `actor_id = NULL`, `before_state = NULL`, and `after_state = {route, status, code, reason, username}`; for example `{"route":"POST /api/v1/auth/login","status":401,"code":"UNAUTHENTICATED","reason":"invalid_credentials","username":"nobody.here"}`. `username` is the lower-cased **input**, never the password (`WebSignInTest` asserts the wrong password text is absent).

### 3.4 `LoginRequest` (`app/Modules/Access/Http/Requests/LoginRequest.php`)

Rules and the normalisation are in 2.1 step 4. No `authorize()` override (default true: the endpoint is public). The lower-casing is `mb_strtolower(trim(...))` and runs only when `username` is a string. Why: the database stores usernames lower-case and enforces it with `chk_user_username_lower` (DEC-35), so `"  Staff.Ahmed "` must find `staff.ahmed` (test "trims and lowercases the username").

### 3.5 `UserProfileResource` (`app/Modules/Access/Http/Resources/UserProfileResource.php`)

`toArray()` returns exactly `id`, `name`, `role` (`$scope->role?->value`), `terminal_ids` (`$scope->terminalIds`), `must_change_password`. `AppServiceProvider` calls `JsonResource::withoutWrapping()` (F01 3.9), so the body is the bare object, not `{"data": ...}`. Both `role` and `terminal_ids` come from `User::accessScope()` (3.6), so the profile and the permission checks can never disagree.

**How `terminal_ids` is derived** (`AccessScope::for`, `AccessScope.php:25-33`): load `activeRoleAssignment.organizationUnit`; if the active assignment's `unit_type` is `TERMINAL`, take `organizationUnit.terminal_id` as the single id `[(int) $terminalId]`; otherwise `[]`. So it is a list of 0 or 1 ids today. `terminal_id` is not stored on the assignment: it lives on the organisation unit (the database check `chk_org_unit_terminal` says a `TERMINAL` unit has a `terminal_id` and the other two types have none).

### 3.6 The `User` model (`app/Modules/Access/Models/User.php`)

Extends Laravel's `Authenticatable`. Attributes:

| Attribute | Effect |
| --- | --- |
| `#[Table(name: 'users')]` | explicit table |
| `#[DateFormat('Y-m-d H:i:s.u')]` | dates written with microseconds, because `updated_at` is the **version token** (F12) |
| `#[Fillable([...])]` | `name, username, password_hash, phone, is_active, must_change_password` |
| `#[Hidden(['password_hash'])]` | never serialised |
| `#[UseFactory(UserFactory::class)]` | `Database\Factories\Access\UserFactory` |
| `use HasApiTokens` (Sanctum) | `tokens()`, `currentAccessToken()`, `createToken()`; since P1-C3. `currentAccessToken()` is a `PersonalAccessToken` for a Bearer request and a Sanctum `TransientToken` for a web session. |

Members:

| Member | What it does |
| --- | --- |
| `$authPasswordName = 'password_hash'` | tells the auth system which column holds the hash (there is no `password` column) |
| `$rememberTokenName = ''` | no remember-token column, no "remember me" |
| `casts()` | `password_hash => 'hashed'` (assigning a plain string hashes it; this is how F08 saves a password), `is_active` and `must_change_password` boolean, dates `datetime` |
| `devices()` | `hasMany(UserDevice)` (used by P1-C3 later) |
| `roleAssignments()` | `hasMany(UserRoleAssignment)` (history; ended rows stay) |
| `activeRoleAssignment()` | `hasOne(UserRoleAssignment)->whereNull('active_to')` (the one row with no end date, DEC-36) |
| `accessScope()` | `AccessScope` built once per `User` instance and cached in a private property (`:92-95`). Gotcha: a long-lived instance does not see a role change made after the first call. |
| `auditLogs()` | `hasMany(AuditLog, 'actor_id')` |

Note: `Notifiable` is used but there is no email column, so the trait is inert. `HasApiTokens` is what lets `auth:sanctum` and the mobile sign-in work; the token model is `PersonalAccessToken` (3.12), registered in `AccessServiceProvider`.

### 3.7 `UserRoleAssignment`, `OrganizationUnit`, `UserDevice`

| Model | Table | Key facts |
| --- | --- | --- |
| `UserRoleAssignment` | `user_role_assignments` | `timestamps: false`. Fillable `user_id, role, organization_unit_id, unit_type, active_from, active_to`. Casts `role => Role`, `unit_type => UnitType`, dates. Relations `user()`, `organizationUnit()`. Never deleted: ending a role sets `active_to` (DEC-36). `active_assignment_key` is a generated column (`IF(active_to IS NULL,1,NULL)`) and is never written. |
| `OrganizationUnit` | `organization_units` | `timestamps: false`. Fillable `unit_type, terminal_id`. Cast `unit_type => UnitType`. Relation `roleAssignments()`. `terminal_id` is a plain column (MasterData is a later layer, so Access cannot declare the relation; `Terminal::organizationUnit()` links back). `single_key` is generated, never written. |
| `UserDevice` | `user_devices` | `timestamps: false`. Fillable `user_id, device_id, platform, app_version, last_seen_at, revoked_at, push_token, push_token_updated_at`; `push_token` hidden; casts `platform => DevicePlatform`. **Web sign-in does not touch it; mobile sign-in creates and updates it** (`MobileSignInService`, 3.11). One row per (user, device id): the unique key `uq_user_device` means two users on one tablet get two rows. Never deleted: `revoked_at` is set (by the Port Admin, P2-E3, not built yet), after which sign-in from that device is refused with 403 `DEVICE_REVOKED` and an existing token of it is revoked on its next use. `chk_ud_push_revoked` requires a revoked device to have no push token. `push_token` is not written by sign-in. |

### 3.8 Enums

| Enum | Cases (value) | Used by |
| --- | --- | --- |
| `DevicePlatform` | `Android = 'ANDROID'`, `Ios = 'IOS'` | `UserDevice.platform` |
| `UnitType` | `HarbourAuthority = 'HARBOUR_AUTHORITY'`, `MarineServices = 'MARINE_SERVICES'`, `Terminal = 'TERMINAL'` | `organization_units.unit_type`, `user_role_assignments.unit_type`, `AccessScope::for` |

(`Role` is F09.) Values equal the SQL ENUM values exactly (F06 explains the pattern).

### 3.9 Factories used by sign-in tests

| Factory | Default | States |
| --- | --- | --- |
| `UserFactory` | random `name`, `username = 'user.'.random10`, `password_hash` = `Hash::make('password')` computed once per run (static cache), `is_active` true, `must_change_password` false | `inactive()` |
| `OrganizationUnitFactory` | a **terminal** unit with a new `Terminal::factory()` | `harbourAuthority()`, `marineServices()` (one each allowed by `uq_org_unit_type_key`) |
| `UserRoleAssignmentFactory` | role `TERMINAL_OFFICER`, unit type `TERMINAL`, new user and unit, `active_from = now()` | `ended()` (history row) |
| `UserDeviceFactory` | random uuid device, random platform, version `1.0.0` | `revoked()` |

`tests/Pest.php` adds two helpers: `useSchemaDatabase()` (points the default connection at the MySQL test database and runs `migrate:fresh` once per run) and `makeUserWithRole(?Role, array $attributes, ?OrganizationUnit $unit)` (a user with one active role in the right kind of unit; `null` makes a user with no role). Role and unit type must match (`chk_ura_role_matches_unit`), which is why the helper picks the unit by role.

### 3.10 Configuration

| Key | Value | Why |
| --- | --- | --- |
| `bootstrap/app.php` `statefulApi()` | on | Sanctum SPA cookie auth for the `api` group; a request from a non-stateful origin (the tablet) is stateless |
| `bootstrap/app.php` `port.api` group | `auth:sanctum`, `EnsureTokenDevice`, `EnsureAccountUsable`, ... | the signed-in routes accept a session or a Bearer token |
| `bootstrap/app.php:75` `redirectGuestsTo` | `null` for `api/*` | a guest on the API gets 401 JSON, not a redirect to a non-existent `login` route |
| `config/sanctum.php` `stateful` | from `SANCTUM_STATEFUL_DOMAINS` (`.env.example`: `localhost:5173,atlantech.test`) | only these origins get a session |
| `config/sanctum.php` `guard` | `['web']` | the guard Sanctum tries first (the session) |
| `config/sanctum.php` `expiration` | `null` | no global token expiry; each token carries its own `expires_at` (30 days, sliding) |
| `config/sanctum.php` `last_used_at` | `false` | Sanctum would stamp a token before the device check; `EnsureTokenDevice` stamps it only after `X-Device-Id` matches, so a refused request never extends a token (P1-C3 review 2) |
| `config/sanctum.php` `middleware.authenticate_session` | `AuthenticateSession::class` | **active**; see F08 section 8 (it ends other sessions when the hash changes) |
| `config/session.php` | driver `database`, lifetime 120 min, cookie `atlantech_session`, `same_site lax`, `http_only true` | idle timeout chosen by the lead (Q1; the requirement NFR-SEC-3 says 15 min, a recorded deviation) |
| `config/cors.php` | `supports_credentials true`, paths `api/*` and `sanctum/csrf-cookie` | |
| `lang/en/errors.php` `auth.invalid_credentials` | "The username or password is incorrect." | detail of the 401 |
| `lang/en/errors.php` `device_revoked` | "This device has been revoked by your port administrator. Contact your port administrator to use it again." | detail of 403 `DEVICE_REVOKED` |
| `port_settings.min_app_version` (database, not config) | `x.y.z`, seeded by `PortProfileAndSettingsSeeder` (F13) | the 426 threshold and the value in `system/info` |

### 3.11 Mobile classes (`app/Modules/Access`)

| Class | What it does |
| --- | --- |
| `Http/Controllers/MobileAuthController` | `login(MobileLoginRequest): MobileSignInResource`. Builds the route string, calls `SignInService::authenticate(...)`, then `MobileSignInService::issueToken($user, X-Device-Id, platform (enum), X-App-Version, $route)`. Docblock lists 422, 426, 429, 401, 403 `ACCOUNT_DISABLED` and 403 `DEVICE_REVOKED`. |
| `Http/Requests/MobileLoginRequest` | extends `LoginRequest` (so the trim and lower-case of the username apply); adds `platform => required, Rule::enum(DevicePlatform)`. |
| `Http/Resources/MobileSignInResource` | `{token: plainTextToken, expires_at: accessToken->expires_at, user: UserProfileResource(user with activeRoleAssignment.organizationUnit)}`. The plain token is shown only here. |
| `Http/Middleware/EnsureMobileHeaders` | `handle`: validates `X-App-Version` (`AppVersion::rules()`) and `X-Device-Id` (`deviceIdRules()`) and throws `ValidationException` (422). Public static helpers `deviceIdRules()` (`required, string, max:100`, the width of `user_devices.device_id`) and `validateHeaders($headers, $rules)` (uses the header name as the field name, so the message is "The X-Device-Id field is required."); `EnsureTokenDevice` and `EnsureMinAppVersion` reuse them. Only on the mobile login route. |
| `Http/Middleware/EnsureMinAppVersion` (alias `app.version`) | Real since P1-C3. If the request has no `X-App-Version` it passes. Otherwise it validates the header (422), reads the minimum from `MinAppVersionSource` and throws `ApiProblemException::appUpdateRequired($minimum)` (426 with `min_app_version`) when `AppVersion::isOlderThan`. In the `port.api` group it applies to **any** request that carries the header (web sends none; lead answer Q2 of P2-D2). Position fixed by the priority list (F11). |
| `Http/Middleware/EnsureTokenDevice` | see 2.5. Constructor `AuditService`. `handle` returns `$next` unless the principal's token is a `PersonalAccessToken`; validates both headers (422); compares `device->device_id` with the header (401 + audit `device_mismatch`); revokes the token and refuses when the device row is revoked (401 + audit `device_revoked`); else `markUsed()`. Private `refuse($request, $reason): never` writes `AuditService::rejectedAttempt(route, ErrorCode::Unauthenticated, $reason, user id, username, 'users', user id)` and throws 401. The same 401 for mismatch and revoked, so a stolen token learns nothing. |
| `Services/MobileSignInService` | `issueToken(...)` (2.4 step 6). Private `lockedDevice` (select for update), `registerDevice` (create, with the duplicate-key recovery: on `UniqueConstraintViolationException` it re-reads the winner's row, or throws 503 if it vanished), `newToken` (see below). Constructor `AuditService`. |
| `Models/PersonalAccessToken` | extends Sanctum's model; see 3.12. |

`newToken`: `$user->generateTokenString()`, stored `token = hash('sha256', plain)`, `name` = device id, `abilities ['*']` (Sanctum abilities are not used for roles; authorisation is F09), `expires_at = now()->addDays(30)`, `user_device_id`; the answer value is `"<id>|<plain>"`.

### 3.12 `PersonalAccessToken` (`app/Modules/Access/Models/PersonalAccessToken.php`)

Registered with `Sanctum::usePersonalAccessTokenModel(...)` in `AccessServiceProvider`, together with `Sanctum::authenticateAccessTokensUsing(fn ($token, $isValid) => $isValid && ! $token->isRevoked())` (a revoked token answers 401 like an expired one).

| Member | Detail |
| --- | --- |
| `LIFETIME_DAYS = 30` | sliding lifetime (FR-AC-06) |
| `$fillable` | `name, token, abilities, expires_at, user_device_id` |
| `casts()` | `revoked_at => datetime` |
| `markUsed()` | one `save()`: `last_used_at = now`, `expires_at = now + 30 days` |
| `device()` | `belongsTo(UserDevice, 'user_device_id')` |
| `isRevoked()` / `revoke()` | `revoked_at !== null` / set it once (a second call keeps the first time) |
| `delete()` | throws `LogicException` ("Tokens are never deleted (FR-GL-03): call revoke()"). This guards the model only; Sanctum's `sanctum:prune-expired` deletes with a query, so `TokenPruneNotScheduledTest` fails if it is scheduled or named in `routes/console.php`. |

Table `personal_access_tokens` (migration `..._create_personal_access_tokens_table.php`): Sanctum's columns plus `user_device_id` (FK `fk_pat_user_device` to `user_devices`), `revoked_at`, `last_used_at`, `expires_at` (indexed `idx_pat_expires_at`), `token` unique `uq_pat_token` (SHA-256 hex, 64 characters), `name` (the device id at sign-in), morph columns `tokenable_type`/`tokenable_id`. No worker grant (a framework table like `sessions`).

### 3.13 Shared and MasterData pieces

| Class | What it does |
| --- | --- |
| `Shared/Support/AppVersion` (final) | `PATTERN = '/^\d+\.\d+\.\d+$/'` (only `x.y.z`: `1.2`, `1.2.0-rc1` and `latest` are refused); `rules()` = `required, string, max:20, regex`; `isOlderThan($v, $min)` = `version_compare($v, $min, '<')`. 20 is the width of `user_devices.app_version`. |
| `Shared/Support/MinAppVersionSource` (interface) | `minAppVersion(): string`; defined in Shared because the 426 check lives in Access, which may not use MasterData. |
| `MasterData/Services/PortSettingMinAppVersion` | implements it: reads `port_settings` where `setting_key = 'min_app_version'`; missing or not `x.y.z` gives `ApiProblemException(ErrorCode::ServiceUnavailable)` (503). Bound in `MasterDataServiceProvider::register()`. |
| `MasterData/Http/Controllers/SystemInfoController` | 2.6. |
| `Shared/Errors/ErrorCode::DeviceRevoked` | `DEVICE_REVOKED`, status 403, title "Device revoked", message key `errors.device_revoked` (F03). Listed in `error-codes.md` v1.3. |

### 3.14 Test support

`tests/TestCase.php` overrides `be()` to call `$this->app['auth']->forgetGuards()` first, because the `auth:sanctum` guard keeps the first user it resolved for the life of the application; without it a test that signs in a second user would still be answered as the first. Tests that call the API twice with different identities call `app('auth')->forgetGuards()` themselves.

## 4. Why this design

| Choice | Reason | Source |
| --- | --- | --- |
| Sanctum SPA cookie session for web (not JWT) | Approved contracts and roadmap say Sanctum; requirement NFR-SEC-3 (JWT RS256, 15-minute web session) conflicts and was overruled. | CT-03; lead, 2026-10-29 (P1-C1 section 7) |
| Identifier is `username` (lower-case, unique, no email) | users have no email column; shifts and demo accounts are created by the Port Admin | DEC-35; FR-AC-01; `chk_user_username_lower` |
| One active role per user | `uq_ura_active` (unique on `user_id`, `active_assignment_key`); the profile has a single `role` | DEC-36 |
| `role` is the SQL value (`PORT_STAFF`) | CT-03's first example said `PORT_AUTHORITY_STAFF`; the enum wins and the contract was corrected | P1-C1 Q6 |
| User with no active role signs in with `role: null` | the web can still show "no role"; every other endpoint answers 403 on its own | P1-C1 N2 |
| 120-minute idle session | long shifts | P1-C1 Q1 |
| No lockout, throttle 5/min per username+IP and 20/min per IP | roadmap says lockout is post-MVP | P1-C1 Q2, Q3 |
| 429 thrown as `ApiProblemException`, never `abort(429)` | the stable problem format (F03) | P1-C1 Q3 |
| New code `SESSION_EXPIRED` 419 for a CSRF mismatch | otherwise `ProblemRenderer` would map 419 to `BAD_REQUEST` | P1-C1 N4 |
| 400 `BAD_REQUEST` when the request has no session | review 1 found a 500 for correct credentials from a non-stateful origin (curl, Postman); the check runs before the credentials so it costs no throttle hit | P1-C1 review 1, decision approved 2026-10-30 |
| `after_state` holds `status` **and** `code` | Coding Notes §6.4 asks for the HTTP status, the lead's N6 asked for the code string; both are kept | P1-C1 review 1 conflict, approved 2026-10-30 |
| No audit of a successful sign-in | lead decision | P1-C1 Q10 |
| `GET auth/me` exists | the SPA needs the profile after a reload | P1-C1 N1 |
| Signed-in routes use `auth:sanctum` (session first, then Bearer token) | one group serves both channels. Until P1-C3 `auth/me` used `auth:web`, because a Bearer token sent to `auth:sanctum` would have queried a missing `personal_access_tokens` table (500); the table now exists, so the group moved. | P1-C1 review 3, P1-C3 (PR #57) |
| `SignInService` is separate from the controllers | the web and the mobile controller reuse the same checks and only differ in what they do after success (session or token) | P1-C1 section 6, P1-C3 |
| Bearer token, opaque, tied to one device, 30 days sliding | FR-AC-06; Sanctum personal access tokens (not JWT); the device id travels in a header so a copied token is useless on another tablet | CT-04, FR-AC-06, DEC-35 |
| One live token per device | signing in again on the same device revokes the old token | P1-C3 lead default D4 |
| `user_device_id` and `revoked_at` on the token table | the Port Admin must be able to revoke a device and see its tokens (P2-E3); tokens are never deleted | lead, 2026-11-01; FR-GL-03 |
| 426 is checked before the body and the password | an old app must learn it has to update even if its body is outdated, and no token is issued | CT-04, FR-AC-05, lead default D3 |
| Both device headers are required at sign-in; `X-Device-Id` at every Bearer request | a token belongs to one device; header names are the error keys | lead defaults D3, D6 |
| A Bearer request must send a well-formed `X-App-Version` (422), the web sends none | the 426 rule must apply to every tablet request, not only those that happen to send the header | lead decision D-B, 2026-11-05 (CT-04 v1.2) |
| Another device or a revoked device gives 401 (audited), a revoked device at **sign-in** gives 403 `DEVICE_REVOKED` | a stolen or lost tablet must not sign itself back in; 401 on tokens keeps the token path indistinguishable | DEC-60 (Closed 2026-11-01); CT-04 v1.2 |
| Sanctum's `last_used_at` stamp is off; `EnsureTokenDevice` slides the expiry | a request refused for a wrong device must not extend the token | P1-C3 review 2 |
| A deactivated user's token is revoked at the next request | same rule as the web session being ended | FR-AC-10, developer 2026-11-05 |
| `sanctum:prune-expired` is never scheduled | it deletes rows by query and would erase token history | FR-GL-03; `TokenPruneNotScheduledTest` |
| `GET /system/info` is public and also the connectivity check | the app reads `min_app_version` before it can sign in; no `/system/ping` | CT-04 v1.1, DEC-58, P1-C5 |
| `MinAppVersionSource` interface in Shared, bound by MasterData | the 426 check is in Access, which may not use MasterData tables | module layers (A4) |
| `Password` check uses the provider, not `Auth::attempt()` | the controller wants to decide the order (throttle, password, active flag) and to start the session itself; a side effect is that an outdated hash is **not** rehashed (harmless while there is one hashing setting; review note 6) | P1-C1 review 1 note 6 |

## 5. How it connects

- **Calls:** `MobileSignInService` → `UserDevice`, `PersonalAccessToken`, `AuditService`; `EnsureMinAppVersion` → `MinAppVersionSource` (MasterData reads `port_settings`); `SignInService` → `AuditService` (F10), `RateLimiter`, `Hash`, the `web` guard provider. `AuthController` → `UserProfileResource` → `User::accessScope()` → `AccessScope` (F09). Errors via `ApiProblemException` and `ErrorCode` (F03).
- **Called by:** the Vue sign-in page (FE-025); `EnsureAccountUsable` (F08) and `ChangePasswordRequest` reuse `SignInService::recordRejectedAttempt`; every later signed-in route relies on the session this feature starts.
- **Module layer:** the sign-in code is in `Access` (layer 2), which may use only `Shared`. `GET system/info` and the `port_settings` reader are in `MasterData` (layer 4, which may use Access and Shared), which is why the 426 check reads the minimum through the Shared interface `MinAppVersionSource`. That is why `OrganizationUnit.terminal_id` is a plain column and the `Terminal` link lives on the MasterData side (A4).
- **Depends on:** F01 (session, Sanctum, CORS config), F03 (problem responses, 419/429), F05/F06 (tables, models), F10 (audit).
- **Used by:** F08, F09, F11 (the `port.api` group begins with `auth:sanctum` and `EnsureTokenDevice`), the Flutter app (MB sign-in and sync), P2-E1 (user admin), P2-E3 (device revoke).

## 6. Data

| Table | Access | Notes |
| --- | --- | --- |
| `users` | read | `username` unique, check `chk_user_username_lower` (lower-case and at least 3 characters), `password_hash`, `is_active`, `must_change_password`, `updated_at DATETIME(6)` (version token) |
| `user_role_assignments` | read | `role` ENUM of the 12 roles, `unit_type`, `active_to` NULL means active, `uq_ura_active`, `chk_ura_role_matches_unit` (the role must fit the unit type), `chk_ura_period` |
| `organization_units` | read | `unit_type`, `terminal_id`, `chk_org_unit_terminal` (`TERMINAL` iff `terminal_id` set), unique `(unit_type, single_key)` |
| `sessions` | write (framework) | web sessions, database driver |
| `audit_logs` | insert (via F10) | only refusals 401/403 |
| `user_devices` | read/write (mobile sign-in) | one row per user and device id (`uq_user_device`); `platform`, `app_version`, `last_seen_at` updated at each sign-in; `revoked_at` (Port Admin, P2-E3) blocks sign-in and tokens; `chk_ud_push_revoked` |
| `personal_access_tokens` | read/write | mobile tokens: `user_device_id` FK, `token` (SHA-256), `expires_at` (30 days after last use), `last_used_at`, `revoked_at`; never deleted; Backend-only (no worker grant) |
| `port_settings` | read | `min_app_version` (`x.y.z`), seeded in F13 |
| `port_profiles` | read | row id 1 for `system/info` (`public_id`, `port_code`, `name`) |

Ownership (CT-15): all of these are Backend-owned. AI workers can read only `users.id`, `name`, `is_active` (`database/grants/workers.sql`, F05).

## 7. Tests

Run (from `project-backend/ATLANTECH`, MySQL running, your own database, see A7):

```bash
vendor/bin/pest tests/Feature/Access/WebSignInTest.php
vendor/bin/pest tests/Feature/Access/ProfileTest.php
```

| Test | Proves |
| --- | --- |
| `WebSignInTest` "hands the web app an XSRF-TOKEN cookie first" | `/sanctum/csrf-cookie` gives 204 and the cookie |
| "signs in, starts a new session and returns the profile" | 200, exact JSON, session cookie name `atlantech_session`, lifetime 120, no audit row, guest becomes authenticated |
| "gives the session a new id on sign-in (no session fixation)" | the id differs from the guest id |
| "answers 400 BAD_REQUEST, without throttling or audit, when the origin is not stateful" | 400 and no side effects; a later valid attempt still works |
| "trims and lowercases the username before matching it" | DEC-35 |
| "returns the role and terminal scope of the one active role" (3 datasets) | Harbour Authority and Marine Services give `[]`, Terminal gives `[terminal_id]` |
| "signs in a user with no active role with role null and no terminals" | N2 |
| "signs in a user who must change the password, with the flag set" | the gate itself is F08 |
| "answers 422 for a missing or invalid field, without an audit row" (3 datasets) | `required`, `min:3` |
| "answers the same 401 for an unknown user and a wrong password, and audits it" (2 datasets) | same code, key, detail; audit row with `actor_id` NULL, `entity_type users`, `after_state` shape, no password |
| "spends one password hash on an unknown username..." | `Hash::make` once, `Hash::check` never |
| "answers 403 ACCOUNT_DISABLED to a deactivated user with the right password, and audits it" | |
| "throttles after 5 failed attempts per username and IP, with Retry-After and no audit row" | 6th attempt is 429; exactly 5 audit rows (the 429 is not audited) |
| "throttles after 20 failed attempts from one IP across usernames" | |
| "clears the username throttle after a successful sign-in" | |
| "answers 419 SESSION_EXPIRED to a stateful request without a valid XSRF token" | the test sets the app environment to `local` because CSRF is skipped while testing |
| `ProfileTest` "returns the signed-in user profile, the same as the sign-in answer" | `assertExactJson` equals the login body |
| "answers 401 UNAUTHENTICATED without a session" | problem JSON |
| "answers 401, not 500, to a Bearer token while mobile tokens do not exist yet" | an unknown Bearer token is a clean problem 401 (the title is older than the mobile stack; the assertion still holds with `auth:sanctum`) |

**Mobile and system info** (run: `vendor/bin/pest tests/Feature/Access/MobileSignInTest.php tests/Feature/Access/MobileTokenTest.php tests/Feature/Access/MobileLogoutTest.php tests/Feature/MasterData/SystemInfoTest.php tests/Feature/Console/TokenPruneNotScheduledTest.php`). The mobile tests send no `Origin`; helpers `mobileSignIn()`, `signInTablet()`, `withTabletToken()` and `logoutTestToken()/logoutTestRequest()` call `app('auth')->forgetGuards()` before each request.

| Test file and case | Proves |
| --- | --- |
| `MobileSignInTest` "signs a tablet in: token, its expiry, the profile, the device row and the token row" | 200 keys `token, expires_at, user`, token shape `id|secret`, expiry = now + 30 days, device and token rows, no audit row |
| "answers 422 for a missing or malformed device header" (5 datasets) | no/ bad `X-App-Version`, no `X-Device-Id`, version `1.2`, `latest`, device id of 101 characters; the header name is the error key; no token |
| "answers 426 with min_app_version and no token, before the body and the password are checked" (3 datasets) | right password, wrong password and an outdated body all give 426, no device row, no audit |
| "answers 503 SERVICE_UNAVAILABLE while the port has no min_app_version" and "...while the stored min_app_version is not x.y.z" (4 datasets) | no token |
| "compares versions as numbers, not as text" | `1.10.0` ≥ `1.9.0` |
| "answers 422 for a missing or unknown platform" | |
| "answers 401 and 403 ACCOUNT_DISABLED as the web sign-in, audited, with no token" | same audit shape on `POST /api/v1/auth/mobile/login` |
| "shares the sign-in throttle with the web: 429 after 5 failed attempts" | |
| "refuses a revoked device with 403 DEVICE_REVOKED, audited, and issues no token" | audit: actor = user, `entity_type user_devices`, `entity_id` = device id, `reason device_revoked`, status 403 |
| "keeps one live token per device..." | the old token gets `revoked_at`, the device row is updated (`platform`, `app_version`) |
| "registers a second user on the same tablet as a separate device row" | |
| "lets an office role sign in on a tablet" | no role restriction at sign-in |
| "never deletes a token" | `delete()` throws |
| "does not touch the user row" | |
| "survives two first sign-ins racing on a new device..." | the duplicate-key recovery |
| `MobileTokenTest` "reads the profile with the Bearer token" | |
| "answers 422 without X-Device-Id and 401 for another device, audited, without extending the token" | reason `device_mismatch`; `last_used_at`/`expires_at` unchanged |
| "moves the expiry to 30 days after each use" | the slide |
| "answers a clean 401 for an expired, a revoked and an unknown token" (3 datasets) | problem JSON 401 |
| "answers 426 to a signed-in tablet whose app is below the minimum" | |
| "applies the password gate to the token, and changes the password with it; the other token is revoked (D-A)" | F08 |
| "refuses a wrong current password on the token route with 422" | `current_password:sanctum` |
| "answers 403 ACCOUNT_DISABLED to a deactivated user and revokes the token" | then 401 even after reactivation |
| "answers 422 to a Bearer request without a well-formed X-App-Version, and lets a web request through without it" (3 datasets) | D-B; token not extended |
| "refuses a token whose device was revoked: 401, audited as device_revoked, and the token is revoked too" | |
| "revokes the other tokens of the user after a password change from a tablet, and keeps the current one and every row" | F08 |
| `MobileLogoutTest` (3 cases) | token revoked, row and device row unchanged, every later request 401; other devices stay signed in; a user who must change the password can log out |
| `SystemInfoTest` | guest gets the fixed shape; `port_code` null allowed; never 426 (an old and a malformed `X-App-Version`, even with 'X-Device-Id'); 503 for no profile row, no setting, or a malformed setting |
| `TokenPruneNotScheduledTest` (2 cases) | `sanctum:prune-expired` is not scheduled or named in `routes/console.php` |

Result of the run for this guide on `develop` at `9559d35` (own empty database `atlantech_testing_g3`): `tests/Feature/Access`, `tests/Feature/Audit`, `tests/Unit/Modules/Access`, `SystemInfoTest`, `RouteAbilityCoverageTest`, `WriteChainOrderTest`, `PublishRoleMatrixTest` and `TokenPruneNotScheduledTest` together: **217 passed, 2017 assertions, 68 s**.

## 8. Gotchas and invariants

- **A web login without a stateful `Origin`/`Referer` has no session.** curl and Postman get 400 on `auth/login` unless they send `Origin: http://localhost:5173`, a matching `Referer` and a CSRF token. The tablet is the opposite: it must send **no** stateful `Origin`, or Sanctum would start a session for it.
- **Dev origin rule (browser):** the session cookie is `SameSite=Lax`, so the SPA and the API must be **same-site** (for example `localhost:5173` talking to `localhost:8000`, or a Vite proxy), not `localhost:5173` to `atlantech.test` (review 3 note; not tested in a browser).
- **Every Bearer request needs both device headers**: `X-Device-Id` (422 if missing, 401 if it is another device) and a well-formed `X-App-Version` (422; D-B). Mobile developers who forget `X-App-Version` on a plain GET get 422, not 200.
- **The order of mobile refusals:** 401 (bad/expired/revoked token) → 422 (headers) → 401 (wrong device, audited) → 401 (revoked device, token revoked, audited) → password gate / account (F08) → ability, terminal (F09) → 426 → licence → binding → version token. At **sign-in**: 422 headers → 426 → 422 body → 429 → 401 → 403 → 403 `DEVICE_REVOKED`.
- **Never delete a token or a device.** Use `revoke()` / `revoked_at`. Do not schedule `sanctum:prune-expired` (`TokenPruneNotScheduledTest`). `PersonalAccessToken::delete()` throws, but a query delete would not.
- **`auth:sanctum` keeps the first user it resolved for the life of the application.** In tests call `app('auth')->forgetGuards()` between identities (`tests/TestCase.php` does it for `be()`/`actingAs`).
- **Do not turn Sanctum's `last_used_at` back on**: it would extend a token before the device check.
- **`min_app_version` must be `x.y.z`.** A different shape in `port_settings` makes every tablet sign-in and every Bearer request that sends the header answer 503; `system/info` answers 503 too.
- **The throttle is shared by the two sign-in channels** and keys on the IP. Many tablets behind one NAT share the 20-per-minute IP limit.
- **Never audit or echo the password.** The audit `username` is the raw (lower-cased) input; if someone types a password into the username field it is stored in `after_state.username`. Only `audit.read` (Port Admin) can read it (P2-D3 review note).
- **Keep 401 and the throttle identical for unknown and wrong.** Any new branch before `refuse()` that answers differently for unknown users re-opens user enumeration. For the same reason a wrong-device token and a revoked-device token both answer plain 401.
- **The throttle keys use `$request->ip()`.** `trustProxies` is not set in `bootstrap/app.php` (checked), so behind a reverse proxy every client has the proxy's IP. Unverified: the deployment setting (P6).
- **`User::accessScope()` is memoised per instance.** A role changed in the same request is not seen.
- **The session regenerates on login and change-password and is invalidated on logout.** Do not store anything in the session that must survive those.
- **Spec drift test:** the cookie name in `openapi/port-api.yaml` (`cookieAuth.name`) must equal `config('session.cookie')`; `OpenApiDriftTest` fails otherwise (F04). The mobile paths and `bearerAuth` live in the same file.
- **Test helper names are global** (`signIn()`, `userWithRole()`, `rejectedAttempts()`, `mobileSignIn()`, `signInTablet()`, `withTabletToken()`, constants `SPA_ORIGIN`, `MOBILE_DEVICE_ID`, `TOKEN_DEVICE_ID`): a new test file must not reuse them.

## 9. How to extend it

**Change the throttle numbers.** Edit the three constants in `SignInService`; tests read the constants, so they follow. Record the lead's decision in the PR.

**Add a field to the profile.** (1) Add it to `UserProfileResource::toArray` and its `@return` shape. (2) Add it to the `UserProfile` schema in `openapi/port-api.yaml` and bump the minor version (F04). (3) Update `ProfileTest`, `WebSignInTest` and `MobileSignInTest` (`assertExactJson`/`assertJsonPath` will fail until you do). (4) Tell Frontend and Mobile (CT-03, CT-04).

**Change the token lifetime.** `PersonalAccessToken::LIFETIME_DAYS`; update `MobileSignInTest`/`MobileTokenTest` and CT-04 (it says 30 days).

**Change the minimum app version.** Update the `min_app_version` row of `port_settings` (`x.y.z`). No code.

**Add a new signed-in route.** Put it inside `Route::middleware('port.api')`, give it `->middleware('ability:<name>')` and a name (F09). Never outside the group: the group carries the token/device check and the password gate.

**Add a new mobile-only refusal at sign-in.** Do it in `MobileSignInService::issueToken` **after** the transaction ends if it must be audited (the audit row would roll back otherwise), and add an `ErrorCode` (F03 section 9) if it needs a new code.

**Add a refusal reason to web sign-in.** Call `$this->refuse($usernameKey, $ipKey, ErrorCode::X, 'reason_snake_case', $username, $route)`; it counts, audits and throws in one step. Add a test that asserts the audit row.

**Revoke a device (P2-E3).** Set `user_devices.revoked_at` (never delete). The next sign-in from it gets 403 `DEVICE_REVOKED` and an existing token gets 401 on its next use, with its token revoked.

## 10. Try it

Commands that were run for this guide (PowerShell, from `project-backend/ATLANTECH`, `php` is Herd's):

```bash
php artisan route:list --except-vendor
```
Real output (8 routes): `GET|HEAD /`, `GET|HEAD api/v1/audit-logs`, `POST api/v1/auth/change-password`, `POST api/v1/auth/login`, `POST api/v1/auth/logout`, `GET|HEAD api/v1/auth/me`, `POST api/v1/auth/mobile/login`, `GET|HEAD api/v1/system/info`.

```bash
node docs/reference/lookup.mjs DEC-35
```
Real output (earlier run): `DEC-35 · decision · Sign-in identifier`, `status: Closed`, affects `P1-C1` and `P1-C3`, unblocks `CT-03` and `CT-04`.

```bash
$env:DB_DATABASE='atlantech_testing_g3'; $env:DB_SCHEMA_TEST_DATABASE='atlantech_testing_g3'
vendor/bin/pest tests/Feature/Access/WebSignInTest.php tests/Feature/Access/MobileSignInTest.php tests/Feature/MasterData/SystemInfoTest.php
```
Real result: these files passed as part of the 217-test run above (2017 assertions, 68 s). The first run on an empty database takes about 40 seconds because `migrate:fresh` builds the schema.

**Expected, not run** (needs the server profile and seeded demo users, and I did not touch the `atlantech` database). With `docker compose --profile server up -d --build`:

```bash
curl -i -c jar -H "Origin: http://localhost:5173" -H "Referer: http://localhost:5173/" http://localhost:8000/sanctum/csrf-cookie
curl -i http://localhost:8000/api/v1/system/info
curl -i -H "X-App-Version: 1.0.0" -H "X-Device-Id: dev-1" -H "Content-Type: application/json" -d '{"username":"x.y","password":"wrong","platform":"ANDROID"}' http://localhost:8000/api/v1/auth/mobile/login
```
Expected: `204` with an `XSRF-TOKEN` cookie; `200` with `port_id`, `port_code`, `port_name`, `license_mode: "FULL"`, `token_warning_days_left: null`, `min_app_version`; and `401` problem JSON `UNAUTHENTICATED` (one new `REJECTED_ATTEMPT` row). With a right demo-user password the third call returns `200 {token, expires_at, user}`; then `curl -H "Authorization: Bearer <token>" -H "X-App-Version: 1.0.0" -H "X-Device-Id: dev-1" http://localhost:8000/api/v1/auth/me` returns the profile, the same call with `X-Device-Id: other` returns `401`, and `POST /api/v1/auth/logout` with the token returns `204`. A web login (`/api/v1/auth/login`) additionally needs the `Origin`, `Referer` and `X-XSRF-TOKEN` headers.

## 11. Open points

- **Contract status:** CT-04 v1.2 (DEC-60, `DEVICE_REVOKED`, D-B, other tokens revoked) is "Proposed, waiting for Mobile Team approval"; `auth-web.md` is v1.1 but CT-04 refers to "CT-03, v1.2" for the password-change rule (a numbering slip in the contract text; the behaviour matches the code).
- **No endpoint revokes a device yet** (P2-E3), so the `DEVICE_REVOKED` and `device_revoked` paths are only reachable through the database or tests.
- **`personal_access_tokens` rows grow forever** (expired and revoked tokens are kept on purpose). A retention plan is not decided. Unverified: whether a later task prunes by an own command.
- **`SignInService::recordRejectedAttempt`** is a leftover delegator (P2-D3 Q2: delete it when the callers use `AuditService` directly). `EnsureAccountUsable` and `ChangePasswordRequest` still call it.
- **Trusted proxies / real client IP** for the throttle: Unverified; `trustProxies` is not set in `bootstrap/app.php`; check the deployment (P6).
- **Password rehash** on login is not done (review note 6).
- **`license_mode` and `token_warning_days_left` in `system/info`** are fixed values (`FULL`, `null`) until the licence work (DEC-54).
- **`oas3-unused-component`** Spectral rule is switched off with the lead's OK until shared components are used (F04).
- **`Notifiable` on `User`** has no use (no email); harmless.
- **`terminal_ids` is derived from the unit type alone**, not from the role: a port-wide role placed in a `TERMINAL` unit would show a terminal id but `AccessScope::isTerminalScoped()` would be false. The database check `chk_ura_role_matches_unit` makes that combination impossible, so it is a guard, not a bug.
- **Token abilities are `['*']` for every token**; authorisation is by role (F09), not by Sanctum abilities. If abilities are ever used, `MobileSignInService::newToken` is the place.
- **Mobile sync and push** (P5) will reuse `X-Device-Id` and `user_devices.push_token`; not built.
