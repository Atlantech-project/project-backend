# F07 · Web sign-in and session

Task origin: P1-C1 (Sprint 1, PR #29), with the profile shape reused by P1-C2 and P2-D1. Facts checked against `develop` at `106b40e`. Paths are inside `project-backend/ATLANTECH/` unless stated. Password change is F08; roles, abilities and scope are F09; the audit writer is F10.

## 1. What it does

Office staff sign in on the Vue dashboard with a **username and password**. The server starts a **cookie session** (Laravel Sanctum "SPA mode"), answers with the user's **profile**, and from then on the browser sends the session cookie. There are three endpoints (a fourth, `change-password`, is F08):

| Endpoint | Who | Answer |
| --- | --- | --- |
| `GET /sanctum/csrf-cookie` (Sanctum, outside `/api/v1`) | anyone | 204 and an `XSRF-TOKEN` cookie |
| `POST /api/v1/auth/login` | guest | 200 profile and a new session id |
| `GET /api/v1/auth/me` | signed-in user | 200 profile (same shape), used after a page reload |
| `POST /api/v1/auth/logout` | signed-in user | 204, the session is gone |

The profile is `{ id, name, role, terminal_ids, must_change_password }`. `role` is the SQL value of the user's **one active role** (for example `PORT_STAFF`), or `null` if the user has none. `terminal_ids` is `[terminal_id]` for a terminal user and `[]` for everyone else.

What it does **not** do:

- No mobile sign-in, no tokens, no `user_devices` rows, no `personal_access_tokens` table (P1-C3, P1-C4). `UserDevice` exists as a model only.
- No lockout of an account (post-MVP, FW-09). Only a throttle (section 3.3).
- No audit row for a **successful** sign-in (lead decision Q10). Only refusals 401 and 403 are audited.
- No password reset, no user creation (P2-E1).
- No check of role or ability: sign-in and `auth/me` work for every active user, even one with no role. Roles are F09.

## 2. Request or data flow

### 2.1 Sign-in (browser, Sanctum SPA flow)

1. The SPA calls `GET /sanctum/csrf-cookie` from its own origin (`Origin`/`Referer` must match `SANCTUM_STATEFUL_DOMAINS`, F01 3.5). Sanctum answers 204 and sets `XSRF-TOKEN`. Test: `WebSignInTest` "hands the web app an XSRF-TOKEN cookie first".
2. The SPA calls `POST /api/v1/auth/login` with header `X-XSRF-TOKEN` (the cookie value) and body `{username, password}`. Route: `routes/api.php:17`, **outside** the `port.api` group (a guest must reach it).
3. Because `bootstrap/app.php:60` calls `statefulApi()`, Sanctum's `EnsureFrontendRequestsAreStateful` sees the trusted origin and runs the cookie, session and CSRF middleware for this request (`vendor/laravel/sanctum/src/Http/Middleware/EnsureFrontendRequestsAreStateful.php:48-56`). A missing or stale CSRF token ends here with **419 `SESSION_EXPIRED`** (F03).
4. `LoginRequest` validates and normalises (`LoginRequest.php:16-29`): `username` is trimmed and lower-cased **before** validation (`prepareForValidation`), then `required|string|min:3|max:50`; `password` is `required|string|max:255`. A failure is **422 `VALIDATION_FAILED`** with `errors` per field. Nothing is audited or throttled for 422.
5. `AuthController::login` (`AuthController.php:30-47`) first checks `$request->hasSession()`. A request from an origin that is not stateful has no session, so it answers **400 `BAD_REQUEST`** at once: no throttle hit, no audit row (`WebSignInTest` "answers 400 BAD_REQUEST, without throttling or audit...").
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

1. `GET /api/v1/auth/me` (`routes/api.php:20`) is inside `port.api`: `auth:web` first (no session gives **401 `UNAUTHENTICATED`**), then `EnsureAccountUsable` (F08: a deactivated user gets 403 and is signed out; `must_change_password` does not block this route), then the two hook middleware (pass-through today).
2. `AuthController::me` (`:53-56`) reloads the relation and returns the same resource as login. `ProfileTest` proves the body is `assertExactJson` equal to the sign-in answer.

### 2.3 Logout

1. `POST /api/v1/auth/logout` (`routes/api.php:22`) is inside `port.api`, so it needs a session and a CSRF token (419 without) and stays open while a password change is pending.
2. `AuthController::logout` (`:78-88`): `Auth::guard('web')->logout()`, then, if there is a session, `invalidate()` (drops all data, new id) and `regenerateToken()` (new CSRF token), then 204 with no body. The next signed-in request is 401 (`PasswordChangeTest` "ends the session, so the next request is 401").

## 3. Every piece of code

### 3.1 Routes (`routes/api.php`)

| Line | Route | Name | Middleware (from `route:list`) |
| --- | --- | --- | --- |
| 17 | `POST auth/login` → `AuthController@login` | `auth.login` | `api` only |
| 20 | `GET auth/me` → `@me` | `auth.me` | `api`, `port.api` |
| 21 | `POST auth/change-password` → `@changePassword` (F08) | `auth.change-password` | `api`, `port.api`, `throttle:5,1` |
| 22 | `POST auth/logout` → `@logout` | `auth.logout` | `api`, `port.api` |
| 24 | `GET audit-logs` → `AuditLogController@index` (F10) | `audit-logs.index` | `api`, `port.api`, `ability:audit.read` |

The `/api/v1` prefix comes from `bootstrap/app.php:57` (`apiPrefix`). `port.api` is the group `auth:web`, `EnsureAccountUsable`, `EnsureMinAppVersion`, `EnsureLicenseAllowsWrite` (`bootstrap/app.php:69-74`, F11). The route **names** matter: `EnsureAccountUsable` and `RouteAbilityCoverageTest` use them.

### 3.2 `AuthController` (`app/Modules/Access/Http/Controllers/AuthController.php`)

Constructor takes `SignInService` (injected). Methods:

| Method | Input | Output | Notes |
| --- | --- | --- | --- |
| `login(LoginRequest)` | validated body, request IP, `"POST /api/v1/auth/login"` (built from `method()` and `path()`) | `UserProfileResource` (200) | Throws `ApiProblemException` 400 without a session. Passes the IP and the route string to the service so the audit row can name them. |
| `me(Request)` | session user | `UserProfileResource` | `$request->user()` is non-null because the route is behind `auth:web`. |
| `logout(Request)` | session | 204 `Response` | Guards `hasSession()` because a stateless call cannot reach here anyway (the route needs `auth:web`), but the check keeps the method safe if reused. |
| `changePassword(ChangePasswordRequest, PasswordChangeService)` | | | **F08.** |

Why `Auth::guard('web')->login()` and not `attempt()`: the service already checked throttle, password and `is_active` in the order the lead wanted, so the controller only starts the session (the service's own docblock: "starting the session or issuing a token is the caller's job", reusable by mobile).

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

Note: `Notifiable` is used but there is no email column, so the trait is inert.

### 3.7 `UserRoleAssignment`, `OrganizationUnit`, `UserDevice`

| Model | Table | Key facts |
| --- | --- | --- |
| `UserRoleAssignment` | `user_role_assignments` | `timestamps: false`. Fillable `user_id, role, organization_unit_id, unit_type, active_from, active_to`. Casts `role => Role`, `unit_type => UnitType`, dates. Relations `user()`, `organizationUnit()`. Never deleted: ending a role sets `active_to` (DEC-36). `active_assignment_key` is a generated column (`IF(active_to IS NULL,1,NULL)`) and is never written. |
| `OrganizationUnit` | `organization_units` | `timestamps: false`. Fillable `unit_type, terminal_id`. Cast `unit_type => UnitType`. Relation `roleAssignments()`. `terminal_id` is a plain column (MasterData is a later layer, so Access cannot declare the relation; `Terminal::organizationUnit()` links back). `single_key` is generated, never written. |
| `UserDevice` | `user_devices` | `timestamps: false`. Fillable `user_id, device_id, platform, app_version, last_seen_at, revoked_at, push_token, push_token_updated_at`; `push_token` hidden; casts `platform => DevicePlatform`. **Not used by web sign-in**; P1-C3 will use it for mobile. Never deleted: `revoked_at` is set. |

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
| `bootstrap/app.php:60` `statefulApi()` | on | Sanctum SPA cookie auth for the `api` group |
| `bootstrap/app.php:94-96` `redirectGuestsTo` | `null` for `api/*` | a guest on the API gets 401 JSON, not a redirect to a non-existent `login` route |
| `config/sanctum.php` `stateful` | from `SANCTUM_STATEFUL_DOMAINS` (`.env.example`: `localhost:5173,atlantech.test`) | only these origins get a session |
| `config/sanctum.php` `guard` | `['web']` | |
| `config/sanctum.php` `middleware.authenticate_session` | `AuthenticateSession::class` | **active**; see F08 section 8 (it ends other sessions when the hash changes) |
| `config/session.php` | driver `database`, lifetime 120 min, cookie `atlantech_session`, `same_site lax`, `http_only true` | idle timeout chosen by the lead (Q1; the requirement NFR-SEC-3 says 15 min, a recorded deviation) |
| `config/cors.php` | `supports_credentials true`, paths `api/*` and `sanctum/csrf-cookie` | |
| `lang/en/errors.php` `auth.invalid_credentials` | "The username or password is incorrect." | detail of the 401 |

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
| `auth/me` uses `auth:web`, not `auth:sanctum` | a Bearer token sent to `auth:sanctum` would query the missing `personal_access_tokens` table and answer 500; with `auth:web` it is a clean 401. P1-C3 moves it to `auth:sanctum` with the table. | P1-C1 review 3 |
| Service is separate from the controller | P1-C3 (mobile) reuses the same checks and only changes what it does after success | P1-C1 section 6 |
| `Password` check uses the provider, not `Auth::attempt()` | the controller wants to decide the order (throttle, password, active flag) and to start the session itself; a side effect is that an outdated hash is **not** rehashed (harmless while there is one hashing setting; review note 6) | P1-C1 review 1 note 6 |

## 5. How it connects

- **Calls:** `SignInService` → `AuditService` (F10), `RateLimiter`, `Hash`, the `web` guard provider. `AuthController` → `UserProfileResource` → `User::accessScope()` → `AccessScope` (F09). Errors via `ApiProblemException` and `ErrorCode` (F03).
- **Called by:** the Vue sign-in page (FE-025); `EnsureAccountUsable` (F08) and `ChangePasswordRequest` reuse `SignInService::recordRejectedAttempt`; every later signed-in route relies on the session this feature starts.
- **Module layer:** this is all in `Access` (layer 2), which may use only `Shared`. That is why `OrganizationUnit.terminal_id` is a plain column and the `Terminal` link lives on the MasterData side (A4).
- **Depends on:** F01 (session, Sanctum, CORS config), F03 (problem responses, 419/429), F05/F06 (tables, models), F10 (audit).
- **Used by:** F08, F09, F11 (the `port.api` group begins with `auth:web`), P1-C3 (mobile), P2-E1 (user admin).

## 6. Data

| Table | Access | Notes |
| --- | --- | --- |
| `users` | read | `username` unique, check `chk_user_username_lower` (lower-case and at least 3 characters), `password_hash`, `is_active`, `must_change_password`, `updated_at DATETIME(6)` (version token) |
| `user_role_assignments` | read | `role` ENUM of the 12 roles, `unit_type`, `active_to` NULL means active, `uq_ura_active`, `chk_ura_role_matches_unit` (the role must fit the unit type), `chk_ura_period` |
| `organization_units` | read | `unit_type`, `terminal_id`, `chk_org_unit_terminal` (`TERMINAL` iff `terminal_id` set), unique `(unit_type, single_key)` |
| `sessions` | write (framework) | web sessions, database driver |
| `audit_logs` | insert (via F10) | only refusals 401/403 |
| `user_devices` | not touched | mobile, P1-C3 |

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
| "answers 401, not 500, to a Bearer token while mobile tokens do not exist yet" | the reason for `auth:web` |

Result of the run for this guide: the Access test folder, the Audit folder, `RolePermissionsTest`, `RouteAbilityCoverageTest` and `PublishRoleMatrixTest` together: **156 passed, 1685 assertions, 41 s** on the empty database `atlantech_testing_g3`.

## 8. Gotchas and invariants

- **A request without a stateful `Origin`/`Referer` has no session.** curl and Postman get 400 on login unless they send `Origin: http://localhost:5173` and a matching `Referer`, and a CSRF token. Use the browser or send those headers (section 10).
- **Dev origin rule (browser):** the session cookie is `SameSite=Lax`, so the SPA and the API must be **same-site** (for example `localhost:5173` talking to `localhost:8000`, or a Vite proxy), not `localhost:5173` to `atlantech.test` (review 3 note; not tested in a browser).
- **Never audit or echo the password.** The audit `username` is the raw (lower-cased) input; if someone types a password into the username field it is stored there, in `after_state.username`. Only `audit.read` (Port Admin) can read it (P2-D3 review note).
- **Keep 401 and the throttle identical for unknown and wrong.** Any new branch before `refuse()` that answers differently for unknown users re-opens user enumeration.
- **The throttle keys use `$request->ip()`.** Behind a reverse proxy every client has the proxy's IP unless trusted proxies are set, so the 20-per-minute IP limit would apply to the whole port. Unverified: whether the deployment sets trusted proxies (review 1 note 5; a deployment task, P6).
- **`User::accessScope()` is cached per instance.** A role changed in the same request is not seen. Reload the user.
- **The session regenerates on login, change-password and (invalidates) on logout.** Do not store anything in the session that must survive those.
- **Do not add a route outside `port.api` that needs a user.** `RouteAbilityCoverageTest` (F09) only allow-lists the four auth route names.
- **Spec drift test:** the cookie name in `openapi/port-api.yaml` (`cookieAuth.name`) must equal `config('session.cookie')`; `OpenApiDriftTest` fails otherwise (F04).
- **Test helper names are global** (`signIn()`, `userWithRole()`, `rejectedAttempts()`, constant `SPA_ORIGIN` in `WebSignInTest`): a new test file must not reuse them.

## 9. How to extend it

**Change the throttle numbers.** Edit the three constants in `SignInService`; tests read the constants, so they follow. Record the lead's decision in the PR.

**Add a field to the profile.** (1) Add it to `UserProfileResource::toArray` and its `@return` shape. (2) Add it to the `UserProfile` schema in `openapi/port-api.yaml` and bump the minor version (F04). (3) Update `ProfileTest` and `WebSignInTest` expectations (`assertExactJson` will fail until you do). (4) Tell Frontend (CT-03 `auth-web.md`).

**Add mobile sign-in (P1-C3).** Reuse `SignInService::authenticate` unchanged, then issue a token instead of calling `Auth::guard('web')->login`. Add the `personal_access_tokens` migration with `revoked_at` and move `auth/me` to `auth:sanctum`.

**Add a new signed-in route.** Put it inside `Route::middleware('port.api')`, give it `->middleware('ability:<name>')` (F09). Never put it outside the group.

**Add a new refusal reason to sign-in.** Call `$this->refuse($usernameKey, $ipKey, ErrorCode::X, 'reason_snake_case', $username, $route)`; it counts, audits and throws in one step. Add a test that asserts the audit row.

## 10. Try it

Commands that were run for this guide (PowerShell, from `project-backend/ATLANTECH`, `php` is Herd's):

```bash
php artisan route:list --except-vendor
```
Real output: `GET|HEAD /`, `GET|HEAD api/v1/audit-logs`, `POST api/v1/auth/change-password`, `POST api/v1/auth/login`, `POST api/v1/auth/logout`, `GET|HEAD api/v1/auth/me` ("Showing [6] routes").

```bash
node docs/reference/lookup.mjs DEC-35
```
Real output: `DEC-35 · decision · Sign-in identifier`, `status: Closed`, affects `P1-C1` and `P1-C3`, unblocks `CT-03` and `CT-04`.

```bash
$env:DB_DATABASE='atlantech_testing_g3'; $env:DB_SCHEMA_TEST_DATABASE='atlantech_testing_g3'
vendor/bin/pest tests/Feature/Access/WebSignInTest.php tests/Feature/Access/ProfileTest.php
```
Real result: part of the 156-test run above (all passed). The first run on an empty database takes about 40 seconds because `migrate:fresh` builds the schema.

**Expected, not run** (needs the server profile and seeded demo users, and I did not touch the `atlantech` database): with `docker compose --profile server up -d --build`, then in a shell:

```bash
curl -i -c jar -H "Origin: http://localhost:5173" -H "Referer: http://localhost:5173/" http://localhost:8000/sanctum/csrf-cookie
curl -i -b jar -c jar -H "Origin: http://localhost:5173" -H "Referer: http://localhost:5173/" -H "X-XSRF-TOKEN: <decoded XSRF-TOKEN cookie value>" -H "Content-Type: application/json" -d '{"username":"staff.ahmed","password":"wrong"}' http://localhost:8000/api/v1/auth/login
```
Expected: 204 with an `XSRF-TOKEN` cookie, then `401` with `Content-Type: application/problem+json`, `"code":"UNAUTHENTICATED"`, and one new `REJECTED_ATTEMPT` row. A sixth wrong attempt within a minute gives `429` with `Retry-After`. The CSRF header value is the URL-decoded cookie.

## 11. Open points

- **Doc versus code (session after password change):** see F08 section 11. `P1-C2` says other sessions stay open; the code ends them.
- **`SignInService::recordRejectedAttempt`** is a leftover delegator (P2-D3 Q2: delete it when the callers use `AuditService` directly). `EnsureAccountUsable` and `ChangePasswordRequest` still call it.
- **Trusted proxies / real client IP** for the throttle: Unverified; check `bootstrap/app.php` (`trustProxies` is not set) and the deployment (P6).
- **Password rehash** on login is not done (review note 6).
- **Mobile:** `UserDevice` is unused; the 401-for-Bearer behaviour is temporary (P1-C3, P1-C4).
- **`oas3-unused-component`** Spectral rule is switched off with the lead's OK until shared components are used (F04).
- **`Notifiable` on `User`** has no use (no email); harmless.
- **`terminal_ids` is derived from the unit type alone**, not from the role: a port-wide role placed in a `TERMINAL` unit would show a terminal id but `AccessScope::isTerminalScoped()` would be false. The database check `chk_ura_role_matches_unit` makes that combination impossible, so it is a guard, not a bug.
