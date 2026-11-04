# F12 · Version token and optimistic locking

Task origin: P2-D4 (Sprint 2, merged in PR #51 with P2-D2, see F11). Facts checked against `develop` at `106b40e`. Paths are inside `project-backend/ATLANTECH/` unless stated. Plan file: `docs/tasks/P2-D4.md`.

## 1. What it does

It stops two people from silently overwriting each other (the "lost update"). Every editable record carries a **version**: its `updated_at` column (`DATETIME(6)`, microseconds) written as an opaque UTC string, for example `2026-11-02T10:15:04.123456Z`. A client reads a record, gets its `version`, and sends the same `version` back with its update. If the record was changed in the meantime, the stored `updated_at` is different and the update is refused with:

- **409 `VERSION_CONFLICT`** and a body member `current: { id, version }` that carries the stored values, so the client can show "changed by someone else" and reload;
- **422 `VALIDATION_FAILED`** with `errors.version` when the client sends no `version` or a value of the wrong shape.

Two checks use the same comparison:

1. the middleware `version.token:<param>` (the early check, at its fixed place in the write chain, F11), and
2. `VersionToken::lockAndAssert(...)` inside the endpoint's transaction, on the row locked with `SELECT … FOR UPDATE`, which makes the check safe against two simultaneous requests (NFR-CONC-1).

Every refusal of this step is also written to the audit log as `REJECTED_ATTEMPT` (FR-GL-02).

It does **not**: create versions for records that have no `updated_at`, apply to creates (a new record has no version), handle mobile idempotency (`Idempotency-Key`, CT-01 section 4) or mobile sync conflicts (CT-07, task P5-O2), and it is **not used by any real endpoint yet**: the first update endpoints are P2-E1..E3 and P2-F1..F5 (F15). Only the tests exercise it, through throw-away routes over the `Tugboat` model. No API resource returns `version` yet either (`UserProfileResource` has none).

## 2. Request or data flow

An update `PATCH /api/v1/tugboats/12` with JSON `{ "version": "2026-11-02T10:15:04.123456Z", "name": "New" }` on a route with `version.token:tugboat`:

1. The write chain runs the earlier steps (401, password gate, ability, terminal scope, 426, 423) and route binding turns `{tugboat}` into the model (F11).
2. `EnsureRecordVersion::handle` (`app/Modules/Shared/Http/Middleware/EnsureRecordVersion.php:41`):
   1. a safe method (GET, HEAD, OPTIONS) is passed on at once (line 43);
   2. it takes the bound parameter, `$request->route('tugboat')`; if that is not an Eloquent model it throws `LogicException` (lines 47-51: a typo in the route is a loud 500 in tests, not a silent pass);
   3. it validates `version` with `VersionToken::rules()` (line 53). If invalid: audit `VALIDATION_FAILED` / `version_invalid`, then `ValidationException` (422, lines 55-59);
   4. if `VersionToken::matches($record, $sent)` is false: audit `VERSION_CONFLICT` / `stale_version` (line 61-63), then `VersionToken::assertCurrent` throws the 409 (line 65);
   5. otherwise it calls `$next($request)`, the endpoint (line 67).
3. The endpoint opens `DB::transaction(...)`, calls `VersionToken::lockAndAssert($tugboat, $request->input('version'))`: that reads the row `FOR UPDATE`, compares again, and returns the locked, fresh row. The endpoint applies its rules, saves (the database sets a new `updated_at`), writes the audit row, commits.
4. The endpoint returns the resource with the new `version = VersionToken::of($locked->refresh())`.
5. **On the way out**, if the endpoint threw the 409 from `lockAndAssert`, the routing pipeline has already turned the exception into a response that keeps the exception object. The middleware reads `$response->exception` (line 70) and, if it is an `ApiProblemException` with `ErrorCode::VersionConflict`, writes the audit row (lines 72-74). By then the endpoint's transaction has rolled back, so the audit row (its own transaction) survives.

A client that gets 409 reads `current.version`, reloads the record, re-applies its change and sends again.

## 3. Every piece of code

### 3.1 `VersionToken` (`app/Modules/Shared/Support/VersionToken.php`)

A class of static methods; it imports only Eloquent, Carbon, `DB` and Shared's own exception (Shared depends on nothing, A4).

| Member | Line | Input → output | What it does and why |
| --- | --- | --- | --- |
| `const string PATTERN` | 22 | `/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/` | The only shape a client may send back: UTC, **exactly six** fractional digits, a literal `Z`. No offset, no shorter fraction. This is what makes the comparison exact. |
| `rules()` | 29 | none → `['required', 'string', 'regex:' . PATTERN]` | Validation rules for the `version` member of an update request. Missing gives `validation.required`, wrong shape gives `validation.regex`, a non-string (a number) gives `validation.string`. Used by the middleware; an endpoint that validates its own body can reuse it. |
| `format(CarbonInterface $at)` | 34 | a Carbon date → `string` | `$at->copy()->utc()->format('Y-m-d\TH:i:s.u\Z')`. Converts to UTC first, so `2026-11-02T12:15:04.123456+02:00` becomes `2026-11-02T10:15:04.123456Z`. A date without a fraction gets `.000000`. |
| `of(Model $model)` | 39 | model → `string` | Reads the model's `updated_at` column (`getUpdatedAtColumn()`), and formats it. Throws `LogicException` ("has no updated_at to use as a version token") if the attribute is not a Carbon date, for example a model without timestamps or a missing cast. Resources must call this for the `version` member. |
| `matches(Model $model, string $sent)` | 50 | model, client string → `bool` | `hash_equals(self::of($model), $sent)`: exact string equality, constant-time. No parsing of `$sent`, so `…123000Z` is not `…123456Z` and `…04Z` is not `…04.000000Z`. |
| `assertCurrent(Model $model, string $sent)` | 58 | model, string → `void` | Throws `ApiProblemException::versionConflict(['id' => $model->getKey(), 'version' => self::of($model)])` when `matches` is false. It is **pure** (no audit): inside the locked transaction an audit row would roll back with the failed write, so the audit is done by the middleware outside the transaction (D4 plan section 7). |
| `lockAndAssert(Model $model, string $sent)` | 77 | model, string → the **locked fresh model** | Requires an open transaction (`DB::transactionLevel() === 0` throws `LogicException`, because a lock held for one statement protects nothing). Runs `$model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail()` and then `assertCurrent` on that fresh row. Returns the locked row; the endpoint must change **that** instance, not the one route binding loaded. `firstOrFail` gives a 404 if the row was deleted in between. |

`ApiProblemException::versionConflict(array $current, ?string $detail = null)` (`app/Modules/Shared/Errors/ApiProblemException.php:52`) builds the 409 with `extensions: ['current' => $current]`; `ProblemRenderer` writes extensions into the body (F03). Reserved member names cannot be used as extensions.

### 3.2 `EnsureRecordVersion` (`app/Modules/Shared/Http/Middleware/EnsureRecordVersion.php`)

- Alias `version.token`, registered in `SharedServiceProvider::boot` (`SharedServiceProvider.php:18`). Used as `version.token:<routeParam>`; the text after the colon arrives as `string $parameter`.
- Constructor: `AuditService $audit` (injected).
- `handle(Request $request, Closure $next, string $parameter): Response`: the flow in section 2. Throws `ValidationException` (422), `ApiProblemException` (409) or `LogicException` (not a model).
- `recordRefusal(Request $request, Model $record, ErrorCode $code, string $reason): void` (line 79, private): calls `AuditService::rejectedAttempt($request->method().' /'.$request->path(), $code, $reason, $user?->getAuthIdentifier(), $user?->getAttribute('username'), $record->getTable(), $record->getKey())`. The audit row has `action = REJECTED_ATTEMPT`, `actor_id` = the caller, `entity_type` = the **table name** of the record (for example `tugboats`), `entity_id` = its key, `after_state` = `{route, status, code, reason, username}` (F10). Reasons used: `version_invalid` (422) and `stale_version` (409).
- Position in the chain: after `SubstituteBindings`, before the controller (`bootstrap/app.php:65-66`). The class comment says it must not move.

### 3.3 Where the pieces are wired

| Wiring | Place |
| --- | --- |
| Alias `version.token` | `SharedServiceProvider.php:18` |
| Order in the priority list | `bootstrap/app.php:66` |
| Column: `updated_at DATETIME(6)` with `useCurrent()->useCurrentOnUpdate()` | each editable table's migration (for example `port_settings`: `$table->dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate()`) |
| Model: `#[DateFormat('Y-m-d H:i:s.u')]` and an `updated_at` datetime cast | the model (for example `PortSetting`); needed so the microseconds are read back |

## 4. Why this design

| Decision | Reason | Source |
| --- | --- | --- |
| Version = `updated_at DATETIME(6)`, returned as the string `version`, not an integer counter or ETag | No extra column or trigger; the database maintains it; microseconds make a clash practically impossible. NFR-CONC-1's wording ("integer `version`" / `ETag`) is replaced by the decision. | DEC-57 (Closed), FR-GL-05, CT-01 section 5, `Coding_Notes_and_Mapping.md` section 4 |
| The client treats `version` as opaque and sends it back unchanged | JavaScript's `Date` keeps milliseconds only; a client that parsed and re-serialised it would send `…123000Z` and be refused. The exact-string compare removes any date maths on the server. | CT-01 section 5 |
| Missing or malformed `version` gives **422** with `errors.version` | CT-01 did not say; the lead decided it follows the existing validation shape. | Lead's answer Q2 (2026-11-03) |
| **Two** checks: middleware for the position, `FOR UPDATE` re-check for the race | A middleware compare alone leaves a window between the read and the save; NFR-CONC-1 demands that of two simultaneous writes exactly one commits. | Lead's answer Q1 (2026-11-03) |
| `assertCurrent` is pure; audit lives in the middleware | Inside the locked transaction, an audit row for the refusal would be rolled back with the failed write. | `docs/tasks/P2-D4.md` section 7 |
| Audit the 409 and 422 **after** P2-D3 merged, through `AuditService::rejectedAttempt` | FR-GL-02 says every refused attempt is audited. No temporary code was written. | Review answer R2 (2026-11-04) |
| Read `$response->exception` on the way out instead of `catch` | The routing pipeline turns each exception into a response at every middleware, so a `catch` around `$next` never sees it. | progress log of `docs/tasks/P2-D4.md`, 2026-11-04 |
| `LogicException` for a route parameter that is not a model | A typo in `version.token:tug` would otherwise skip the check silently. | review "Should fix", done |
| Coverage test for PUT/PATCH | A new update endpoint cannot skip the check. | D4 plan step 4 |
| Rejected | An integer `version` column on every table; ETag/`If-Match` headers; a middleware-only compare; a catch-based audit. | D4 plan section 7; DEC-57 |

## 5. How it connects

- **Used by:** nothing in production code yet. Planned users: P2-E1..E3 (users, roles, devices), P2-F1..F5 (master data), every update endpoint, and later the mobile app (P5-O2) which sends `version` like the web does.
- **Uses:** `AuditService` and `ErrorCode`, `ApiProblemException` (Shared, F03, F10); the write chain position (F11); Laravel route binding and `Validator`.
- **Resources must return `version`:** `'version' => VersionToken::of($this->resource)` on every single record and every list item. Nothing enforces this; `UtcDateTimeTest` (F03) only covers the date format.
- **Module rule:** `VersionToken` is in Shared and imports no other module. Models of any module are passed in as `Illuminate\Database\Eloquent\Model`.
- **Contracts:** CT-01 sections 5, 6 and 10 (the body shape of the 409 example); `error-codes.md` (`VERSION_CONFLICT`, message key `errors.version_conflict`, text in `lang/en/errors.php:18`).

## 6. Data

| Item | Detail |
| --- | --- |
| Column read | `updated_at DATETIME(6)` of the record. The plan checked (2026-11-03) that `users`, `terminals`, `berths`, `tugboats`, `safety_constraints`, `port_shifts`, `equipment`, `storage_facilities`, `pilot_profiles`, `tug_master_profiles` and the other master-data tables have it ("the 24 migrations with `updated_at DATETIME(6)`"). Tables without it, such as `audit_logs`, are append-only and have no version. |
| Written | `audit_logs` on every refusal: `action` `REJECTED_ATTEMPT`, `entity_type` = table, `entity_id` = key, `after_state` JSON. The table is append-only for everyone (F10). |
| Migrations / enums | none added by this feature. `ErrorCode::VersionConflict` (409, title "Record changed") and `ErrorCode::ValidationFailed` (422) existed before. |
| Locks | `SELECT … FOR UPDATE` on one row by primary key, inside the endpoint's transaction. |

Edge to know: a save that changes no column does not change `updated_at` (Eloquent skips the update), so the token stays the same and the response repeats it. A real change always produces a new token (D4 plan section 3).

## 7. Tests

All run on MySQL (`useSchemaDatabase()`), each inside a transaction that is rolled back. The three files below plus `WriteChainOrderTest` and `RouteVersionCoverageTest`: 27 tests, all passed on 2026-11-04 (see section 10).

**`tests/Feature/Api/VersionTokenHelperTest.php`** (9 tests; no HTTP; uses a `Tugboat` built in memory by `tugboatUpdatedAt()`):

| Test | Proves |
| --- | --- |
| `formats updated_at as UTC ISO 8601 with microseconds` | `of` and `format` give `…123456Z`; no fraction becomes `.000000`; `+02:00` is converted to UTC |
| `matches only the exact token, microseconds included` | `…123000Z` and `…04Z` do not match |
| `throws a 409 VERSION_CONFLICT carrying the stored id and version for a stale token` | `assertCurrent` throws `ApiProblemException` with `errorCode` `VersionConflict`, status 409, `extensions['current'] = ['id' => 12, 'version' => '…']`; the current token passes silently |
| `validates the shape of the version a client sends back` (5 data sets) | exact token valid; null, no microseconds, offset form and a number invalid |
| `refuses to lock outside a transaction` | `lockAndAssert` throws `LogicException` when `DB::transactionLevel()` is 0 (mocked with `DB::shouldReceive`) |

**`tests/Feature/Api/VersionTokenTest.php`** (12 tests). It registers three test routes over `Tugboat`: `PATCH test/tugboats/{tugboat}` (the normal endpoint shape: `DB::transaction`, `lockAndAssert`, update, return the new `version`), `PATCH test/tugboats-race/{tugboat}` (inside its transaction it changes the row and bumps `updated_at` **before** calling `lockAndAssert`, imitating someone else saving after the middleware check), and `GET test/tugboats/{tugboat}`. All use `['api', 'port.api', 'ability:tug-fleet.manage', 'version.token:tugboat']`; callers are `makeUserWithRole(Role::TugManager)`.

| Test | Proves |
| --- | --- |
| `accepts the current version, saves, and returns a new version` | 200; response `version` differs from the old one; name saved |
| `refuses a stale version with 409 and the stored id and version, and does not save` | second save with the old token: 409, content type `application/problem+json`, `code` `VERSION_CONFLICT`, `current.id`, `current.version` equal to stored; name unchanged |
| `answers 422 with errors.version when the version is missing or malformed` (2 data sets) | missing: `validation.required`; `'2026-11-02'`: `validation.regex`; `code` `VALIDATION_FAILED` |
| `lets a read through without a version` | GET passes |
| `refuses an unauthorised caller with 403 before it looks at the version` | `PORT_STAFF` sending no version gets 403 `FORBIDDEN`, not 422 (chain order) |
| `re-checks the version on the locked row and issues FOR UPDATE` | after a change elsewhere, `lockAndAssert` throws `ApiProblemException` and the query log contains `for update` |
| `fails loudly when version.token names a route parameter that is not a bound model` | `version.token:tug` on a route with `{tugboat}`: `LogicException` containing `version.token:tug` (with `withoutExceptionHandling`) |
| `audits a stale version refused by the middleware as REJECTED_ATTEMPT on the record` | one `AuditLog` row: action `RejectedAttempt`, `actor_id` the caller, `entity_type` `tugboats`, `entity_id` the record, `after_state` has status 409, code `VERSION_CONFLICT`, reason `stale_version` |
| `audits a missing version as REJECTED_ATTEMPT with 422` | same, code `VALIDATION_FAILED`, status 422, reason `version_invalid` |
| `audits the 409 from the locked re-check after the refused write rolled back` | race route: 409; the tugboat name is unchanged (the write rolled back); one audit row with reason `stale_version` |
| `writes no audit row for an accepted write` | zero `audit_logs` rows after a successful update |

(The helper `assertVersionRefusalAudited()` in the file checks the single audit row.)

**`tests/Feature/Api/RouteVersionCoverageTest.php`** (1 test, belongs to this feature; F04 lists it for the OpenAPI work and links here): every route whose URI starts with `api/v1/` and whose methods include PUT or PATCH must carry a middleware string starting with `version.token:`, except URIs on `$allowList` (empty today). It passes today because the real API has no PUT or PATCH route. It reads `$route->gatherMiddleware()`, which returns the **route's own** middleware strings, so it works for `version.token:x` written on the route (a name inside a group would not be expanded, which is why the alias is always written on the route).

Not covered: two real simultaneous requests on two database connections (the review noted NFR-CONC-1's "50 trials" test is not run; the lock makes it safe by construction; a two-connection test should come with the first real update endpoint); that an endpoint really calls `lockAndAssert` (no test can force it; it is on the PR checklist for every update endpoint); POST/DELETE routes (see gotchas).

Run: `vendor/bin/pest tests/Feature/Api/VersionTokenHelperTest.php tests/Feature/Api/VersionTokenTest.php tests/Feature/Api/RouteVersionCoverageTest.php`.

## 8. Gotchas and invariants

- **Send exactly what you received.** A client that rounds the microseconds gets 409 on its own latest version.
- **Always call `lockAndAssert` and edit the instance it returns.** The bound model from route binding may already be stale. After the save, build the response from `$locked->refresh()`.
- **`lockAndAssert` needs a transaction**; use `AuditService::transaction()` or `DB::transaction` and put the audit row in the same one (F10).
- **The middleware must not do the work of the endpoint's lock.** It runs outside the transaction; only `lockAndAssert` is race-safe.
- **POST "action" routes** (for example `POST users/{user}/deactivate` in the P2-E1 plan) change an existing record but are not PUT/PATCH, so `RouteVersionCoverageTest` does not force `version.token` on them. Add it by hand; the P2-E1 plan lists `version.token:user` on them.
- **DELETE with a version:** the middleware reads `version` from the request input (`$request->only('version')`), which includes the JSON body and, by Laravel's rules, also the query string (Unverified by a test). A client that sends `DELETE` with a body may find proxies dropping it; decide the delete rule when the first delete endpoint exists (records are not deleted in this system according to the P2-E1 plan).
- **A model without a working `updated_at` cast** makes `VersionToken::of` throw `LogicException`. Models in this project use `#[DateFormat('Y-m-d H:i:s.u')]` so microseconds survive; copy that when you add a model.
- **Message and code are frozen:** `VERSION_CONFLICT`, status 409 and key `errors.version_conflict` never change (contracts README, versioning rules). The body keeps `current: { id, version }`.
- **The 409 of business rules** (other conflicts) is a different code (P2-E1 plans a new `ErrorCode::Conflict`); do not reuse `VERSION_CONFLICT` for them: the client reacts to `VERSION_CONFLICT` by reloading.
- **Two writes in the same microsecond** could in theory share a token; the row lock and `DATETIME(6)` make it practically impossible (accepted, D4 plan section 7).
- **The audit row of the early 409 is written before the exception is thrown**, outside any transaction of the endpoint (the endpoint has not started yet), so it is always kept. In tests, the surrounding test transaction makes savepoints; the real behaviour on a separate connection is not tested (Unverified).

## 9. How to extend it

**Protect a new update route (worked example).**

1. Route (inside `port.api`, F11):

```php
Route::patch('tugboats/{tugboat}', [TugboatController::class, 'update'])
    ->middleware(['ability:tug-fleet.manage', 'version.token:tugboat'])
    ->name('tugboats.update');
```

2. Form request: validate the fields of the update. `version` is already validated by the middleware, so you do not need `VersionToken::rules()` again, but the field must be allowed in the request body.

3. Controller or service:

```php
public function update(UpdateTugboatRequest $request, Tugboat $tugboat, AuditService $audit): TugboatResource
{
    $saved = $audit->transaction(function () use ($request, $tugboat, $audit) {
        $locked = VersionToken::lockAndAssert($tugboat, $request->string('version')->toString());

        // business rules here: 409 (conflict) or 422
        // plan limits last: 422 PLAN_LIMIT_EXCEEDED

        $locked->update($request->safe()->except('version'));
        $audit->recordUpdated($locked, $request->user()->id);

        return $locked;
    });

    return new TugboatResource($saved->refresh());
}
```

4. Resource: `'version' => VersionToken::of($this->resource)` in `toArray`, on single records and list items.
5. OpenAPI: add `version` to the request and response schemas and the shared 409 and 422 responses (F04).
6. Tests: stale 409 with `current`, missing 422, a new `version` after the save, 403 before the version check. Copy `VersionTokenTest`'s shape.

**Allow a PUT/PATCH route without a version:** add its `uri()` to `$allowList` in `RouteVersionCoverageTest` with a comment saying why (a security decision: say it in the PR).

**Add a model that is versioned:** the table needs `updated_at DATETIME(6)` with `useCurrent()->useCurrentOnUpdate()`; the model needs the `updated_at` datetime cast and `#[DateFormat('Y-m-d H:i:s.u')]`.

**Change the token format:** a breaking change to CT-01 section 5 (approved contract: `contract-change` skill, F14), a major version, and a change of `PATTERN`.

## 10. Try it

From `project-backend/ATLANTECH`, PowerShell. These runs were done on 2026-11-04 on `develop` `106b40e`, with an own empty database `atlantech_testing_g4` (Docker MySQL was running).

```powershell
$env:DB_DATABASE="atlantech_testing_g4"; $env:DB_SCHEMA_TEST_DATABASE="atlantech_testing_g4"
vendor/bin/pest tests/Feature/Api/VersionTokenHelperTest.php tests/Feature/Api/VersionTokenTest.php tests/Feature/Api/RouteVersionCoverageTest.php tests/Feature/Api/WriteChainOrderTest.php
```
Real result: `27 passed (114 assertions)`, about 39 seconds.

The format, matching and 409 body, run in a small script that boots the app (no database needed), real output:

```
2026-11-02T10:15:04.123456Z
bool(false)                     <- matches('...123000Z')
["required","string","regex:\/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}\\.\\d{6}Z$\/"]
App\Modules\Shared\Errors\ApiProblemException 409 {"current":{"id":12,"version":"2026-11-02T10:15:04.123456Z"}}
```

An HTTP call against a running server: not run, expected: there is no real update endpoint to call yet. Use the test routes through Pest (`VersionTokenTest`). When the first endpoint exists, the shape is:

```bash
curl -X PATCH http://atlantech.test/api/v1/tugboats/12 -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "Origin: http://localhost:5173" -H "X-XSRF-TOKEN: <token>" --cookie "<session cookies>" \
  -d '{"version":"2026-11-02T10:15:04.123456Z","name":"Renamed"}'
```
Expected for a stale version: HTTP 409, `Content-Type: application/problem+json`, body with `code: VERSION_CONFLICT`, `message_key: errors.version_conflict` and `current`. (Cookie and CSRF steps: F07.)

## 11. Open points

- **No real endpoint uses it yet** and no resource returns `version` yet (P2-E1..E3, P2-F1..F5). The first one should add a two-connection race test (review note, NFR-CONC-1).
- **POST/DELETE action routes** are not covered by the coverage test (see gotchas). Consider widening `RouteVersionCoverageTest` to POST routes with a model parameter other than create, or a route tag.
- **The plan named `tests/Unit/Modules/Shared/VersionTokenTest.php`**; the helper tests are `tests/Feature/Api/VersionTokenHelperTest.php` because Unit tests here have no application (facades). The index (`00_INDEX.md`) names the right files.
- **Request source of `version`:** `$request->only('version')` reads all input, not only the JSON body; CT-01 says "body". Harmless, not tested.
- **Doc vs code:** the middleware's class comment says the `version` must be "in the JSON body"; code accepts any input source (see above). The D4 plan section 3 also says the 409 audit stays "in the endpoint's catch"; the final code reads `$response->exception` instead (the plan's progress log of 2026-11-04 explains the change). Code is right.
- **The audit row on a separate real connection** (outside the test transaction) is argued from the design but not run (Unverified; what to check: a test without the wrapping `DB::beginTransaction()` that fires the race route and counts `audit_logs`).
