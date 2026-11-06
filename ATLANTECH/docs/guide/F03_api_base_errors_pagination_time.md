# F03 · API base: errors, pagination, time

Task origin: P1-A2 (Sprint 1), plus later additions: `SESSION_EXPIRED` and `errors.auth.invalid_credentials` (P1-C1), the `page` upper bound (P1-A2 review), and its first real users in P2-F8. Facts first checked against `develop` at `106b40e`, updated on 2026-11-06 against `9559d35`, **and again against `58affe4` after IDEMP-01 (PR #62): `IDEMPOTENCY_KEY_REUSED` now has a thrower and `SERVICE_UNAVAILABLE` a new one**; the 2026-11-06 text that follows is unchanged unless it says otherwise (P1-C5, P1-C3, P1-C4 merged: one new code `DEVICE_REVOKED`, `AppVersion` and `MinAppVersionSource` helpers, two new routes). `ProblemRenderer`, `ApiProblemException`, `PageRequest`, `PaginatedCollection`, `ZonedDateTime` and the exception section of `bootstrap/app.php` did not change in that range. Paths are inside `project-backend/ATLANTECH/` unless stated. The contract is CT-01 (`PortHub/contracts/api-conventions.md` v1.5 and `error-codes.md` v1.4; the conventions were Approved 2026-10-30 and the catalogue gained `DEVICE_REVOKED` on 2026-11-06, DEC-60).

## 1. What it does

This feature defines **the shape every API response follows**, so the web, mobile and QA teams can write one error handler, one pager and one date parser.

- **A fixed base path:** every route is served under `/api/v1`.
- **One error format:** every error under `/api/*` is an RFC 9457 problem body (`application/problem+json`) with six always-present members, a stable `code` from a closed catalogue of 18, and no stack trace, class name or SQL, even when `APP_DEBUG=true`.
- **Message keys:** each code has a translation key (`errors.<lower_snake_code>`); the English text is in `lang/en/errors.php`. Clients branch on `code` and translate by `message_key`.
- **One list format:** `{ "data": [...], "meta": { "page", "per_page", "total" } }`, `page` 1 to 1 000 000, `per_page` 1 to 100 (default 25), out-of-range is 422 and never clamped.
- **UTC everywhere:** the app and the MySQL session run in UTC; output datetimes are `2026-11-02T10:15:04.123456Z`; input datetimes must carry `Z` or an offset, and a naive time is refused with 422.

It does **not** do: translations other than English (`lang/ar` waits for FR-GL-09; there is no `Accept-Language`), the checks of the write chain (F11), the version-token logic (F12, it only supplies the 409 shape), the OpenAPI file (F04), or any product endpoint of its own. The routes used by its tests exist only inside the tests.

## 2. Request or data flow

How an error becomes a response, for a request such as `GET /api/v1/does-not-exist`:

1. nginx or Herd passes the request to `public/index.php`, which boots `bootstrap/app.php` (F01 section 2).
2. `withRouting(... apiPrefix: 'api/v1')` (`bootstrap/app.php:27-33`) mounts `routes/api.php` under `/api/v1` with the `api` middleware group. `/up` stays outside the prefix.
3. No route matches. Laravel throws `NotFoundHttpException`.
4. Laravel's `Handler::render()` first calls `prepareException()`, which already converts several exceptions to HTTP exceptions (`ModelNotFoundException` to 404, `AuthorizationException` to 403, `TokenMismatchException` to 419, other `RequestExceptionInterface` to 400), **then** runs the callback registered in `withExceptions` (`vendor/laravel/framework/.../Handler.php:721-725`).
5. The callback (`bootstrap/app.php:77-79`) calls `ProblemRenderer::render($exception, $request)`.
6. `render()` returns `null` (leave it to Laravel) when the path is not `api/*` or the exception is an `HttpResponseException` (a response the code already built). Otherwise a `match` picks the branch (`ProblemRenderer.php:56-70`): `ApiProblemException`, `ValidationException`, `AuthenticationException`, any `HttpExceptionInterface` (by status), anything else (500).
7. `problem()` builds the body and a `JsonResponse` with `Content-Type: application/problem+json` and the code's status (`ProblemRenderer.php:77-95`).
8. `shouldRenderJsonWhen(api/* or expects JSON)` (`bootstrap/app.php:74-76`) keeps non-API requests that ask for JSON in Laravel's own JSON error format (checked by `ProblemResponseTest`: `/no-such-web-page` has no `code` member).

For a list endpoint: `PageRequest::fromRequest()` validates `page` and `per_page` (a bad value throws `ValidationException`, which becomes the 422 problem above), `PageRequest::paginate($query)` runs the query, `new PaginatedCollection($paginator)` shapes the response. For a datetime input: a validation rule list from `ZonedDateTime::rules()` checks the shape, then `ZonedDateTime::toUtc()` converts to a UTC instant.

## 3. Every piece of code

### 3.1 `/api/v1` prefix and middleware (`bootstrap/app.php`)

| Item | Where | What it does |
| --- | --- | --- |
| `withRouting(web, api, commands, health: '/up', apiPrefix: 'api/v1')` | `bootstrap/app.php:27-33` | Sets the prefix here, not in `routes/api.php`. A breaking change would add `/api/v2` beside it (CT-01 section 1). `routes/api.php` was added without `php artisan install:api` because that command installs Sanctum, a dependency change that belongs to P1-C1 (P1-A2 plan). |
| `$middleware->statefulApi()` | `bootstrap/app.php:35` | Sanctum's SPA support: requests from `SANCTUM_STATEFUL_DOMAINS` origins get the session and cookie middleware on API routes (F07). |
| `redirectGuestsTo(fn => $request->is('api/*') ? null : route('login'))` | `bootstrap/app.php:69-71` | A guest on an API path gets a 401 problem instead of a redirect. The non-API branch calls `route('login')`, which does not exist (no web login page): the web side is a stub. |
| `shouldRenderJsonWhen` and `exceptions->render(...)` | `bootstrap/app.php:73-80` | Section 3.4. |

The write-chain middleware group and priority list in the same file belong to F11.

`routes/api.php` today (on `9559d35`) holds `POST auth/login`, `POST auth/mobile/login`, `GET system/info` (these three need no sign-in), and, in the `port.api` group, `GET auth/me`, `POST auth/change-password`, `POST auth/logout` and `GET audit-logs` (F07 to F10). `ApiRoutingTest` proves that an unknown path gives a `NOT_FOUND` problem and that `/api/v1/up` is a 404 while `/up` is 200.

### 3.2 `App\Modules\Shared\Errors\ErrorCode` (the catalogue)

A string-backed enum (`ErrorCode.php`). The case value is the stable `code` in UPPER_SNAKE. Methods:

| Method | Returns | Rule |
| --- | --- | --- |
| `status(): int` | The HTTP status | A `match` over all cases. It has no default arm: a new case without an arm throws `UnhandledMatchError` the first time it is used. |
| `messageKey(): string` | `'errors.'.strtolower($this->value)` | For example `errors.version_conflict`. |
| `type(): string` | `TYPE_BASE_URI` (`https://atlantech.io/errors/`) plus the kebab-case code | An identifier, not a web page. |
| `title(): string` | A short fixed English title | A `match` over all cases (no default arm either). |

The 18 cases (the first 17 checked by running the enum, `DEVICE_REVOKED` read from the diff; same order as the enum, which the OpenAPI file and the tests also follow):

| Code | Status | Message key | Title | Raised by today |
| --- | --- | --- | --- | --- |
| `VALIDATION_FAILED` | 422 | `errors.validation_failed` | Validation failed | `ValidationException` (every Form Request and `validate()`), `PageRequest`, `EnsureRecordVersion` (invalid `version`) |
| `VERSION_CONFLICT` | 409 | `errors.version_conflict` | Record changed | `VersionToken::assertCurrent` and `lockAndAssert`, `EnsureRecordVersion` (F12) |
| `UNAUTHENTICATED` | 401 | `errors.unauthenticated` | Unauthenticated | `AuthenticationException`; `SignInService` (wrong credentials); `EnsureAbility` and `EnsureTerminalScope` for a guest |
| `SESSION_EXPIRED` | 419 | `errors.session_expired` | Session expired | A CSRF failure (`TokenMismatchException`, status 419) |
| `FORBIDDEN` | 403 | `errors.forbidden` | Forbidden | `AccessRefusal`, `TerminalScope`, `EnsureAbility`, `RestrictsToOwnTasks` (F09); any 403 the framework raises, including `AuthorizationException` and Laravel's origin-mismatch 403 |
| `NOT_FOUND` | 404 | `errors.not_found` | Not found | Unknown route; `ModelNotFoundException` |
| `METHOD_NOT_ALLOWED` | 405 | `errors.method_not_allowed` | Method not allowed | A known path with a wrong method (keeps the `Allow` header) |
| `ACCOUNT_DISABLED` | 403 | `errors.account_disabled` | Account disabled | `EnsureAccountUsable`; `SignInService` (F07, F08) |
| `PASSWORD_CHANGE_REQUIRED` | 403 | `errors.password_change_required` | Password change required | `EnsureAccountUsable` (F08) |
| `DEVICE_REVOKED` | 403 | `errors.device_revoked` | Device revoked | `MobileSignInService` (`MobileSignInService.php:52-54`): mobile sign-in from a device the Port Admin revoked; the refusal is audited and no token is issued (P1-C3, DEC-60, CT-04 v1.2). Text: "This device has been revoked by your port administrator. Contact your port administrator to use it again." A *token* of a revoked device on a later request is a 401, not this code (`EnsureTokenDevice`, F07 and F08) |
| `APP_UPDATE_REQUIRED` | 426 | `errors.app_update_required` | App update required | `EnsureMinAppVersion` (`EnsureMinAppVersion.php:43`, filled by P1-C3, F11) through `ApiProblemException::appUpdateRequired($minimum)`. |
| `LICENSE_READ_ONLY` | 423 | `errors.license_read_only` | License is read-only | Reserved (licence guard is future work, DEC-54) |
| `PLAN_LIMIT_EXCEEDED` | 422 | `errors.plan_limit_exceeded` | Plan limit exceeded | Reserved (DEC-54) |
| `TOO_MANY_ATTEMPTS` | 429 | `errors.too_many_attempts` | Too many attempts | `SignInService` (with `Retry-After`; web and mobile sign-in share it); `throttle:5,1` on change-password (a framework `ThrottleRequestsException`, status 429) |
| `IDEMPOTENCY_KEY_REUSED` | 422 | `errors.idempotency_key_reused` | Idempotency key reused | `IdempotentWrite` (a retried logout under another body) and `ReplayIdempotentWrite` (every other Bearer write): the same `Idempotency-Key` sent again with another body, path, query or method (IDEMP-01, F11 section 3.11, CT-01 v1.5 section 4). |
| `SERVICE_UNAVAILABLE` | 503 | `errors.service_unavailable` | Service unavailable | `GET /api/v1/system/info` when there is no port profile (`SystemInfoController.php:27-29`); `PortSettingMinAppVersion` when the `min_app_version` setting is missing (`PortSettingMinAppVersion.php:24`); `MobileSignInService.php:78` when the locked device row cannot be read; `IdempotentWrite` with `Retry-After: 1` when a request with the same `Idempotency-Key` is still running after the 5 second wait (IDEMP-01); and a 503 `HttpException`, for example maintenance mode |
| `SERVER_ERROR` | 500 | `errors.server_error` | Server error | Any other `Throwable` |
| `BAD_REQUEST` | 400 | `errors.bad_request` | Bad request | `AuthController::login` without a session (origin not stateful, `AuthController.php:32-34`); any other framework 4xx that has no catalogue code (413, 415 ...) |

Rules the enum itself keeps (`ErrorCodeTest`): UPPER_SNAKE values, status between 400 and 599, `errors.*` keys, `type` under `TYPE_BASE_URI` in kebab-case, a non-empty title, and no two codes share a key or a type.

Why status, key, type and title are methods and not data: one place per code, and `match` makes the compiler-level guarantee that nothing is forgotten.

### 3.3 `App\Modules\Shared\Errors\ApiProblemException`

`class ApiProblemException extends Symfony\Component\HttpKernel\Exception\HttpException`. **This is the exception domain code throws for a catalogue refusal.** Because it is an HTTP exception, Laravel does not write it to the log as an error.

Constructor `__construct(ErrorCode $errorCode, ?string $detail = null, array $replace = [], array $extensions = [], array $headers = [], ?Throwable $previous = null)`; all stored as `public readonly`:

| Argument | Meaning |
| --- | --- |
| `$errorCode` | The catalogue code. The HTTP status comes from it (`parent::__construct($errorCode->status(), ...)`). |
| `$detail` | English text that replaces the catalogue text (for example the sign-in text `errors.auth.invalid_credentials`). `null` means "use the translation of the message key". The exception's own message is `$detail ?? $errorCode->value`. |
| `$replace` | Placeholder values for the translation (`__($key, $replace)`). |
| `$extensions` | Extra problem members: `current` on 409, `min_app_version` on 426. |
| `$headers` | Extra response headers, for example `Retry-After`. |

It throws `InvalidArgumentException` if `$extensions` contains a name in `RESERVED_MEMBERS` (`type`, `title`, `status`, `code`, `message_key`, `detail`): an extension may never replace a core member.

Named constructors:

- `versionConflict(array $current, ?string $detail = null): self`: 409 with `extensions: ['current' => $current]`. `$current` carries at least `id` and `version` (F12 builds it).
- `appUpdateRequired(string $minAppVersion): self`: 426 with `extensions: ['min_app_version' => ...]`.

Rule for later code: **throw `ApiProblemException`, never `abort(409|422|423|426)`.** `abort()` with those statuses maps to `BAD_REQUEST` because the renderer's status table (section 3.4) only holds the statuses that have no extra members (P1-A2 review note 3).

### 3.4 `App\Modules\Shared\Errors\ProblemRenderer`

Registered in `bootstrap/app.php:77-79` as `app(ProblemRenderer::class)->render($exception, $request)`. Constants: `CONTENT_TYPE = 'application/problem+json'`; `CODE_BY_STATUS` (below); `FRAMEWORK_RULE_NAMESPACE = 'Illuminate\Validation\Rules\'`.

**`render(Throwable, Request): ?JsonResponse`** returns `null` for non-`api/*` paths and for `HttpResponseException`. Otherwise the first matching branch wins:

| Exception | Result |
| --- | --- |
| `ApiProblemException` | Its own code; `detail` is the thrower's `$detail` or `__($messageKey, $replace)`; its extension members; its headers. |
| `ValidationException` | 422 `VALIDATION_FAILED` with `errors` (below). Any `status` set on the exception is ignored: a code keeps one status. |
| `AuthenticationException` | 401 `UNAUTHENTICATED`. |
| Any `HttpExceptionInterface` | The code for its status via `CODE_BY_STATUS`, keeping the exception's headers (`Allow`, `Retry-After`). |
| Anything else | 500 `SERVER_ERROR`. |

`CODE_BY_STATUS`: 401 `UNAUTHENTICATED`, 403 `FORBIDDEN`, 404 `NOT_FOUND`, 405 `METHOD_NOT_ALLOWED`, 419 `SESSION_EXPIRED`, 429 `TOO_MANY_ATTEMPTS`, 503 `SERVICE_UNAVAILABLE`. Any other status below 500 is `BAD_REQUEST`; any other 5xx is `SERVER_ERROR`.

How each framework exception reaches the table (after Laravel's `prepareException()`; `ProblemResponseTest` proves each row):

| Raised | Becomes | Final code |
| --- | --- | --- |
| `AuthenticationException` | itself | `UNAUTHENTICATED` 401 |
| `AuthorizationException` (`Gate::authorize`, policies) | `AccessDeniedHttpException` 403 | `FORBIDDEN` |
| `ModelNotFoundException` (route binding, `findOrFail`) | `NotFoundHttpException` 404 | `NOT_FOUND` |
| Unknown path | `NotFoundHttpException` | `NOT_FOUND` |
| Wrong method | `MethodNotAllowedHttpException` 405 with `Allow` | `METHOD_NOT_ALLOWED`, `Allow: GET, HEAD` kept |
| `ThrottleRequestsException` | `TooManyRequestsHttpException` 429 with `Retry-After` | `TOO_MANY_ATTEMPTS`, `Retry-After` kept |
| `TokenMismatchException` | `HttpException` 419 | `SESSION_EXPIRED` |
| `HttpException(503)` | itself | `SERVICE_UNAVAILABLE` |
| `HttpException(413)` or another unlisted 4xx | itself | `BAD_REQUEST` |
| `RuntimeException`, `QueryException`, any other `Throwable` | itself | `SERVER_ERROR` |
| `HttpResponseException` | not touched | the response the code built (not a problem body) |

**`problem(ErrorCode $code, ?string $detail = null, array $members = [], array $headers = []): JsonResponse`** (public, reusable by other code). The body is built in this order: `type`, `title`, `status`, `code`, `message_key`, `detail` (`$detail ?? __($code->messageKey())`), then the `$members` (so extension members come last). Status is the code's status. Headers: `Content-Type: application/problem+json` and then `$headers`. JSON flags: `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` (URLs in `type` stay readable).

**Why `detail` is the catalogue text and never the exception message:** an exception message may contain SQL, a table name or a class name. The renderer never reads `$exception->getMessage()`. `ProblemResponseTest` checks a 500 with `APP_DEBUG=true` has no `exception`, `file`, `line`, `trace`, `message` and does not contain `SQLSTATE`, the table name or the class name; it also checks the 4xx cases never echo the message. Logging is separate: the exception is still reported to the log by Laravel (except `ApiProblemException`, which is an HTTP exception).

**`validationProblem(ValidationException)`** (private). Builds `errors` as `{ field: [message keys] }`:

1. For each field with messages, if the validator recorded the failed rules (`$validator->failed()`), each rule name becomes a key through `ruleMessageKey()`; otherwise (for example `ValidationException::withMessages()`) each message goes through `messageAsKey()`.
2. Keys are de-duplicated per field.
3. `detail` is the **first** error message (`errors()->first()`); if that message is itself a translation key (`Lang::has`) it is translated, otherwise it is used as is. If it is empty, the catalogue text is used.

`ruleMessageKey(string $rule)`: a plain rule name (`Required`) becomes `validation.` plus the snake case (`validation.required`); a Laravel rule object in the namespace `Illuminate\Validation\Rules\` (`Enum`, `File`, `Password`, `In`, ...) becomes `validation.` plus the snake-cased class name (`validation.enum`); any other class (a custom rule object, a closure rule) becomes `errors.validation_failed`. `messageAsKey(string $message)`: kept when it is a translation key, otherwise `errors.validation_failed`.

Examples, from `ValidationProblemTest`: `name=too long name` with `max:5` gives `errors.name = ["validation.max"]`; a wrong enum value gives `["validation.enum"]`; a closure rule gives `["errors.validation_failed"]`; `detail` is the English message of the first failure.

### 3.5 `lang/en/errors.php`

One flat entry per `ErrorCode` case, keyed by the code in lower snake case, no placeholders (for example `'version_conflict' => 'This record was changed by another user. Reload it and apply your changes again.'`). Each text ends with a full stop (checked by `ErrorMessagesTest`). It also holds one feature group, `'auth' => ['invalid_credentials' => 'The username or password is incorrect.']` (key `errors.auth.invalid_credentials`, used as the `detail` of the sign-in 401). The header comment gives the convention for feature keys: `errors.<area>.<name>`.

The rest of the `lang/` folder is not published: `validation.*` messages come from the framework's own files in `vendor` (`ErrorMessagesTest` checks `validation.required` still resolves). The `lang/` base folder was created by hand for this feature (approved, P1-A2 Q9).

### 3.6 `Shared/Http/Pagination/PageRequest.php`

`final readonly class PageRequest` with promoted public properties `int $page = 1` and `int $perPage = 25`.

| Member | Value or behaviour |
| --- | --- |
| `DEFAULT_PER_PAGE` | 25 |
| `MAX_PER_PAGE` | 100 |
| `MAX_PAGE` | 1 000 000. Far past any real list; keeps `(page - 1) * per_page` inside an integer offset (without it `page=9223372036854775807` overflowed the offset and returned another page's rows under a wrong `meta.page`; fixed after P1-A2 review 1). |
| `rules(): array` | `page => ['sometimes','integer','min:1','max:1000000']`, `per_page => ['sometimes','integer','min:1','max:100']`. Static, so a Form Request can merge them with its own filters (`AuditLogIndexRequest` does `...PageRequest::rules()`). |
| `fromRequest(Request): self` | Validates only `page` and `per_page` of the query string with `Validator::make($request->query(), rules())->validate()` and returns the object (missing values use the defaults). A bad value throws `ValidationException`, so the client gets the 422 problem. |
| `paginate(EloquentBuilder\|Relation\|QueryBuilder $query): LengthAwarePaginator` | `$query->paginate($this->perPage, ['*'], 'page', $this->page)`. The query must carry its own ordering: the class does not add one, so each endpoint states its order (CT-01 section 8). |

Rules: out of range is 422 and never clamped; a page past the end is a normal 200 with empty `data`.

`AuditLogIndexRequest::page()` builds the object by hand (`new PageRequest((int) query('page', 1), (int) query('per_page', 25))`) because the Form Request's `rules()` has already validated the values by that point. Calling `page()` on a Form Request that did not merge `PageRequest::rules()` would skip the bounds; always merge the rules.

### 3.7 `Shared/Http/Resources/PaginatedCollection.php`

`class PaginatedCollection extends ResourceCollection`. The constructor only narrows the type to `LengthAwarePaginator`. `paginationInformation(Request, array $paginated, array $default)` returns **only** `['meta' => ['page' => current_page, 'per_page' => per_page, 'total' => total]]`, all cast to int. The default `links` and Laravel's own `meta` are dropped. The resource's `data` key is the framework's standard wrapper for collections. Use it as is (returns the items' own arrays) or extend it and set `$collects` to the item resource, as `AuditLogCollection` does (F10).

`AppServiceProvider` calls `JsonResource::withoutWrapping()` (F01), so a **single** record resource has no `data` wrapper (CT-01 section 9), while a collection still shows `data` and `meta`. `PaginationTest` checks the exact structure `data[*]`, `meta.page`, `meta.per_page`, `meta.total` and nothing else.

### 3.8 `Shared/Http/Validation/ZonedDateTime.php`

`final class ZonedDateTime`, static helpers (no instances):

| Member | Behaviour |
| --- | --- |
| `PATTERN` | `/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z\|[+-]\d{2}:\d{2})$/`: date, `T`, time, optional 1 to 6 fraction digits, then `Z` or `±HH:MM`. |
| `rules(): array` | `['string', 'regex:'.PATTERN, 'date']`, to be spread into a field's rules: `'at' => ['required', ...ZonedDateTime::rules()]`. Shape first (a naive value, `2026-11-02 10:15:04`, a date alone, `+0200` without a colon, or 7 fraction digits fails with `validation.regex`), then a real calendar date and time (`2026-02-30T...` or hour 25 fail with `validation.date`). A non-string fails with `validation.string`. |
| `toUtc(string $value): CarbonImmutable` | `CarbonImmutable::parse($value)->utc()`. Call it only on a validated value. |

Why naive times are refused: the port has its own time zone (`port_profiles.timezone`), so a time without a zone is ambiguous. Shift times (local `TIME` columns) are not datetimes and do not use this class.

### 3.9 UTC rules (no code, configuration plus tests)

| Rule | Where it is set |
| --- | --- |
| The app runs in UTC | `config/app.php` `timezone` = `UTC` (F01) |
| The MySQL session is UTC | `config/database.php` `mysql.timezone` = `'+00:00'` (F01) |
| Output | Laravel serialises Carbon dates as ISO 8601 with 6 fraction digits and `Z`. No custom code. This is lossless for the `DATETIME(6)` version token (`updated_at`), which clients must treat as an opaque string (CT-01 section 5; F12). |
| Input | `ZonedDateTime` (above) |
| Stored value | `DATETIME(6)` columns hold the UTC value without a zone marker; AI workers must read them as UTC (AIBD-037) |
| Display | The client converts to local time (DEC-45) |

`UtcDateTimeTest` proves: `config('app.timezone')`, PHP's default time zone and `now()` are UTC; `@@session.time_zone` is `+00:00` (group `infra`); an offset input such as `2026-11-02T12:15:04+02:00` is stored as `2026-11-02 10:15:04` and returned as `2026-11-02T10:15:04.000000Z`; and the refusal cases listed above.

## 4. Why this design

| Choice | Reason | Source |
| --- | --- | --- |
| Build on the CT-01 draft before it was approved | The plan answers were the lead's choices; the contract was approved later (2026-10-30) with the 17 codes that existed in `ErrorCode` then. | P1-A2 Q1; `error-codes.md` change log |
| `updated_at DATETIME(6)` as the version token, output with microseconds | The later decision wins over the earlier integer `version`; microseconds are lossless. | P1-A2 Q2 and Q5; P1-B1 Q3; DEC-57 |
| Exactly `meta {page, per_page, total}`, default 25, max 100, 422 outside the range, empty page past the end | The lead's list shape; clamping would hide client bugs. | P1-A2 Q3; CT-01 section 8 |
| Test-only sample routes, nothing shipped | The done-when needed "a sample endpoint" but the product had no endpoint yet (`/system/info` is P1-C5). | P1-A2 Q4 |
| Initial catalogue = CT-01 list plus `METHOD_NOT_ALLOWED` and `PASSWORD_CHANGE_REQUIRED`; deactivated user is 403 `ACCOUNT_DISABLED`, not 401 | 405 is what Laravel returns for a wrong method; the password code is named for P1-C2; 403 follows FR-AC-01 and CT-03. `BAD_REQUEST` (400) was added in step 4 for other framework 4xx, and `SESSION_EXPIRED` (419) in P1-C1. | P1-A2 Q6; `ErrorCode.php` docblocks |
| `type` = `https://atlantech.io/errors/<kebab-code>`; all six members always present | The CT-01 example form; clients (and the QA schema check, NFR-INT-2) can rely on the members. | P1-A2 Q7 |
| Datetime input needs `Z` or an offset | A naive time is ambiguous for a port with a local zone. | P1-A2 Q8; AIBD-037 |
| `lang/` created by hand with only `errors.php` | `lang:publish` would add the framework's full set which is not needed; new base folder needed approval. | P1-A2 Q9 |
| `errors` values are message keys, not sentences; `detail` carries the first English message | Clients translate by key; people still get readable text. | P1-A2 Q10; CT-01 section 6 |
| `ApiProblemException` is an `HttpException` | It renders with its status and is not logged as an error. | `ApiProblemException.php` docblock |
| The renderer never reads exception messages | NFR-UX-4: no internals to the client, even in debug. | `ProblemRenderer.php` docblock |
| Shared depends on nothing | Every module throws these errors, so the kernel must sit at layer 1 (F01, A4). `AuditService` takes the actor as a plain integer for the same reason (F10). | `ModuleArchitectureTest` |

Rejected: a clamped `per_page`; `links` and Laravel's paginator fields; naive datetimes; a different exception class per code; publishing Laravel's whole `lang/` set; SQLite for tests (F02).

## 5. How it connects

- **Called by:** `bootstrap/app.php` (the renderer); every module that throws `ApiProblemException` (Access middleware and services, `EnsureRecordVersion`, `VersionToken`); `AuditLogController` and `AuditLogIndexRequest` (`PageRequest`, `ZonedDateTime`, `AuditLogCollection extends PaginatedCollection`).
- **Calls:** Laravel's translator (`__`, `Lang::has`), the validator (`failed()`, `errors()`), Carbon, `config('app.timezone')`.
- **Depended on by:** F04 (the OpenAPI file mirrors the catalogue and the paging bounds, and `OpenApiDriftTest` fails when they differ), F07 to F12 (every refusal), and the three client teams through CT-01.
- **Other repos:** Frontend (FE-030 HTTP client and error normalisation, FE-034 fixtures, FE-035 generated types, FE-103 error-state tests), Mobile (the 403 and 426 shapes), QA (CT-26 contract checks).
- **Module layer:** all code here lives in `Shared`, layer 1, and uses no other module.

## 6. Data

No tables, no migrations. The only database fact is the session time zone. `PaginationTest` and `UtcDateTimeTest` read and write existing tables (`cargo_types`, `tugboats`) inside a rolled-back transaction, through test-only routes.

## 7. Tests

All run with `vendor/bin/pest <file>`; the Feature ones need MySQL for the two that use a table (F02 section 3.13 for the database variables). I ran every file below in this session on `atlantech_testing_g1`: 122 tests, 699 assertions, all passed.

| Test file | Proves |
| --- | --- |
| `tests/Feature/Api/ApiRoutingTest.php` | An unknown path under `/api/v1` gives a `NOT_FOUND` problem with `application/problem+json`; `/up` is 200 and `/api/v1/up` is 404. |
| `tests/Feature/Api/ProblemResponseTest.php` | Each framework exception maps to its code and status; the body has exactly the six members; the problem is returned even without `Accept: application/json`; 405 keeps `Allow`; 429 keeps `Retry-After`; a 500 with `APP_DEBUG=true` leaks nothing; 4xx never echo the exception message or model class; an `HttpResponseException` passes through untouched; a non-API JSON 404 is Laravel's own. |
| `tests/Feature/Api/StableCodesTest.php` | `ACCOUNT_DISABLED`, `LICENSE_READ_ONLY`, `PLAN_LIMIT_EXCEEDED` render with status, key and English detail; `VERSION_CONFLICT` carries `current`; `APP_UPDATE_REQUIRED` carries `min_app_version`; a thrower's `detail` and `headers` are used. |
| `tests/Feature/Api/ErrorMessagesTest.php` | Every `ErrorCode` has an English text ending in a full stop; every flat key in `errors.php` belongs to a case (no orphans); `validation.required` still resolves. |
| `tests/Feature/Api/ValidationProblemTest.php` | 422 body with `errors` as message keys; a failed rule once per field; free text from `withMessages` becomes `errors.validation_failed`, translation keys are kept. |
| `tests/Feature/Api/PaginationTest.php` | Exactly `data` and `meta`; default 25; page 2 of 10 returns the right rows; `per_page=100` is allowed; a page past the end (9 and 1 000 000) is 200 with empty `data`; nine invalid values are 422 with the expected key (`per_page` 101, 0, `abc`, 2.5; `page` 0, -1, `abc`, 1 000 001, 9223372036854775807). Needs MySQL (30 `cargo_types` rows). |
| `tests/Feature/Api/UtcDateTimeTest.php` | UTC in app, PHP and MySQL session (the last is group `infra`); offset to UTC round trip through a `DATETIME(6)` column; eight refusal cases. Needs MySQL. |
| `tests/Unit/Modules/Shared/Errors/ApiProblemExceptionTest.php` | The status comes from the code; `detail`, `replace` and headers are kept; `versionConflict` and `appUpdateRequired` extensions; a reserved extension name throws `InvalidArgumentException`. |
| `tests/Unit/Modules/Shared/Errors/ErrorCodeTest.php` | The exact list of 18 codes with statuses (order matters, it is compared with `toBe`); shape rules for value, status, key, type and title; the CT-01 and CT-03 examples; no duplicate key or type. |

Not covered by any test (see section 11): the `AuthenticationException` branch on a real guarded route with a Bearer token, a `redirectGuestsTo` check for a web path, and OriginMismatch 403.

## 8. Gotchas and invariants

- **Adding a case to `ErrorCode` breaks three things at once** (the enum order test, the OpenAPI drift test, the translation test). That is the point: step 9 lists them.
- **Order matters.** `ErrorCodeTest` and `OpenApiDriftTest` compare lists in enum order. Put a new case at the **same position** in the enum, in the `ErrorCodeTest` list and in the OpenAPI `ErrorCode.enum`. The default is the end; P1-C3 inserted `DEVICE_REVOKED` right after `PASSWORD_CHANGE_REQUIRED` (next to the other 403 codes) and changed the three lists at that place. Both work as long as the three agree.
- **A code, its status and its key never change** once CT-01 is approved. If a status is wrong, that is a contract change (skill `contract-change`), not a refactor.
- **Never `abort(409)`, `abort(422)`, `abort(423)`, `abort(426)`.** They render as 400 `BAD_REQUEST`. Throw `ApiProblemException`.
- **Never build an error response by hand under `/api`** (`response()->json(['error' => ...], 400)`). It is not RFC 9457. `HttpResponseException` passes through untouched on purpose, so a hand-built response would reach the client unchanged.
- **Do not put internals in `$detail`.** The thrower's `detail` is sent to the client as is. Use English text a user can read.
- **Extension members never replace core members** (`RESERVED_MEMBERS`). Do not reuse the names `type`, `title`, `status`, `code`, `message_key`, `detail`.
- **A closure or custom rule object gives `errors.validation_failed`**, not a specific key, until custom rules carry their own `errors.<area>.<name>` key (see section 11).
- **`ValidationException` always renders 422** even if someone sets another status.
- **`PageRequest::paginate()` adds no `orderBy`.** An unordered query gives unstable pages. Always order, with a unique tie-break (`AuditLogController` orders by `occurred_at` then `id`).
- **Merge `PageRequest::rules()`** into any Form Request that uses `new PageRequest(...)` directly, or the bounds are not enforced.
- **Times in input must have a zone.** A client that sends `2026-11-02T10:15:04` gets 422 `validation.regex`. Do not "fix" it by assuming UTC.
- **Treat `version` as an opaque string.** JavaScript `Date` keeps milliseconds only and would round the microseconds.
- **Headers on a problem are merged after `Content-Type`.** A thrower that passes its own `Content-Type` header would override the problem content type; do not.
- **The renderer sees only `api/*` paths.** A route outside the API prefix gets Laravel's default error format.

## 9. How to extend it

### 9.1 Add a new error code, step by step

Do this in one pull request (CT-01 says so). Example: `EXPORT_TOO_LARGE`, status 422.

1. **Contract first.** Because CT-01 is approved, adding a code is a controlled change: use the `contract-change` skill. In PortHub it means a new row in `contracts/error-codes.md` (code, key, status, meaning, introduced-by task) and a minor version bump of that file, plus the status table row in `api-conventions.md` section 7 when the status is new. This goes in the paired PortHub PR (F04, F14).
2. **Enum case.** In `app/Modules/Shared/Errors/ErrorCode.php`, add `case ExportTooLarge = 'EXPORT_TOO_LARGE';` **at the end** (or next to related codes, keeping the same place in the other two lists), with a one-line docblock if it is reserved or special.
3. **Status.** Add the case to the `match` in `status()`.
4. **Title.** Add the case to the `match` in `title()` (short, English, fixed).
5. **Translation.** In `lang/en/errors.php` add `'export_too_large' => 'Plain English sentence that ends with a full stop.',` (the key is the code in lower snake case; the file is flat for codes, grouped for feature keys).
6. **Unit test.** In `tests/Unit/Modules/Shared/Errors/ErrorCodeTest.php` add the code and status to the expected list (at the end). `ErrorMessagesTest` then proves the translation exists and ends with a full stop.
7. **OpenAPI** (F04): add the value at the same position in `components.schemas.ErrorCode.enum`; add `components.responses.ExportTooLarge` (the response name is the **case name**, PascalCase) with an `application/problem+json` example whose `status`, `code`, `message_key`, `title`, `type` and `detail` match the code and `lang/en`; bump `info.version` (minor). `OpenApiDriftTest` fails until this is done.
8. **Throw it** where the rule is enforced: `throw new ApiProblemException(ErrorCode::ExportTooLarge)` (add `detail:` or `extensions:` if needed; extra members need a schema like `VersionConflictProblem` in the OpenAPI file).
9. **Framework status mapping.** Only if a *framework* exception with this status should become the code, add `status => ErrorCode::X` to `CODE_BY_STATUS` in `ProblemRenderer`. Usually not needed.
10. **Feature test.** Add a test that hits the real endpoint and checks `code`, status and `message_key` (see `StableCodesTest` for the pattern with a test-only route).
11. Run `vendor/bin/pest tests/Unit/Modules/Shared tests/Feature/Api`, then Pint.

### 9.2 Add a feature-specific message key (not a new code)

Add it under a group in `lang/en/errors.php`: `'berth' => ['loa_exceeds_length' => '...']`, key `errors.berth.loa_exceeds_length`. Use it as the `detail` text of an existing code (`new ApiProblemException(ErrorCode::ValidationFailed, __('errors.berth.loa_exceeds_length'))`) or as a validation message. Add the row to the "Feature-specific keys" table of PortHub `error-codes.md`. Note: `ErrorMessagesTest` only forbids unknown **flat** keys; groups are free.

### 9.3 Add a paginated list endpoint

1. Form Request with `...PageRequest::rules()` plus the filters (copy `AuditLogIndexRequest`).
2. Item resource and a collection class that `extends PaginatedCollection` with `public $collects = ItemResource::class;`.
3. Controller: build a query **with explicit ordering**, `return new MyCollection($request->page()->paginate($query));`.
4. OpenAPI: `$ref` the `Page` and `PerPage` parameters, response `{ data, meta: PageMeta }`, and the error responses (F04).

### 9.4 Accept a datetime in a request

Rules: `'starts_at' => ['required', ...ZonedDateTime::rules()]`; use `ZonedDateTime::toUtc($validated['starts_at'])` before comparing or storing; return the model's date attribute (Laravel formats it with `Z` and microseconds).

### 9.5 Add a translation language

Not planned (FR-GL-09). The keys already work: add `lang/ar/errors.php` with the same keys, and a locale switch (there is none today).

## 10. Try it

From `project-backend/ATLANTECH`, PHP from Herd (PowerShell here).

Routes under the prefix (I ran this; the command is read-only):

```bash
php artisan route:list --except-vendor
```
Output: `GET|HEAD /` (welcome page) and, on `106b40e`, the five `api/v1/...` routes `audit-logs`, `auth/change-password`, `auth/login`, `auth/logout`, `auth/me` ("Showing [6] routes"). I re-ran it on `9559d35`: seven `api/v1` routes (those five plus `auth/mobile/login` and `system/info`), "Showing [8] routes".

The catalogue and one rendered problem (I ran it through `artisan tinker` with a script file; no database access):

```php
foreach (App\Modules\Shared\Errors\ErrorCode::cases() as $c) { echo $c->value.' '.$c->status().' '.$c->messageKey().PHP_EOL; }
echo app(App\Modules\Shared\Errors\ProblemRenderer::class)->problem(App\Modules\Shared\Errors\ErrorCode::Forbidden)->getContent();
echo App\Modules\Shared\Http\Validation\ZonedDateTime::toUtc('2026-11-02T12:15:04+02:00')->format('Y-m-d\TH:i:s.u\Z');
```
Output (shortened; this run was on `106b40e`, so it printed 17 lines and no `DEVICE_REVOKED`; on `9559d35` the loop prints 18, with `DEVICE_REVOKED 403 errors.device_revoked` after `PASSWORD_CHANGE_REQUIRED`, which I did not re-run): from `VALIDATION_FAILED 422 errors.validation_failed` to `BAD_REQUEST 400 errors.bad_request`, then
`{"type":"https://atlantech.io/errors/forbidden","title":"Forbidden","status":403,"code":"FORBIDDEN","message_key":"errors.forbidden","detail":"You do not have permission to do this."}`
and `2026-11-02T10:15:04.000000Z`.

The tests for this feature (I ran them on my own database; see F02 section 3.13 for the variables):

```bash
vendor/bin/pest tests/Unit/Modules/Shared tests/Feature/Api/ApiRoutingTest.php tests/Feature/Api/ProblemResponseTest.php tests/Feature/Api/StableCodesTest.php tests/Feature/Api/ErrorMessagesTest.php tests/Feature/Api/ValidationProblemTest.php tests/Feature/Api/PaginationTest.php tests/Feature/Api/UtcDateTimeTest.php
```
Together with the F04 test files, my run on `106b40e` printed `tests 122, passed 122, assertions 699`; the re-run on `9559d35` (plus `SystemInfoTest` and `RouteAbilityCoverageTest`) printed `tests 136, passed 136, assertions 734`.

A real 404 problem over HTTP (**expected, not run:** needs the server profile or Herd):

```bash
curl -i http://atlantech.test/api/v1/nothing-here
```
Expect `HTTP/1.1 404`, `Content-Type: application/problem+json`, and a body with `"code":"NOT_FOUND"` and `"message_key":"errors.not_found"`.

A 422 over HTTP (**expected, not run**; sign in first, see F07): `GET /api/v1/audit-logs?per_page=101` with a Port Admin session gives `422` with `"errors":{"per_page":["validation.max"]}`.

## 11. Open points

- **F3-1 · Custom rules have no key of their own.** A closure or custom rule object fails as `errors.validation_failed`. The code comment says "until custom rules carry their own key", but no mechanism exists yet (no interface or convention for `errors.<area>.<name>` from a rule). The first feature rule (for example a berth length check) must decide it.
- **F3-2 · `ZonedDateTime` checks the offset shape only.** `+25:00` passes the pattern and PHP parses it (P1-A2 review note 7). Harmless today; tighten to `±(0\d|1[0-4]):[0-5]\d` if wanted.
- **F3-3 · Framework 409, 422, 423, 426 via `abort()` become 400.** By design (section 8), but there is no test or architecture rule that forbids `abort(409)` in the code base.
- **F3-4 · No guard against hand-built error responses.** `HttpResponseException` passes through. A code review rule only.
- **F3-5 · Untested branches.** The API-guest 401 behind a real `auth` middleware is covered only indirectly (the Access tests); there is no test that `redirectGuestsTo` returns `null` for `api/*` alone; `OriginMismatchException` (Laravel's origin check) would render as `FORBIDDEN`, no test.
- **F3-6 · Reserved codes with no thrower.** `LICENSE_READ_ONLY` and `PLAN_LIMIT_EXCEEDED` (DEC-54) are defined, translated and documented but nothing raises them yet (`IDEMPOTENCY_KEY_REUSED` was in this list until IDEMP-01 on 2026-11-06) (the licence hooks are in F11). `APP_UPDATE_REQUIRED` is raised since P1-C3.
- **F3-7 · `lang/ar` and `Accept-Language`** wait for FR-GL-09.
- **F3-8 · Web login route.** `redirectGuestsTo` references `route('login')` for non-API paths; no such route exists (skeleton default, P1-A2 review note 6).
- **F3-10 · Added 2026-11-06.** `DEVICE_REVOKED` was added "pending the CT-04 change that names it" (docblock in `ErrorCode.php`); the contract side is now done (`error-codes.md` v1.3, DEC-60), so the docblock is stale. `MinAppVersionSource` (interface in `Shared/Support`, bound by MasterData) and `AppVersion` (`x.y.z` regex `/^\d+\.\d+\.\d+$/`, `version_compare`) are the two new Shared helpers; they belong to F11 and F07, they only raise `ApiProblemException`. `ProblemRenderer` needed no change for any of it.
- **F3-9 · Count in older documents.** The P1-A4 plan and its first OpenAPI draft say 16 codes; the code and the approved `error-codes.md` v1.0+ have 17 (with `BAD_REQUEST`), and 18 since P1-C3 (`DEVICE_REVOKED`). The code wins.
