# F08 · Password-change gate and account state

Task origin: P1-C2 (Sprint 1, backend PR #38, PortHub PR #24), which also added the web logout; extended by the mobile stack (P1-C3 PR #55, P1-C4 logout PR #56, review fixes PR #57: Bearer tokens on these routes, token revocation, decision D-A). Facts re-checked against `develop` at `9559d35` (first written at `106b40e`). Paths are inside `project-backend/ATLANTECH/` unless stated. Sign-in itself is F07.

## 1. What it does

Two rules that apply to **every signed-in route**, enforced by one middleware, `EnsureAccountUsable`, right after authentication:

1. **A deactivated user is refused and signed out on the very next request** (FR-AC-10). Answer: 403 `ACCOUNT_DISABLED`. On the web the session is ended; on mobile the **token is revoked**. The request after that is 401.
2. **A user with `must_change_password = true` can do only three things** until the password is changed (FR-AC-03): read their profile (`GET auth/me`), change the password (`POST auth/change-password`) and sign out (`POST auth/logout`). Every other route answers 403 `PASSWORD_CHANGE_REQUIRED`.

It also provides the endpoint that clears the flag: `POST /api/v1/auth/change-password`, which saves the new password, clears `must_change_password`, writes the audit row in the same transaction, and answers 200 with the profile and a **new session id**. It works with a web session **or** a mobile Bearer token. A password change also **closes the user's other sessions** (decision D-A, 2026-11-05): other web sessions end by themselves, and the user's other mobile tokens are revoked. The session or token that made the change stays.

It does **not**: reset a forgotten password (P2-E1), notify anyone, force a change after a time, or check password history or a breached-password list. It also does not decide who may *deactivate* a user (the user-admin endpoints, P2-E1, are not built yet; today `is_active` changes only in the database or in seeders), and it does not revoke a *device* (that is P2-E3; the device check is in `EnsureTokenDevice`, F07).

## 2. Request or data flow

### 2.1 Every signed-in request (the gate)

Order inside the `port.api` group and the priority list (`bootstrap/app.php`): `auth:sanctum` (session or Bearer token) → (`throttle`, if the route has one) → `EnsureTokenDevice` (Bearer only, F07) → `EnsureAccountUsable` → `ability:` → `terminal.scope` → `EnsureMinAppVersion` → the rest (F11). Inside `EnsureAccountUsable::handle`:

1. `$request->user()` is read. If it is not an `Access\Models\User` (a guest on a route that did not need auth) the middleware passes the request on. A mobile principal **is** a `User`; its token is read with `$user->currentAccessToken()`.
2. **Check 1, active?** If `! $user->is_active`: audit a `REJECTED_ATTEMPT` (`account_disabled`), then `signOut`: if the request carries a mobile token (`PersonalAccessToken`) that token is **revoked**; otherwise the web session ends (`Auth::guard('web')->logout()`, `session()->invalidate()`, `regenerateToken()`). Then throw `ApiProblemException(ErrorCode::AccountDisabled)` → **403**.
3. **Check 2, password pending?** If `must_change_password` and the route is not one of the allow-listed route **names**: audit a `REJECTED_ATTEMPT` (`password_change_required`), throw `ApiProblemException(ErrorCode::PasswordChangeRequired)` → **403**. The session or token stays valid (the user must still be able to change the password).
4. Otherwise `$next($request)`.

The order of the two checks matters: a user who is both deactivated and flagged gets `ACCOUNT_DISABLED` (and is signed out), not `PASSWORD_CHANGE_REQUIRED`. The role check (F09) comes **after** the gate, so a flagged user without the ability sees `PASSWORD_CHANGE_REQUIRED` first (`AbilityMiddlewareTest` "answers the password gate first").

### 2.2 `POST /api/v1/auth/change-password`

Route: `routes/api.php:28`, name `auth.change-password`, middleware `port.api` and `throttle:5,1`.

1. `auth:sanctum` → 401 without a session or a valid token. On the web, the CSRF check (Sanctum stateful pipeline) → 419 `SESSION_EXPIRED` without a valid token. With a Bearer token the request must carry `X-Device-Id` and `X-App-Version` (`EnsureTokenDevice`, F07: 422 / 401), and the CSRF step does not apply (stateless).
2. `ThrottleRequests` (5 per minute, keyed per signed-in user; a 6th request in the minute answers **429 `TOO_MANY_ATTEMPTS`** with `Retry-After`). It counts every request, valid or not, so a stolen open session cannot guess the current password quickly.
3. `EnsureAccountUsable` (2.1). A flagged user passes because this route name is on the allow-list.
4. `ChangePasswordRequest::rules()` (3.2) validates; `current_password` is checked against the `sanctum` guard, which is the session user or the token user. A failure is **422 `VALIDATION_FAILED`** with the Laravel rule keys under `errors` (for example `validation.current_password`).
5. If the failure includes `current_password`, `failedValidation` first writes an audit row `wrong_current_password` (3.2), then lets the normal 422 happen.
6. `AuthController::changePassword` reads `$user->currentAccessToken()` and calls `PasswordChangeService::change($user, $newPassword, $token instanceof PersonalAccessToken ? $token : null)`: the current mobile token, or null for a web session.
7. The service opens an audit transaction, saves `password_hash` (plain text; the model's `hashed` cast hashes it) and `must_change_password = false`, sets `revoked_at = now` on **every other live mobile token of the user** (all of them for a web change; all but the current one for a change from a tablet), and writes one `UPDATE` audit row, then commits.
8. If the request has a session, the controller calls `$request->session()->regenerate()` (new session id; a Bearer request has none) and returns `UserProfileResource` (200) with `must_change_password: false`.

### 2.3 What happens to other sessions after a change (rule D-A)

**Decision D-A (Marwan, 2026-11-05):** a password change closes the user's other sessions, on the web and on mobile, and keeps the one that made the change. This replaces the earlier plan text of P1-C2 ("they stay open", 2026-10-31); `contracts/auth-web.md` v1.1 and CT-04 v1.2 say the new rule.

- **Other web sessions** end by themselves. `config/sanctum.php` sets `middleware.authenticate_session` to `Laravel\Sanctum\Http\Middleware\AuthenticateSession`, and Sanctum's stateful pipeline runs it on every stateful request (`vendor/laravel/sanctum/src/Http/Middleware/EnsureFrontendRequestsAreStateful.php:55`). It stores a hash of the user's password hash in the session (`password_hash_web`) and, on a later request, logs the session out if it differs from the user's current `password_hash`. The session that made the change stays signed in because the middleware stores the new value after that request. Test: `PasswordChangeTest` "closes the user's other web sessions and keeps the one that changed the password (D-A)" (it replays the old `password_hash_web` and expects 401). I also confirmed the mechanism earlier with a throw-away probe (401 after the hash changed). Not run against the `database` session driver or two real browsers (Unverified; the test uses the testing driver).
- **Other mobile tokens** are revoked by `PasswordChangeService::change` (3.3): `revoked_at` is set on every live token of the user except the current one; **rows stay** (FR-GL-03). Those tablets get 401 on their next request and must sign in again. Tokens of other users are untouched. Tests: `PasswordChangeTest` "revokes every mobile token of the user after a change from the web, keeps the rows, and leaves other users alone"; `MobileTokenTest` "revokes the other tokens of the user after a password change from a tablet, and keeps the current one and every row" and "applies the password gate to the token, and changes the password with it; the other token is revoked (D-A)".
- **The session or token that made the change stays valid**, whichever channel it came from (a web change revokes all tokens; a tablet change keeps its own token and does not touch any web session except through the hash rule, which also ends other web sessions).

## 3. Every piece of code

### 3.1 `EnsureAccountUsable` (`app/Modules/Access/Http/Middleware/EnsureAccountUsable.php`)

| Member | Detail |
| --- | --- |
| Constructor | `SignInService $signIn` (only to reuse `recordRejectedAttempt`) |
| `ALLOWED_DURING_PASSWORD_CHANGE` | `['auth.me', 'auth.change-password', 'auth.logout']`, matched with `$request->routeIs(...)` on route **names**. A new route is therefore blocked while a change is pending unless its name is added here (do that only for the user's own account actions). |
| `handle(Request, Closure): Response` | 2.1 above. Throws `ApiProblemException` 403 `ACCOUNT_DISABLED` or `PASSWORD_CHANGE_REQUIRED`. Works the same for a web session and a mobile token. |
| `audit(Request, User, ErrorCode, string $reason)` (private) | `$this->signIn->recordRejectedAttempt($request->method().' /'.$request->path(), $code, $reason, $user->username, $user)`; so the audit row has the user as **actor and entity** (`actor_id = entity_id = user id`, `entity_type = 'users'`) and `after_state = {route, status, code, reason, username}`. |
| `signOut(Request, User)` (private) | If `$user->currentAccessToken()` is a `PersonalAccessToken`: `$token->revoke()` (the web session guard is not touched). Otherwise the web path: `Auth::guard('web')->logout()`, then if there is a session `invalidate()` and `regenerateToken()`. Replaced the earlier `endSession`. |

Registered in the `port.api` group and in the priority list after `AuthenticatesSessions`, `EnsureTokenDevice` and `EnsureMobileHeaders` (`bootstrap/app.php`), so it runs after `auth:sanctum`, the throttle and the device check, but before `ability`, `terminal.scope`, the version hooks and route binding, whatever order a route lists them (F11). Consequence for mobile: a token with a wrong or revoked device is refused (401) **before** the password gate and the account check.

### 3.2 `ChangePasswordRequest` (`app/Modules/Access/Http/Requests/ChangePasswordRequest.php`)

Body fields: `current_password`, `new_password`, `new_password_confirmation` (the last is required by the `confirmed` rule, it is not listed itself).

| Field | Rules | Meaning |
| --- | --- | --- |
| `current_password` | `required`, `string`, `current_password:sanctum` | must match the signed-in user's password on the `sanctum` guard, so it works for a web session and for a Bearer token (rule key `validation.current_password` when wrong). It was `:web` until the mobile stack. |
| `new_password` | `required`, `string`, `max:255`, `confirmed`, `different:current_password`, `Password::min(8)->letters()->numbers()` | at least 8 characters with at least one letter and one number; equal to `new_password_confirmation`; not equal to the current value as sent |

`failedValidation(Validator)` (`:30-43`): if the failed fields include `current_password` and there is a signed-in user, it calls `SignInService::recordRejectedAttempt(route, ErrorCode::ValidationFailed, 'wrong_current_password', username, user)` and then `parent::failedValidation`. Reason: a wrong current password from an open session may mean someone is guessing, so it is audited (status 422, code `VALIDATION_FAILED`). A weak or unconfirmed new password is a plain 422 and is **not** audited (`PasswordChangeTest` "does not record a weak new password"). The password text never appears in the row (test asserts that).

There is no `authorize()` override (default allows): authentication is the route middleware.

### 3.3 `PasswordChangeService` (`app/Modules/Access/Services/PasswordChangeService.php`)

`change(User $user, string $newPassword, ?PersonalAccessToken $currentToken = null): void`. `$currentToken` is the mobile token of the request, kept alive; `null` for a web session. Inside `AuditService::transaction()`:

1. remember `$wasRequired = $user->must_change_password`;
2. `$user->forceFill(['password_hash' => $newPassword, 'must_change_password' => false])->save()`. `forceFill` is used because the columns are fillable, but the intent is explicit; the `hashed` cast on `User` hashes the plain string. `updated_at` (the version token) moves too;
3. `PersonalAccessToken::query()->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->getKey())->whereNull('revoked_at')->when($currentToken !== null, fn ($q) => $q->whereKeyNot($currentToken->getKey()))->update(['revoked_at' => now()])`: revoke every other live token of this user in the same transaction;
4. `$this->audit->record(AuditLogAction::Update, $user->getTable(), $user->id, $user->id, ['must_change_password' => $wasRequired], ['must_change_password' => false, 'password_changed' => true])`.

The password, the flag, the token revocation and the audit row commit together or not at all (Coding Notes §6.1). Token rows are updated, never deleted (FR-GL-03). The audit row does not list the revoked tokens (it records only the flag and `password_changed`). The service writes its **own** audit row rather than `recordUpdated()` because `recordUpdated` strips password columns, which would leave an empty before/after for a password-only change (P2-D3 review note); `password_changed: true` is a marker, not a value. The actor is the user themself (no admin reset exists yet).

### 3.4 `AuthController::changePassword`

See 2.2 steps 6-8. It receives `PasswordChangeService` by method injection, passes the current mobile token (or null), calls `session()->regenerate()` only if the request has a session, and builds the profile from `$user->load('activeRoleAssignment.organizationUnit')`.

### 3.5 Error codes (catalogue in F03)

| `ErrorCode` | Status | `message_key` / detail |
| --- | --- | --- |
| `AccountDisabled` | 403 | `errors.account_disabled` · "Your account has been disabled. Contact your port administrator." |
| `PasswordChangeRequired` | 403 | `errors.password_change_required` · "You must change your password before you continue." |
| `TooManyAttempts` | 429 | `errors.too_many_attempts` (also from the `throttle:5,1` route middleware, mapped by `ProblemRenderer`) |
| `ValidationFailed` | 422 | the change-password rule keys |
| `SessionExpired` | 419 | CSRF mismatch |

### 3.6 Logout (shares the allow-list)

`AuthController::logout` is described in F07 2.3: a Bearer token is revoked (`revoke()`, the row stays, other devices keep theirs); otherwise the web session is ended. It is on the allow-list so a flagged user can leave, from the web or from a tablet (`MobileLogoutTest` "lets a user who must change the password log out with the token").

## 4. Why this design

| Choice | Reason | Source |
| --- | --- | --- |
| One middleware for both rules, right after authentication (`auth:sanctum`) and the device check | FR-GL-01 puts the password gate second in the write chain, before roles; a deactivated user must be caught on every route, not only on sign-in | FR-AC-03, FR-AC-10; P1-C2 section 3 |
| `is_active` checked before `must_change_password` | a disabled account should not get a "go change your password" hint, and should be signed out | P1-C2 §3 behaviour |
| `auth/me` allowed during the gate | the SPA needs the flag after a page reload to know it must show the change-password screen; it only reads the user's own data | P1-C2 Q2, §7 conflict (lead, 2026-10-30) |
| Allow-list by route name, three names | short and explicit; Coding Notes §6.1 allowed only change password and logout, `me` was added by the lead | P1-C2 |
| Rules: 8+ characters, letters and numbers, differs from current | lead's answer; `Password::min(8)->letters()->numbers()` | P1-C2 Q1 |
| Body has `new_password_confirmation` | lead's answer; Laravel's `confirmed` rule | P1-C2 Q3 |
| Throttle 5 per minute per user on the route | an attacker with an open session could otherwise guess the current password | P1-C2 §3; CT-01 widened to "sign-in and password change" (v1.2) |
| Refusals audited with the user as actor | FR-GL-02 says every refused attempt is audited; review 1 of P1-C2 found the first version did not | P1-C2 review, finding 1 |
| Wrong current password audited, weak new password not | the first can mean guessing; the second is an ordinary typo | P1-C2 review |
| Save and audit in one transaction | Coding Notes §6.1; FR-GL-02 | `PasswordChangeService` docblock |
| New session id after a change | CT-03 says sign-in and password change both give a new id; reduces the value of a stolen id | CT-03 `auth-web.md` |
| A password change closes the user's other sessions (web and mobile) and keeps the current one | a changed password should end a possibly stolen session; tokens must behave like the web, where `AuthenticateSession` already ends other sessions. This reverses the 2026-10-31 plan text ("stay open"). | lead's decision D-A, 2026-11-05; `auth-web.md` v1.1; CT-04 v1.2 |
| A deactivated user's mobile token is revoked, not just refused | same effect as ending the web session; the token cannot work again if the user is re-enabled | developer, 2026-11-05 (`MobileTokenTest`) |
| `current_password:sanctum` instead of `:web` | the password check must work for a user who has a token and no session | P1-C3 review (PR #57) |
| Logout built here, ahead of Sprint 2; the mobile token revocation came with P1-C4 | the lead asked for the web part early (2026-10-30); P1-C4 added the Bearer branch | P1-C2 §9; P1-C4 (PR #56) |

## 5. How it connects

- **Calls:** `SignInService::recordRejectedAttempt` → `AuditService::rejectedAttempt` (F10). `PasswordChangeService` → `AuditService::transaction`/`record`. `UserProfileResource` (F07).
- **Called by:** every signed-in route through the `port.api` group (F11). `ChangePasswordRequest` and `AuthController` for the endpoint.
- **Depends on:** F07 (session, profile), F10 (audit), F03 (errors, 429/419).
- **Used by:** F09 (the ability check comes after this gate), F11 (the gate is step 2 of the write chain), Frontend FE-025 (change-password screen) and FE-021 (guards), via `contracts/auth-web.md`.
- **Module layer:** all in Access; it uses only Shared (`AuditService`, `ErrorCode`, `ApiProblemException`).
- **Mobile:** the same middleware serves Bearer tokens since P1-C3 (`signOut` revokes the token; `current_password:sanctum`). The device check (`EnsureTokenDevice`, F07) runs just before it, so a wrong-device token never reaches the gate.

## 6. Data

| Table | Access | Notes |
| --- | --- | --- |
| `users` | read `is_active`, `must_change_password`; write `password_hash`, `must_change_password`, `updated_at` | `must_change_password`: "1 = one-time password in use; must change before any other action". `updated_at DATETIME(6)` with `ON UPDATE` is the version token (F12). |
| `audit_logs` | insert | `UPDATE` row for the change; `REJECTED_ATTEMPT` rows for refusals (F10) |
| `sessions` | write (framework) | regenerated, invalidated |
| `personal_access_tokens` | update `revoked_at` | other live tokens of the user at a password change; the token of a deactivated user at its next request; the token at logout. Rows are never deleted. |
| cache store | write | throttle counters for `throttle:5,1` |

No migration. Who sets `must_change_password = true`: nothing in the web API yet. `DemoPeopleSeeder` sets it to `true` for the demo users it creates (`database/seeders/DemoPeopleSeeder.php:70`, F13), so a freshly seeded demo user must change the shared demo password at first sign-in. The user-creation endpoint of P2-E1 (a "one-time password") will set it too.

## 7. Tests

`tests/Feature/Access/PasswordChangeTest.php` (run: `vendor/bin/pest tests/Feature/Access/PasswordChangeTest.php`). It registers a stand-in route `GET /api/v1/_gate` with `['api', 'auth:web', EnsureAccountUsable::class]` to stand for "any other signed-in route".

| Group / test | Proves |
| --- | --- |
| the gate: "refuses other routes with 403 PASSWORD_CHANGE_REQUIRED while the flag is set" | code and `message_key` |
| "still answers auth/me while the flag is set" | allow-list, flag visible |
| "lets a user without the flag through" | |
| "opens the other routes after the password is changed" | flag cleared |
| a deactivated user: "is refused with 403 ACCOUNT_DISABLED on every signed-in route" (2 paths) | |
| "is signed out, so the next request is 401" | the session really ends |
| audit: "records the gate refusal with its actor, route and code" | `actor_id = entity_id = user id`, route, status 403, code, reason |
| "records a deactivated user" / "records a wrong current password, never the password itself" / "does not record a weak new password" / "does not record what the gate allows" | what is and is not audited |
| 419: "answers 419 SESSION_EXPIRED" for change-password and logout | CSRF is enforced |
| logout: "ends the session, so the next request is 401" / "stays open while the password change is pending" / "answers 401 without a session" | |
| change: "saves the password, clears the flag and answers with the profile" | `Hash::check` of the new value, flag false |
| "writes one audit row without the password" | `UPDATE`, actor = user, `before_state = {must_change_password: true}`, `after_state = {must_change_password: false, password_changed: true}` |
| "gives a new session id" | |
| "refuses a bad body with 422 and the rule key" (5 datasets: wrong current, none, not confirmed, same as current, weak) | rule keys `validation.current_password`, `validation.required`, `validation.confirmed`, `validation.different`, `validation.password`; the flag stays set |
| "answers 401 without a session" | |
| "throttles guessing with 429 and Retry-After" | 5 wrong attempts then 429 |

Added with the mobile stack (run with `PasswordChangeTest` and `MobileTokenTest`):

| Test | Proves |
| --- | --- |
| `PasswordChangeTest` "closes the user's other web sessions and keeps the one that changed the password (D-A)" | the web rule |
| `PasswordChangeTest` "revokes every mobile token of the user after a change from the web, keeps the rows, and leaves other users alone" | 3 token rows, the user's 2 revoked, the other user's 1 live |
| `MobileTokenTest` "applies the password gate to the token, and changes the password with it; the other token is revoked (D-A)" | gate on a Bearer request (`audit-logs` gives `PASSWORD_CHANGE_REQUIRED`), change with the token, the current token still works, the other device's token gives 401 |
| `MobileTokenTest` "refuses a wrong current password on the token route with 422" | `current_password:sanctum` |
| `MobileTokenTest` "answers 403 ACCOUNT_DISABLED to a deactivated user and revokes the token" | then 401 even after the user is re-enabled |
| `MobileTokenTest` "revokes the other tokens of the user after a password change from a tablet, and keeps the current one and every row" | 3 rows kept, `tablet-a` live, `tablet-b` revoked, another user's `tablet-c` live |
| `MobileLogoutTest` (3 cases) | token logout (also for a flagged user) |

`AbilityMiddlewareTest` "answers the password gate first" proves the order against F09.

## 8. Gotchas and invariants

- **Every new signed-in route must sit inside `port.api`**, otherwise it skips this gate (and the deactivated-user check). `RouteAbilityCoverageTest` enforces this only for routes that declare an ability (F09).
- **New account-self routes need their name on the allow-list**, or a flagged user can never reach them (and the SPA will show a dead end). Only user-own actions belong there.
- **The allow-list is by route name, not path.** Renaming `auth.me` silently locks flagged users out of it.
- **Deactivation takes effect on the next request, not instantly.** The user keeps a valid cookie until then. Each request of an already-deactivated user writes a `REJECTED_ATTEMPT` until the first one signs them out (only one, because the session is invalidated).
- **The throttle counts all requests** to change-password, not only failures. A legitimate user who retries more than 5 times in a minute gets 429.
- **`different:current_password` compares the submitted strings**, not hashes; it works only when `current_password` is valid too.
- **Never write the password to the audit log.** `PasswordChangeService` writes a boolean marker; keep it that way. `AuditService::state()` also drops `password` and `password_hash` from any model state (F10).
- **The hashing is the model's cast.** Do not call `Hash::make` before assigning `password_hash`, or it will be hashed twice.
- **`User::accessScope()` memoises** per instance. After a change the profile is rebuilt from `$user->load(...)`, which is fine; role changes are not part of this feature.
- **Other web sessions** are ended by the Sanctum `AuthenticateSession` middleware, not by this code (2.3). If someone removes `authenticate_session` from `config/sanctum.php`, that effect disappears and `PasswordChangeTest` "closes the user's other web sessions..." fails. **Other mobile tokens** are revoked by `PasswordChangeService`; keep that query inside the same transaction.
- **A web change revokes all of the user's tokens** (there is no current token to keep); a tablet change keeps the tablet's token. Do not "fix" this asymmetry.

## 9. How to extend it

**Allow one more route during a pending change.** Add its route name to `ALLOWED_DURING_PASSWORD_CHANGE` and add a test in `PasswordChangeTest` (a flagged user gets 200, and no audit row). Say why in the PR.

**Change the password policy.** Edit the `Password::min(8)->letters()->numbers()` chain in `ChangePasswordRequest`; update the data set in "refuses a bad body" and `contracts/auth-web.md`. Mixed case, symbols or "uncompromised" are options of `Password::`.

**Add a new account-state rule (for example "licence expired user")**: add a branch in `EnsureAccountUsable::handle` after the two checks, add an `ErrorCode` (F03 section 9), audit with `$this->audit(...)`, add tests, and document the code in `contracts/auth-web.md`.

**Add the admin "reset password" (P2-E1).** Create the user or reset with `must_change_password = true` and a one-time password; the gate then forces the change. Write the audit row in the same transaction through `AuditService` with the admin as actor.

**Add a new refusal audit reason.** Use a short snake_case reason string and call `$this->audit($request, $user, $code, 'your_reason')`.

## 10. Try it

Run for this guide (from `project-backend/ATLANTECH`, own database `atlantech_testing_g3`):

```bash
$env:DB_DATABASE='atlantech_testing_g3'; $env:DB_SCHEMA_TEST_DATABASE='atlantech_testing_g3'
vendor/bin/pest tests/Feature/Access/PasswordChangeTest.php
```
Real result: the file passed as part of the 217-test run on `develop` at `9559d35` (Access and Audit folders, `RolePermissionsTest`, `SystemInfoTest`, `RouteAbilityCoverageTest`, `WriteChainOrderTest`, `PublishRoleMatrixTest`, `TokenPruneNotScheduledTest`): 217 passed, 2017 assertions, 68 s.

```bash
php artisan route:list --path=api/v1/auth --json
```
Real output (shortened): `auth.change-password` has middleware `["api","port.api","Illuminate\\Routing\\Middleware\\ThrottleRequests:5,1"]`; `auth.login` has `["api"]`; `auth.mobile.login` has `EnsureMobileHeaders` and `EnsureMinAppVersion`; `auth.logout` and `auth.me` have `["api","port.api"]`.

The earlier throw-away probe (outside the repository, deleted) showed `401` on `auth/me` after `users.password_hash` changed under an open session; the same behaviour is now covered by `PasswordChangeTest` "closes the user's other web sessions and keeps the one that changed the password (D-A)".

**Expected, not run** (needs a running server and a user with the flag; I did not touch the `atlantech` database): sign in as a user with `must_change_password = 1`, then `GET /api/v1/audit-logs` (the only other route today) returns `403 PASSWORD_CHANGE_REQUIRED`, `GET /api/v1/auth/me` returns 200 with `"must_change_password": true`, `POST /api/v1/auth/change-password` with `{"current_password":"...","new_password":"New-pass-22","new_password_confirmation":"New-pass-22"}` returns 200 with the flag `false` and a new `atlantech_session` cookie, and the audit list (as Port Admin) shows one `UPDATE` row on `users`.

## 11. Open points

- **Resolved:** the earlier finding "other sessions are ended although the plan says they stay open" is now the decided rule D-A (2.3). `docs/tasks/P1-C2.md` still carries the old sentence in its section 1 and review note 3; it is a historical plan file, so trust this guide and `auth-web.md` v1.1.
- **`SignInService::recordRejectedAttempt` delegator** is still used by this feature (P2-D3 Q2 planned its removal).
- **No admin deactivate/reset endpoints yet** (P2-E1); `is_active` and `must_change_password` can only be changed outside the API.
- **Revoked devices:** the device check lives in `EnsureTokenDevice` (F07); the Port Admin endpoint that sets `user_devices.revoked_at` is P2-E3 and not built. CT-04 v1.2 is still "Proposed" until the Mobile Team approves it.
- **Password policy** is minimal (length, letter, number); no history, no expiry.
- **Revoked tokens pile up** (rows are kept on purpose); no retention rule exists yet.
- **Do not add `PASSWORD_CHANGE_REQUIRED` to the OpenAPI responses of `auth/me`, `change-password` or `logout`**: they are on the allow-list, so they cannot answer it.
- **Demo users start flagged** (`DemoPeopleSeeder.php:70`): a Frontend or QA person who signs in with a demo account must first change the password. This is by design but surprises people.
