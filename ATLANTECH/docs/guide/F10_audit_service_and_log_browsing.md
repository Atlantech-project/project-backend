# F10 · Audit service and audit-log browsing

Task origin: P2-D3 (audit service, backend PR #47) and P2-F8 (audit-log browsing, same PR family). Facts re-checked against `develop` at `9559d35` (first written at `106b40e`); the mobile stack (P1-C3, PR #55 and #57) added two audit reasons and a new caller. Paths are inside `project-backend/ATLANTECH/` unless stated.

## 1. What it does

Two things on one table, `audit_logs`:

1. **`AuditService`** is the **one writer** of audit rows (FR-GL-02). A change and its audit row are saved in **one database transaction**; a **refused** request is saved as a `REJECTED_ATTEMPT` row. Only the Backend writes audit rows; the AI workers have no access to the table (DEC-08).
2. **`GET /api/v1/audit-logs`** lets the Port Admin (`PORT_GM`, ability `audit.read`) browse the log: newest first, paged, with filters, with the actor's name and the before/after states. The history of one master-data record is this list filtered by `entity_type` and `entity_id`.

The audit log is **append-only** (NFR-SEC-6): the `AuditLog` model and its query builder refuse update, delete, truncate, increment and friends.

It does **not**: audit reads (a successful GET writes nothing; a refused one is audited by the guards), audit a successful sign-in (F07), store the actor's role (the table has no such column), or enforce append-only at the database level for the application account (section 8).

## 2. Request or data flow

### 2.1 Writing a change with its audit row

Typical use (from the service's docblock, `AuditService.php:20-25`):

1. The caller opens `$audit->transaction(function () { ... })` (a `DB::transaction`).
2. Inside it, the caller writes the business change (for example `Berth::query()->create($data)`).
3. Still inside, the caller calls `$audit->recordCreated($model, $actorId)`, `recordUpdated($model, $actorId)` or `recordDeleted($model, $actorId)`, or `record(...)` directly.
4. `record()` checks `DB::transactionLevel() > 0`, else throws `LogicException` (so an audit row cannot be written "later").
5. It inserts into `audit_logs`; if the audit insert or the change fails, **both** roll back (`AuditServiceTest` "stores neither the change nor the audit row when the write fails" / "...when the audit row cannot be saved").
6. Real example in the code base: `PasswordChangeService::change` (F08).

### 2.2 Writing a refusal (`REJECTED_ATTEMPT`)

1. A guard refuses a request (sign-in 401/403 in F07 on both channels; the mobile device checks in F07; the password gate in F08; `AccessRefusal` 403 in F09; `EnsureRecordVersion` 409 and 422 in F12).
2. The guard calls `$audit->rejectedAttempt($route, $code, $reason, $actorId, $username, $entityType = 'users', $entityId = null)`.
3. The method calls `$this->transaction(fn () => $this->record(RejectedAttempt, ...))`: a transaction of its own, one row with `before_state = NULL` and `after_state = {route, status, code, reason, username}`.
4. The caller then throws its `ApiProblemException`.

### 2.3 Browsing: `GET /api/v1/audit-logs`

1. Route in `routes/api.php`: `Route::get('audit-logs', ...)->middleware('ability:audit.read')->name('audit-logs.index')`, inside `port.api`. So: `auth:web` (401) → `EnsureAccountUsable` (F08) → `EnsureAbility` (F09; any role but `PORT_GM` gets 403 and an audit row).
2. Laravel builds `AuditLogIndexRequest` (a `FormRequest`) and validates the query string. Failure → **422 `VALIDATION_FAILED`** with `errors` per field.
3. `AuditLogController::index` (`AuditLogController.php:19-41`) takes `$request->validated()` and builds one query:
   1. `AuditLog::query()->leftJoin('users', 'users.id', '=', 'audit_logs.actor_id')->select('audit_logs.*', 'users.name as actor_name', 'users.username as actor_username')`. **Left** join, because `actor_id` is NULL for the system or a worker;
   2. each filter that is present adds a `where` (table 3.8);
   3. `q` becomes a `LIKE %…%` on `users.username` or `users.name`, with `\ % _` escaped by `addcslashes`;
   4. `orderByDesc('audit_logs.occurred_at')`, then `orderByDesc('audit_logs.id')`.
4. `$request->page()->paginate($query)` (`PageRequest`: `page` default 1, `per_page` default 25, max 100) runs the query.
5. The result is wrapped in `AuditLogCollection` → `{ "data": [AuditLogEntry...], "meta": { "page", "per_page", "total" } }` (CT-01, F03). No `links`.

## 3. Every piece of code

### 3.1 `AuditService` (`app/Modules/Shared/Audit/AuditService.php`)

A plain class (resolved from the container). The actor is a **plain `?int`**, never a `User`: `Shared` is layer 1 and must not depend on `Access` (A4). `NULL` means the system or an AI worker; there is **no default** actor, so every caller states it.

| Method | Input → output | Behaviour |
| --- | --- | --- |
| `transaction(Closure $write): mixed` | closure → its return value | `DB::transaction($write)`. Nested calls join the outer transaction (Laravel savepoints). |
| `record(AuditLogAction $action, string $entityType, ?int $entityId, ?int $actorId, ?array $before, ?array $after): AuditLog` | → the saved row | Throws `LogicException` if `DB::transactionLevel() === 0`. Else `AuditLog::query()->create([...])`; `occurred_at` is left to the column default (`useCurrent`, second precision). Note `chk_al_states`: `before_state` must be NULL for CREATE and `after_state` must be NULL for DELETE (the database throws a `QueryException` otherwise). |
| `recordCreated(Model, ?int $actorId): AuditLog` | | `record(Create, table, key, actor, null, state(model))` |
| `recordUpdated(Model, ?int $actorId): ?AuditLog` | | Call it right after the `save()` that changed the **same instance**. Uses `getChanges()`; returns `null` and writes nothing if nothing changed. Rebuilds the previous attributes from `getPrevious()`, then stores only the changed keys, before and after (`Arr::only`). Note: `updated_at` appears among the changed fields. |
| `recordDeleted(Model, ?int $actorId): AuditLog` | | `record(Delete, table, key, actor, state(model), null)` |
| `rejectedAttempt(string $route, ErrorCode $code, string $reason, ?int $actorId, ?string $username, string $entityType = 'users', ?int $entityId = null): AuditLog` | → the saved row | See 2.2 and below. |
| `state(Model): array` (private) | | `Arr::except($model->attributesToArray(), ['password', 'password_hash'])`. Because it uses `attributesToArray()`, attributes in a model's `$hidden` also stay out; the password columns are removed explicitly as a second guard. |

**`rejectedAttempt` in detail.**

- `$route` is `"METHOD /path"` as built by the caller (for example `"POST /api/v1/auth/login"`).
- `after_state` is exactly `{route, status: $code->status(), code: $code->value, reason, username}`. `username` is the **raw input** and may be null (a refused write by a signed-in user passes their username). It never carries a password.
- `$entityType`/`$entityId` name the refused target: before sign-in they stay `'users'`/`NULL`; a signed-in refusal names the user (`'users'`, user id) like the guards in F08/F09; a refused write names its table and row (`EnsureRecordVersion` passes the record's table and key).
- **Why it runs in its own transaction, and why after the rollback.** A refused write is rolled back; an audit row written inside that transaction would roll back with it and the refusal would leave no trace. `rejectedAttempt` therefore opens its own `transaction()`, and the caller must call it **after** the refused write's transaction has ended. If it is called while an outer transaction is open, it only joins it (nested transactions are savepoints in one transaction), so a later rollback of the outer one takes the audit row with it (P2-D3 review note). `AuditServiceTest` "keeps a refused attempt after the refused write was rolled back" proves the correct order: the exception is caught outside the transaction and the audit call comes after.
- **Callers today:** `SignInService::recordRejectedAttempt` (delegator; F07, F08), `AccessRefusal::refuse` (F09), `EnsureRecordVersion::recordRefusal` (F12, `EnsureRecordVersion.php:79-90`), **`EnsureTokenDevice::refuse`** (F07, mobile token on the wrong or a revoked device) and **`MobileSignInService::issueToken`** (F07, mobile sign-in from a revoked device).

**Every `after_state.reason` written today, and where.** The reason is a free `string` argument; there is no enum behind it except `AccessRefusalReason` for the F09 cases.

| `reason` | `code` / status | `entity_type` / `entity_id` | Written by | Meaning |
| --- | --- | --- | --- | --- |
| `invalid_credentials` | `UNAUTHENTICATED` 401 | `users` / NULL, actor NULL | `SignInService::refuse` (web and mobile sign-in) | unknown username or wrong password |
| `account_disabled` | `ACCOUNT_DISABLED` 403 | `users` / NULL at sign-in; user id on a later request | `SignInService::refuse` (sign-in); `EnsureAccountUsable` | deactivated user |
| `password_change_required` | `PASSWORD_CHANGE_REQUIRED` 403 | `users` / user id | `EnsureAccountUsable` | route not on the allow-list while the flag is set |
| `wrong_current_password` | `VALIDATION_FAILED` 422 | `users` / user id | `ChangePasswordRequest::failedValidation` | wrong current password (guessing from an open session or token) |
| `no_active_role`, `role_not_allowed`, `terminal_out_of_scope`, `not_own_task` | `FORBIDDEN` 403 | `users` / user id | `AccessRefusal::refuse` (F09) | role, terminal or own-task refusal |
| **`device_mismatch`** | `UNAUTHENTICATED` 401 | `users` / user id | `EnsureTokenDevice` (`refuse($request, 'device_mismatch')`) | a Bearer token used with an `X-Device-Id` that is not the device it was issued to |
| **`device_revoked`** (a) | `UNAUTHENTICATED` 401 | `users` / user id | `EnsureTokenDevice` | a Bearer token whose device row has `revoked_at`; the token is revoked in the same step |
| **`device_revoked`** (b) | `DEVICE_REVOKED` 403 | **`user_devices`** / device id | `MobileSignInService::issueToken` | a sign-in (right password) from a device the Port Admin revoked; no token is issued |
| `version_invalid`, `stale_version` | `VALIDATION_FAILED` 422 / `VERSION_CONFLICT` 409 | the record's table / its key | `EnsureRecordVersion` (F12) | missing or bad version token / stale version |

Notes on the two new reasons:

- `device_mismatch` and `device_revoked` (a) are 401, not 403, on purpose: a stolen token learns nothing about which case it hit. They carry the user as actor and entity, like the password gate. The same reason string `device_revoked` appears twice with **different codes**: 401 `UNAUTHENTICATED` on a token request, 403 `DEVICE_REVOKED` at sign-in. Filter the audit list by `after_state.code` if you need to tell them apart (the browsing endpoint has no filter on `reason`).
- The sign-in case (b) is written **after** `DB::transaction` in `MobileSignInService` ends (`MobileSignInService.php:51-55`): inside the transaction it only returns the locked device row; the caller then calls `rejectedAttempt` and throws. This is the pattern F10 requires (audit after the refused work, never inside it). `MobileSignInTest` "refuses a revoked device with 403 DEVICE_REVOKED, audited, and issues no token" asserts `entity_type user_devices`, `entity_id` = device id and `reason device_revoked`. `MobileTokenTest` "answers 422 without X-Device-Id and 401 for another device, audited, without extending the token" and "refuses a token whose device was revoked: 401, audited as device_revoked, and the token is revoked too" cover (a) and `device_mismatch`.
- A **successful** mobile sign-in writes no row (lead decision Q10), and 422 and 426 at sign-in are not audited.

### 3.2 `AuditLog` (`app/Modules/Shared/Models/AuditLog.php`)

`#[Table(name: 'audit_logs', timestamps: false)]`, `#[Fillable([actor_id, entity_type, entity_id, action, before_state, after_state, occurred_at])]`, `#[UseFactory(AuditLogFactory::class)]`. Casts: `action => AuditLogAction`, `before_state`/`after_state => 'array'` (JSON columns), `occurred_at => 'datetime'`.

Append-only guards (NFR-SEC-6):

| Override | Effect |
| --- | --- |
| `performUpdate(Builder): never` | throws `LogicException('audit_logs is append-only (NFR-SEC-6): rows are never updated.')`, so `$row->update()`, `save()` on a changed row and `saveQuietly()` fail |
| `incrementOrDecrement(...): never` | same, so `$row->increment('x')` fails |
| `performDeleteOnModel(): never` | throws "rows are never deleted" |
| `newEloquentBuilder($query)` | returns `AuditLogBuilder` (3.3) |

### 3.3 `AuditLogBuilder` (`app/Modules/Shared/Models/AuditLogBuilder.php`)

`class AuditLogBuilder extends Eloquent\Builder`. Every method below throws `LogicException('audit_logs is append-only (NFR-SEC-6): rows are never updated or deleted.')` through a private `refuse(): never`:

`update`, `delete`, `forceDelete`, `upsert`, `increment`, `decrement`, `incrementEach`, `decrementEach`, `updateOrInsert`, `updateFrom`, `truncate`.

The last three need an explicit override because the Eloquent builder forwards unknown methods to the query builder (`__call`), where `truncate()` would empty the table and `updateOrInsert` would change a row. P2-D3 review 2 found exactly this; fixed in commit `9a14148`. `AuditLog::truncate()` (model static) and `AuditLog::query()->truncate()` are both covered by tests.

### 3.4 `AuditLogAction` (`app/Modules/Shared/Enums/AuditLogAction.php`)

String enum of `audit_logs.action`:

| Case | Value | Used for |
| --- | --- | --- |
| `Create` | `CREATE` | `recordCreated` |
| `Update` | `UPDATE` | `recordUpdated`, `PasswordChangeService` |
| `Delete` | `DELETE` | `recordDeleted` |
| `StatusChange` | `STATUS_CHANGE` | state transitions (`record()` called directly; later features) |
| `SafetyException` | `SAFETY_EXCEPTION` | the Port Admin's safety exception on a plan (later feature, `plans.safety-exception`) |
| `RejectedAttempt` | `REJECTED_ATTEMPT` | every refusal |

### 3.5 `AuditLogFactory` (`database/factories/Shared/AuditLogFactory.php`)

`definition()`: `actor_id` null, `entity_type` random 10 letters, `entity_id` null, `action` `Create`, `before_state` null, `after_state` null, `occurred_at` `now()`. Tests override fields (for example `occurred_at`, `actor_id`). Creating through the factory works because insert is allowed; `$row->update()` is not.

### 3.6 `AuditLogController` (`app/Modules/Shared/Http/Controllers/AuditLogController.php`)

One method, `index(AuditLogIndexRequest): AuditLogCollection`, described in 2.3. No `authorize` code: the route middleware does it.

### 3.7 `AuditLogIndexRequest` (`app/Modules/Shared/Http/Requests/AuditLogIndexRequest.php`)

`authorize()` returns `true` (the ability is the route's job). `rules()`:

| Query field | Rules |
| --- | --- |
| `page` | `sometimes`, `integer`, `min:1`, `max:1000000` (from `PageRequest::rules()`) |
| `per_page` | `sometimes`, `integer`, `min:1`, `max:100` |
| `entity_type` | `sometimes`, `string`, `max:60` (a table name; matches `audit_logs.entity_type`) |
| `entity_id` | `sometimes`, `integer`, `min:1` |
| `actor_id` | `sometimes`, `integer`, `min:1` |
| `action` | `sometimes`, `string`, `Rule::enum(AuditLogAction::class)` |
| `from` | `sometimes`, `ZonedDateTime::rules()` (string, regex for ISO 8601 with `Z` or an offset, `date`), and `before_or_equal:to` when `to` is present |
| `to` | `sometimes`, `ZonedDateTime::rules()` |
| `q` | `sometimes`, `string`, `min:1`, `max:100` |

`page(): PageRequest` returns `new PageRequest((int) query('page', 1), (int) query('per_page', 25))`. An out-of-range value is a 422, never clamped; a page past the end is a normal 200 with empty `data` (F03). A time without an offset (`2026-11-01T10:00:00`) is refused with 422 (naive times are ambiguous for a port).

### 3.8 Filters in the controller

| Filter | Query condition |
| --- | --- |
| `entity_type` | `audit_logs.entity_type = ?` |
| `entity_id` | `audit_logs.entity_id = ?` |
| `actor_id` | `audit_logs.actor_id = ?` |
| `action` | `audit_logs.action = ?` |
| `from` | `audit_logs.occurred_at >= ZonedDateTime::toUtc(from)` (inclusive) |
| `to` | `audit_logs.occurred_at <= ZonedDateTime::toUtc(to)` (inclusive) |
| `q` | `users.username LIKE %q%` OR `users.name LIKE %q%` (case-insensitive by collation; rows with no actor never match) |

Filters combine with AND. Master-data history for one record: `?entity_type=berths&entity_id=3`. `entity_type` is the **table name** (plural), as written by `Model::getTable()`; an entity type that nobody wrote just returns an empty list.

### 3.9 `AuditLogCollection` and `AuditLogEntryResource`

`AuditLogCollection extends PaginatedCollection` with `$collects = AuditLogEntryResource::class` (F03: the `{data, meta}` envelope and nothing else). `AuditLogEntryResource::toArray()` returns:

```json
{
  "id": 12,
  "actor": { "id": 5, "name": "Amal Admin", "username": "amal.admin" },
  "entity_type": "berths",
  "entity_id": 3,
  "action": "UPDATE",
  "before_state": { "name": "A" },
  "after_state": { "name": "B" },
  "occurred_at": "2026-11-01T09:00:00.000000Z"
}
```

- `actor` is `null` when `actor_id` is NULL (system or worker); otherwise `{id, name, username}` from the joined columns `actor_name` and `actor_username`.
- `entity_id`, `before_state`, `after_state` may be `null`.
- `occurred_at` is UTC with microseconds and a trailing `Z` (Laravel's date serialisation; the database session is `+00:00`).
- `action` is the enum's string value.

## 4. Why this design

| Choice | Reason | Source |
| --- | --- | --- |
| One writer, `AuditService` | one place guarantees the same transaction and the same refusal shape | FR-GL-02, DEC-02, AIBD-025 |
| Service in `Shared`, actor as `?int` | `Shared` is layer 1 and cannot use `Access\User`; `NULL` is the system | P2-D3 Q1 (Marwan, 2026-11-03) |
| `record()` throws outside a transaction | the API shape makes "write the change, audit later" impossible (not a structural guarantee: a caller that never calls the service is not caught; P2-D2 makes the service mandatory per endpoint) | P2-D3 §3, review note |
| `rejectedAttempt` has its own transaction | a refused write is rolled back; the refusal must still be kept | P2-D3 review 2 note; FR-GL-02 |
| `after_state` holds `{route, status, code, reason, username}` | Coding Notes §6.4 asks for status; the lead asked for the code; both kept | P1-C1 review 1 conflict (approved 2026-10-30), P2-D3 |
| `SignInService::recordRejectedAttempt` kept as a delegator | other branches still called it; delete it later | P2-D3 Q2 |
| Eloquent append-only guard, no trigger, no grants change | the lead decided; a trigger or app-account grants touch `database/grants` and need the `ai-impact` label and AI approval; left to deployment (P6) | P2-D3 Q3, §7 |
| Builder overrides `truncate`, `updateOrInsert`, `updateFrom` | the Eloquent builder forwards them to the query builder, where they run | P2-D3 review 2 (Must fix) |
| Password columns removed from state | secrets must never reach the log | `AuditService::state` |
| Browsing: Port Admin only, `audit.read` | the log contains usernames typed into the sign-in form and user data | FR-MD-16, P2-F8; P2-D1 matrix |
| Six filters plus `q` and `actor` shape `{id, name, username}` | lead's answers | P2-F8 Q1, Q2 |
| Left join | a deleted user's rows keep `actor_id`; system rows have none | P2-F8 §3 data |
| Order `occurred_at desc, id desc` | `occurred_at` is second-precision, so ties need the id; `PageRequest` needs a stated order | P2-F8 §3 |
| No actor role in the response | `audit_logs` has no role column; adding one is a schema change (CT-17, AI approval) | P2-F8 §7 note |
| Master-data history as a filter, not a separate endpoint | one list serves both screens (P-29) | P2-F8 |
| The database account still matters | workers get no access to `audit_logs`; the app account should get INSERT and SELECT only | DEC-08, `audit_logs` table comment |

## 5. How it connects

- **Called by:** `PasswordChangeService` (F08), `SignInService` (F07), `EnsureAccountUsable` and `ChangePasswordRequest` through the delegator (F08), `AccessRefusal` (F09), `EnsureRecordVersion` (F12), `EnsureTokenDevice` and `MobileSignInService` (F07, the device reasons). Every later write endpoint must use `transaction()` + `record*()` (F11 describes where in the chain).
- **Calls:** `DB`, `AuditLog`, `ErrorCode` (status and value for `after_state`, F03). The browsing endpoint calls `PageRequest`, `PaginatedCollection`, `ZonedDateTime` (F03).
- **Depends on:** F05 (table, `chk_al_states`), F09 (`audit.read`, `AccessRefusal`), F03.
- **Used by:** Frontend screen P-29 (FE-050), QA, the Port Admin; the later features that write status changes and safety exceptions.
- **Module layer:** `Shared` uses nothing; `Access` uses it. The browsing controller joins the `users` table by name, which is a read of an Access table from Shared, done with the query builder (not the `User` model) so the architecture test is satisfied. Unverified as a design point: if `users` were renamed, Shared would break; what to check: `ModuleArchitectureTest` still passes (it only inspects class usage).

## 6. Data

Table `audit_logs` (Backend-only, CT-15):

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned | |
| `actor_id` | bigint unsigned NULL, FK `fk_al_user` to `users` | NULL = system or worker; FK means a user with audit rows cannot be hard-deleted (they never are, FR-GL-03) |
| `entity_type` | varchar(60) | the table name; for refusals the target table |
| `entity_id` | bigint unsigned NULL | NULL when a refusal has no entity (failed login) |
| `action` | ENUM six values | `AuditLogAction` |
| `before_state`, `after_state` | JSON NULL | |
| `occurred_at` | datetime (no fraction), default `CURRENT_TIMESTAMP` | |

Constraint `chk_al_states`: `(action <> 'CREATE' OR before_state IS NULL) AND (action <> 'DELETE' OR after_state IS NULL)`. Indexes `fk_al_user (actor_id)`, `idx_al_entity (entity_type, entity_id)`, `idx_al_time (occurred_at)`. The browsing query uses them; the `q` search is a leading-wildcard `LIKE` on `users` and cannot use an index (fine for the 50 000 rows of NFR-CAP-3; recheck with the benchmark seed).

MySQL stores JSON object keys in its own order; tests compare with `toEqual` (by key), not `toBe`.

**What the code does not enforce, but the database account should (DEC-08, NFR-SEC-6).**

- Workers: `database/grants/workers.sql` gives the six worker roles `SELECT` on every port table **except** `audit_logs`, `notification_logs`, `user_devices` and `recommendation_reviews`, and gives no `INSERT` on `audit_logs`. A worker therefore cannot read or write the log. (Verified: the file's header and `grep audit_logs` show only that exclusion, no grant.)
- The **application** database account should have `INSERT` and `SELECT` only on `audit_logs` (the table's comment says so). Nothing in the repository sets the application account's privileges. The `AuditLog` guards stop only Eloquent: `DB::table('audit_logs')->delete()`, `$model->getQuery()->delete()`, `toBase()` and raw SQL still work. So a real deployment must grant the app account INSERT+SELECT (and no UPDATE/DELETE) on this table, or add a trigger. Both are deployment work (P6); DB-level rejection is what NFR-SEC-6 literally asks for.

## 7. Tests

Run with `vendor/bin/pest tests/Feature/Audit/AuditServiceTest.php tests/Feature/Audit/AuditLogBrowsingTest.php` (own database, A7).

`tests/Feature/Audit/AuditServiceTest.php` — commits for real (transactions are the subject). It remembers `max(id)` of `audit_logs` before each test and deletes only rows above it afterwards, plus users named `audit-test-%`, so it does not touch other runs' rows.

| Test | Proves |
| --- | --- |
| "saves the change and its audit row together" | CREATE row: entity type `users`, actor, `before_state` NULL, no `password_hash` in `after_state` |
| "stores neither the change nor the audit row when the write fails" | a thrown exception rolls both back |
| "stores neither when the audit row cannot be saved" | a CREATE with a before state breaks `chk_al_states`; the user is not stored |
| "refuses to write an audit row outside a transaction" | `LogicException`, no row |
| "writes NULL actor for the system" | `STATUS_CHANGE` with actor NULL |
| "stores only the changed fields on update and nothing when nothing changed" | before keys `['name','updated_at']`, no `password_hash`, `recordUpdated` returns null on no change |
| "stores the old row on delete, without an after state" | |
| "keeps a refused attempt after the refused write was rolled back" | exact `after_state`, 423 `LICENSE_READ_ONLY`, entity type `berths`, the rolled-back user is gone |
| "records a refusal before sign-in with no actor and no entity" | |
| "refuses to update or delete an audit row through the model or the query builder" | `update`, `fill()->saveQuietly()`, `delete`, builder `update` and `delete` |
| "refuses increments on an audit row" | model and builder `increment`/`decrement` |
| "refuses statements the query builder would otherwise run" | `truncate` (model and builder), `updateOrInsert`, `updateFrom` |

`tests/Feature/Audit/AuditLogBrowsingTest.php` — rolled back; user `Amal Admin` as `PORT_GM`.

| Test | Proves |
| --- | --- |
| "refuses a guest with 401" | |
| "lists rows newest first with the actor and the states" | `meta {page 1, per_page 25, total 2}`, `actor` null for a system row, the actor shape, `occurred_at` as `2026-11-02T09:00:00.000000Z`, before/after states |
| "orders rows with the same time by id, newest first" | the tie-break |
| "paginates" | `per_page=2&page=2` |
| "filters by entity, actor, action and dates" | each filter and combinations, including an offset time (`+02:00`) and an unknown entity |
| "searches the actor by username or name" | `q` case-insensitive, a literal `%` matches nothing (escaped) |
| "answers 422 for a bad filter" (5 datasets) | unknown action, naive `from`, `from` after `to`, `per_page=101`, `entity_id=0` |
| "answers 403 FORBIDDEN to every role except the Port Admin and audits the refusal" (11 roles) | one new `REJECTED_ATTEMPT` row each |
| "answers 403 FORBIDDEN to a user with no active role" | |

Real run for this guide: both files passed inside the 217-test run on `develop` at `9559d35` (Access and Audit folders, `RolePermissionsTest`, `SystemInfoTest`, `RouteAbilityCoverageTest`, `WriteChainOrderTest`, `PublishRoleMatrixTest`, `TokenPruneNotScheduledTest`; 2017 assertions; 68 s) on `atlantech_testing_g3`. The two device reasons are tested in `MobileSignInTest` and `MobileTokenTest` (F07), not in the Audit folder.

## 8. Gotchas and invariants

- **Never call `rejectedAttempt` inside the transaction it is meant to survive.** Call it after the rollback (or outside any transaction). `MobileSignInService::issueToken` does it right: it returns from the transaction, then audits and throws. `EnsureRecordVersion` calls it before `$next` or after the response, never inside the endpoint's transaction.
- **`record*()` need an open transaction**; always go through `$audit->transaction()`.
- **Pass the actor explicitly**, `null` for the system. There is no `Auth::id()` fallback in Shared on purpose.
- **Do not put secrets in `before`/`after` arrays** you pass to `record()` yourself: only `recordCreated/Updated/Deleted` strip `password` and `password_hash`. `rejectedAttempt` stores `username` as typed, so a password typed into the username field is stored (lower-cased by `LoginRequest`), visible to the Port Admin only.
- **`recordUpdated` strips password fields**, so a password-only change gives an empty before/after; write your own `record()` row, as `PasswordChangeService` does.
- **`recordUpdated` must be called on the same instance right after `save()`**; on a re-fetched instance `getChanges()` is empty.
- **The append-only guard is Eloquent-only** (section 6). Do not use `DB::table('audit_logs')` for anything but reading; and never delete in tests (`AuditServiceTest` deletes with `DB::table`, which is why it filters by its own ids).
- **`occurred_at` has one-second precision**: ordering inside a second relies on `id`.
- **The browsing query joins `users`**; a column added to `audit_logs` that clashes with `users` names must be selected explicitly (the select uses `audit_logs.*` plus two aliases).
- **`entity_type` is a table name** in the plural; sign-in refusals use `users`, except the mobile revoked-device refusal, which uses `user_devices`. `personal_access_tokens` revocations (logout, password change, deactivation) write **no** audit row of their own; only the password change's `UPDATE` row on `users` exists, and it does not list the tokens.
- **A refused read (403) is audited, a successful read is not.**
- **`q` excludes rows with no actor** (system rows), because it filters on joined columns.

## 9. How to extend it

**Audit a new write.** In the service or controller: `$audit->transaction(function () use (...) { $model = Model::create(...); $audit->recordCreated($model, $request->user()->id); return $model; });` For an update: `$model->update(...)` then `$audit->recordUpdated($model, $actorId)`. For a status change: `$audit->record(AuditLogAction::StatusChange, $model->getTable(), $model->id, $actorId, ['status' => $old], ['status' => $new])`.

**Audit a new kind of refusal.** Call `$audit->rejectedAttempt($route, ErrorCode::X, 'snake_case_reason', $actorId, $username, $table, $id)` after the rollback, then throw the problem. Add a test that reads the row.

**Add an action.** Add the case to `AuditLogAction` **and** the SQL ENUM through a migration (F05: strict ENUM, the model-layer test compares them), regenerate the status vocabulary (`composer contracts:status-vocabulary`, F14) and extend the `action` filter automatically (the request uses `Rule::enum`). Also OpenAPI `AuditAction` (F04).

**Add a filter.** Add the rule in `AuditLogIndexRequest::rules()`, a `->when(isset($filters['x']), ...)` line in the controller, a test in `AuditLogBrowsingTest`, the query parameter in `openapi/port-api.yaml` (minor version bump), and the PortHub copy.

**Show the actor's role.** Needs a new column on `audit_logs` (CT-17, AI approval); not possible in code alone.

**Harden the table.** Grant the application account `INSERT, SELECT` only and add a trigger or revoke `UPDATE, DELETE`; do it in the deployment scripts (P6), not in `database/grants/workers.sql`.

## 10. Try it

Run for this guide:

```bash
$env:DB_DATABASE='atlantech_testing_g3'; $env:DB_SCHEMA_TEST_DATABASE='atlantech_testing_g3'
vendor/bin/pest tests/Feature/Audit
```
Real result: part of the 217-test run, all passed.

```bash
php artisan route:list --path=api/v1/audit-logs
```
Real output: `GET|HEAD api/v1/audit-logs .. audit-logs.index › App\Modules\Shared\Http\Controllers\AuditLogController@index`.

```bash
node docs/reference/lookup.mjs DEC-08
```
Real output: `DEC-08 · decision · Database read source and worker privileges`, `status: Closed`, owner Backend Team, `decides CT-17`, unblocks `P3-I5` and `AI-013`.

**Expected, not run** (needs a running server and a Port Admin session; I did not touch the `atlantech` database). After signing in as `PORT_GM` (F07) with the session cookie jar:

```bash
curl -b jar "http://localhost:8000/api/v1/audit-logs?entity_type=users&action=REJECTED_ATTEMPT&per_page=5" -H "Accept: application/json"
```
Expected: `200` with `{"data":[{"id":...,"actor":null,"entity_type":"users","entity_id":null,"action":"REJECTED_ATTEMPT","before_state":null,"after_state":{"route":"POST /api/v1/auth/login","status":401,"code":"UNAUTHENTICATED","reason":"invalid_credentials","username":"..."},"occurred_at":"...Z"}],"meta":{"page":1,"per_page":5,"total":N}}`. With any other role: `403 FORBIDDEN`. With `?per_page=101`: `422 VALIDATION_FAILED` with `errors.per_page`.

## 11. Open points

- **Not enforced by the code (the main one):** DB-level append-only for the application account (NFR-SEC-6, DEC-08). Nothing in the repository grants or revokes privileges for the app account; `workers.sql` only keeps workers out. Left to deployment (P6). Until then `DB::table('audit_logs')->delete()` works.
- **`SignInService::recordRejectedAttempt` delegator** should be removed once `EnsureAccountUsable` and `ChangePasswordRequest` call `AuditService` directly (P2-D3 Q2).
- **Mandatory service use** is by API shape only; no test or static check proves that a future write endpoint calls `transaction()` + `record*()` (P2-D3 review note; F11 is meant to make it part of the endpoint recipe).
- **Raw `username` in refusals** can contain a typed password (P2-D3 review). Port Admin only can read it; consider masking when the username fails the `LoginRequest` shape (not decided).
- **No actor role in the response** (NFR-SEC-6 lists the role; the table has no column). Needs a CT-17 change.
- **OpenAPI 403 lists `FORBIDDEN` only** although the route can answer `PASSWORD_CHANGE_REQUIRED` and `ACCOUNT_DISABLED` (P2-F8 review, same as the other paths).
- **Readiness pack line for FT-03** (`GET /api/v1/audit-logs`) and the PortHub copy of the OpenAPI file for this endpoint were still open in the P2-F8 follow-up (2026-11-04). Unverified whether they have landed; what to check: PortHub `contracts/openapi` for `/api/v1/audit-logs`.
- **`q` search is not indexed** (leading wildcard); recheck with the benchmark data (NFR-CAP-3).
- **No READ_ONLY licence test** for the browsing endpoint until the licence middleware (F11/F15) is filled; by design a GET is allowed.
- **`AuditServiceTest` commits for real** and shares the database; run it only on your own test database.
