# F04 · The OpenAPI contract

Task origin: P1-A4 (Sprint 1), kept current by every endpoint task since (P1-C1, P1-C2, P2-F8). Facts first checked against `develop` at `106b40e` (file at 1.2.0, 5 paths), **updated on 2026-11-06 against `9559d35`: the file is at `info.version` 1.6.0 with 7 paths** (P1-C5 system info, P1-C3 mobile sign-in and Bearer security, P1-C4 sign-out, P1-C3 review fixes). Paths are inside `project-backend/ATLANTECH/` unless stated. Contracts involved: **CT-02** (Port API OpenAPI file, Approved v1.0.0 on 2026-10-30), **CT-01** (the conventions it describes, see F03) and **DEC-13** (Closed 2026-10-29, Option 1).

## 1. What it does

`openapi/port-api.yaml` is **the written description of the port API**: every path, request field, response and error, in the OpenAPI 3.0 format. The Vue and Flutter teams generate their types and mocks from it, and QA derives its contract tests from it. The Backend Team is its single owner (DEC-13).

This feature has three parts:

- **The file** with the shared building blocks (problem schemas, one response per error code, paging parameters and `PageMeta`, security schemes, mobile headers) and the real paths.
- **Two automatic guards** that keep the file true: `OpenApiDriftTest` (routes and error codes against the file) and the Spectral lint in CI (job `openapi`, see F02).
- **A publishing command**, `php artisan atlantech:contracts-openapi` (`composer contracts:openapi`), that copies the file to PortHub `contracts/openapi/port-api.yaml`, which is where the other teams read it.

It does **not** do: generate the file from code (it is hand-written; Scramble was considered and rejected), check field-by-field that a schema equals the real response (that stays a review step, the `openapi-sync` skill), serve the file over HTTP or render docs UI, version the API (that is the `/api/v1` prefix, F03), or approve the contract text (CT-02's own task did that).

## 2. Request or data flow

There is no request. The flow is a **change flow** for any pull request that adds or changes an endpoint:

1. The developer adds or changes the route in `routes/api.php`, the Form Request and the resource (F07 to F12 show examples).
2. In the same PR, they edit `openapi/port-api.yaml`: the path and operation, request body, success schema, one `$ref` per error the endpoint can return, and a minor bump of `info.version` (section 3.7).
3. `OpenApiDriftTest` runs in job `tests` and fails if a route has no path, a path has no route, or the shared parts no longer equal the code (section 3.3).
4. Job `openapi` runs Spectral on the file and fails on a warning (section 3.6).
5. The author runs `composer contracts:openapi`. The command copies the file to `../../contracts/openapi/port-api.yaml` (the PortHub checkout above the backend, `PublishOpenApi.php:26`).
6. On a PortHub branch (named like `feature/<task-id>-openapi`), they commit that copy and open a **paired PortHub PR** into `main`, link it from the backend PR (PR template, F02 section 3.5) and from the readiness pack (CT-23). Frontend and Mobile are requested as reviewers for critical changes only.
7. The backend PR merges into `develop`. Later, one PortHub PR moves the `project-backend` submodule pointer (A2); until then the PortHub copy is ahead of or equal to the pointer, never behind the merged source.

The pairing matters because PortHub holds the copy the other teams read, while only the backend repo can run the drift test and Spectral (CT-02 plan, P1-A4 Q1).

## 3. Every piece of code

### 3.1 `openapi/port-api.yaml`: structure

943 lines, OpenAPI `3.0.3`. Top level, in order:

| Key | Content |
| --- | --- |
| `openapi` | `3.0.3` (the drift test accepts any `3.0.x`). |
| `info` | `title: ATLANTECH Port API`, `version: '1.2.0'`, a description that restates the CT-01 rules (prefix, UTC times, problem format, paging, opaque version token), `contact: ATLANTECH Backend Team`. |
| `servers` | One entry, `url: /` ("paths carry the full /api/v1 prefix"). Paths therefore read `/api/v1/auth/login`, exactly as in the CT-02 example. |
| `tags` | `Auth` (web sign-in with the Sanctum SPA session, and mobile sign-in with a device token; signed-in routes take either the cookie or `Authorization: Bearer`), `Audit` and `System` (public port information for the mobile app). A new area adds its tag. |
| `paths` | Seven paths (section 3.2). |
| `components` | `securitySchemes`, `parameters`, `headers`, `schemas`, `responses`. |

The file starts with a comment block stating the source-of-truth rule, the drift test, the publish command and the version rule. The header comment is part of what is copied to PortHub.

### 3.2 The paths today

| Path and method | `operationId` | Security | Errors referenced | Owner feature |
| --- | --- | --- | --- | --- |
| `POST /api/v1/auth/login` | `authLogin` | `security: []` (open) | 400, 401, 403 `AccountDisabled`, 419, 422, 429 | F07 |
| `POST /api/v1/auth/mobile/login` | `authMobileLogin` | `security: []`; parameters `AppVersion`, `DeviceId` | 200 `MobileSignIn`; 401; 403 described inline with two examples (`ACCOUNT_DISABLED`, `DEVICE_REVOKED`); 422; 426 `AppUpdateRequired`; 429 | F07 |
| `GET /api/v1/auth/me` | `authMe` | `cookieAuth` **or** `bearerAuth` | 401, 403 `AccountDisabled`, 422, 426 | F07 |
| `POST /api/v1/auth/change-password` | `authChangePassword` | `cookieAuth` or `bearerAuth`; `XsrfToken` | 401, 403 `AccountDisabled`, 419, 422, 426, 429 | F08 |
| `POST /api/v1/auth/logout` | `authLogout` (204) | `cookieAuth` or `bearerAuth`; `XsrfToken` | 401, 403 `AccountDisabled`, 419, 422, 426 | F07 |
| `GET /api/v1/audit-logs` | `auditLogsList` | `cookieAuth` or `bearerAuth` | 401, 403 `Forbidden`, 422, 426 | F10 |
| `GET /api/v1/system/info` | `systemInfo` | `security: []` (public, never 426) | 200 `SystemInfo`; 503 `ServiceUnavailable` (no port profile or no `min_app_version` setting) | F11 and MasterData |

On the signed-in paths the `security` list has two alternatives (`- cookieAuth: []` and `- bearerAuth: []`), which OpenAPI reads as "either one". The `422` and `426` responses on those paths come from the Bearer rules: a Bearer request must send `X-App-Version` (`x.y.z`) and `X-Device-Id`; a missing or malformed value is 422 and a version older than `min_app_version` is 426 (1.6.0 added the 422). A web request may omit `X-App-Version`. Behaviour of the mobile paths themselves is in F07, F08 and F11.

Conventions in an operation: `operationId` is the route name in camelCase; `tags`; `summary`; a `description` that tells the **behaviour a client needs** (throttle limits, the allow-list while `must_change_password` is set, filters); `parameters` with `$ref` to shared parameters; `requestBody` with a `$ref` schema; `responses` keyed by status, each error a `$ref` to `#/components/responses/<ErrorCode case>`. `GET /api/v1/audit-logs` is the model for a **list** endpoint: `$ref` parameters `Page` and `PerPage`, its own query parameters inline, and a `200` with `{ data: [AuditLogEntry], meta: PageMeta }`.

### 3.3 `components`

**`securitySchemes`**

| Name | Definition | Notes |
| --- | --- | --- |
| `cookieAuth` | `type: apiKey`, `in: cookie`, `name: atlantech_session` | The Laravel session cookie of the Sanctum SPA flow (CT-03). The name must equal `config('session.cookie')` (env `SESSION_COOKIE`, default `atlantech_session`); a drift test checks it. Writes also send `X-XSRF-TOKEN`. |
| `bearerAuth` | `type: http`, `scheme: bearer` | Mobile personal access token (CT-04). Listed as an alternative on every signed-in path since 1.4.0; its description states the header rules (422, 401, 426). |

**`parameters`**: `Page` (query, integer 1 to 1 000 000, default 1), `PerPage` (query, integer 1 to 100, default 25), `XsrfToken` (header `X-XSRF-TOKEN`, required, for web writes), and three mobile headers (`AppVersion` and `DeviceId` are used by the mobile sign-in path since 1.4.0, `IdempotencyKey` by no path yet): `AppVersion` (`X-App-Version`), `DeviceId` (`X-Device-Id`), `IdempotencyKey` (`Idempotency-Key`, UUID, described with the replay rule and `IDEMPOTENCY_KEY_REUSED`).

**`headers`**: `Allow` (methods the path supports; used by `MethodNotAllowed`) and `RetryAfter` (seconds; used by `TooManyAttempts` and `ServiceUnavailable`).

**`schemas`** (15):

| Schema | Purpose |
| --- | --- |
| `DateTime` | `string`, `format: date-time`, UTC with microseconds on output, input must carry `Z` or an offset. Also the type of the version token. |
| `PageMeta` | Required `page`, `per_page`, `total` (`per_page` maximum 100, `total` minimum 0). |
| `ErrorCode` | String enum of all 18 codes, in `ErrorCode` order (`DEVICE_REVOKED` sits after `PASSWORD_CHANGE_REQUIRED`). |
| `Problem` | The RFC 9457 body. Required `type` (uri), `title`, `status` (400 to 599), `code` (`$ref ErrorCode`), `message_key` (pattern `^errors\.[a-z0-9_]+(\.[a-z0-9_]+)?$`), `detail`. |
| `ValidationProblem` | `allOf` `Problem` plus required `errors`: object from field name to an array of message keys. |
| `VersionConflictProblem` | `allOf` `Problem` plus required `current { id: integer, version: DateTime }`. |
| `AppUpdateRequiredProblem` | `allOf` `Problem` plus required `min_app_version: string`. |
| `LoginRequest`, `ChangePasswordRequest`, `MobileLoginRequest` | Request bodies (F07, F08). `MobileLoginRequest` requires `username`, `password` and `platform` (`ANDROID` or `IOS`). |
| `MobileSignIn` | The mobile sign-in answer: required `token` (opaque device token), `expires_at` (`DateTime`; each use moves it 30 days later), `user` (`UserProfile`). |
| `SystemInfo` | Required `port_id` (uuid, `port_profiles.public_id`), `port_code` (nullable), `port_name`, `license_mode` (`SETUP`, `FULL`, `READ_ONLY`; always `FULL` until the licence work), `token_warning_days_left` (nullable; always null for now), `min_app_version` (`x.y.z`). |
| `UserProfile` | `id`, `name`, `role` (nullable enum of the 12 roles), `terminal_ids`, `must_change_password`. |
| `AuditAction` | Enum of the six audit actions (F10). |
| `AuditLogEntry` | One audit row (F10). |

**`responses`** (18, one per `ErrorCode` case, named by the **case name in PascalCase**): `ValidationFailed`, `VersionConflict`, `Unauthenticated`, `SessionExpired`, `Forbidden`, `NotFound`, `MethodNotAllowed`, `AccountDisabled`, `PasswordChangeRequired`, `DeviceRevoked`, `AppUpdateRequired`, `LicenseReadOnly`, `PlanLimitExceeded`, `TooManyAttempts`, `IdempotencyKeyReused`, `ServiceUnavailable`, `ServerError`, `BadRequest`. Each has `description` (`'<status> <CODE>'`, reserved ones add a note), content `application/problem+json` with a `schema` (`Problem`, or `ValidationProblem`, `VersionConflictProblem`, `AppUpdateRequiredProblem`) and an `example` whose `type`, `title`, `status`, `code`, `message_key` and `detail` are taken from `ErrorCode` and `lang/en/errors.php`. `MethodNotAllowed` declares the `Allow` header; `TooManyAttempts` and `ServiceUnavailable` declare `Retry-After`.

### 3.4 `tests/Feature/Api/OpenApiDriftTest.php`: exactly what makes it fail

It parses the file with `Symfony\Component\Yaml\Yaml::parseFile` (the `symfony/yaml` package is a `require-dev` dependency added for this) and has six tests. Helper functions `openApi()`, `openApiOperations(array $spec)` and `apiRouteOperations()` are declared in the file.

| Test | Fails when |
| --- | --- |
| `is an OpenAPI 3.0 file with a semantic version` | `openapi` does not start with `3.0.`, `info.version` is not `N.N.N`, or `paths` is missing. |
| `lists exactly the routes the server serves under /api/v1` | The sorted list of `METHOD /path` strings in `paths` (`get`, `put`, `post`, `delete`, `patch`, `options`) differs from the sorted list of every registered route whose URI starts with `api/v1` (methods without `HEAD`). So: a new route with no path; a path with no route; a different method; or a different path template (the parameter name inside `{...}` must be the route's, for example `{berth}`). Routes that use `Route::any` or `match` count each of their methods. |
| `lists exactly the ErrorCode values, in order` | `components.schemas.ErrorCode.enum` is not identical (values and order) to `ErrorCode::cases()`. |
| `has one problem response per ErrorCode ...` | A case has no `components.responses.<CaseName>`; or its `application/problem+json` example differs from the enum in `status`, `code`, `message_key`, `title` or `type`; or there is a response that is not a case name (the key list is compared with `toEqualCanonicalizing`). |
| `describes the pagination bounds PageRequest enforces` | `Page` minimum, maximum or default is not 1, `PageRequest::MAX_PAGE`, 1; `PerPage` minimum, maximum or default is not 1, `MAX_PER_PAGE`, `DEFAULT_PER_PAGE`; or `PageMeta.required` is not `[page, per_page, total]`. |
| `names the session cookie the server really sets` | `cookieAuth.in` is not `cookie` or `cookieAuth.name` differs from `config('session.cookie')`. |

**What it does not check** (so a reviewer must): that `info.version` was bumped; per-operation `security`, parameters, request bodies and response schemas against the code; `description` text of responses; the PortHub copy; the order of paths; that `required` lists match the Form Request rules.

### 3.5 `app/Modules/Shared/Console/PublishOpenApi.php` and `tests/Feature/Console/PublishOpenApiTest.php`

`class PublishOpenApi extends Illuminate\Console\Command`, registered by `SharedServiceProvider::boot()` when `runningInConsole()` (F01). Signature: `atlantech:contracts-openapi {--output= : ...}`. Composer script: `composer contracts:openapi`. Description: "Publish openapi/port-api.yaml to PortHub contracts/openapi (CT-02)".

`handle(): int`:

1. Target: `--output` if given, else `base_path('../../contracts/openapi/port-api.yaml')`. From `PortHub/project-backend/ATLANTECH` that is `PortHub/contracts/openapi/port-api.yaml`. This only works inside the PortHub checkout; elsewhere pass `--output`.
2. If the target folder does not exist **and** its parent does not exist either, print "Folder not found: ... Run from the PortHub checkout or pass --output." and return `FAILURE`. (So it never invents a whole `contracts/` tree.)
3. If only the last folder (`openapi/`) is missing, create it with `mkdir`; on failure print "Could not create ..." and return `FAILURE`.
4. `copy(base_path('openapi/port-api.yaml'), $target)`; on failure print "Could not write ..." and return `FAILURE`.
5. Print `Wrote <real path>.` and return `SUCCESS`.

The copy is **byte for byte**; nobody edits the PortHub file by hand. A review of P1-A4 added the failure checks in 3 and 4 (before, a failed copy still printed success).

`PublishOpenApiTest`: (a) copies to a temp folder `--output .../openapi/port-api.yaml` and the file content equals the source (the `openapi/` folder is created); (b) when the parent `missing/` does not exist, the command fails and writes nothing. Neither test needs MySQL.

### 3.6 `.spectral.yaml` and the CI job `openapi`

`ATLANTECH/.spectral.yaml`:

```yaml
extends: [[spectral:oas, recommended]]
rules:
  oas3-unused-component: off
```

`spectral:oas` recommended is Spectral's built-in OpenAPI ruleset (valid `$ref`s, unique `operationId`s, defined tags and similar hygiene rules; I did not list its rules one by one). The only change is switching off `oas3-unused-component`, because shared components wait for endpoints that will use them: on 1.6.0 these components are unreferenced: the parameter `IdempotencyKey` and the responses `VersionConflict`, `NotFound`, `MethodNotAllowed`, `PasswordChangeRequired`, `DeviceRevoked`, `LicenseReadOnly`, `PlanLimitExceeded`, `IdempotencyKeyReused`, `ServerError` (I counted them with a script that looks for `#/components/<kind>/<name>`; on 1.2.0 the list was longer, because the mobile parameters, `bearerAuth`, `AppUpdateRequired` and `ServiceUnavailable` have been used since P1-C3 and P1-C5; the security schemes are referenced by name under `security`, not by `$ref`). Turning the rule on would make the job fail.

CI (`.github/workflows/ci.yml:54-70`, F02 section 3.1): Node 22, `npx --yes @stoplight/spectral-cli@6.17.0 lint openapi/port-api.yaml --fail-severity=warn`. The version is pinned, `--fail-severity=warn` makes warnings fail, and the ruleset is found in the working directory. To bump Spectral, change the number in `ci.yml` and re-run it locally.

### 3.7 Version rule (`info.version`)

Semver, kept by hand (nothing automatic enforces it). Decided in P1-A4 Q6 and restated in the file header and the `openapi-sync` skill:

- **Minor bump** (`1.2.0` to `1.3.0`) for each PR that adds or changes an endpoint without breaking a client.
- **Major bump** is a breaking change and means a new base path `/api/v2` served beside `/api/v1`.
- `0.x` was the draft phase (0.1.0 shared components, 0.2.0 sign-in); `1.0.0` was set when CT-01 and CT-02 were approved (2026-10-30, commit `762f515`). History of the file in git: 0.1.0 (P1-A4), 0.2.0 (P1-C1), 1.0.0 (CT-02), 1.1.0 (P1-C2 change-password, logout), 1.2.0 (P2-F8 audit-logs), 1.3.0 (P1-C5 `system/info`), 1.4.0 (P1-C3 mobile sign-in, Bearer on signed-in paths, `DEVICE_REVOKED`), 1.5.0 (P1-C4 sign-out for web and mobile), 1.6.0 (P1-C3 review 3: `X-App-Version` on Bearer requests, password change revokes the user's other tokens). PortHub history shows copy commits for 1.5.0 and 1.6.0 (and 1.2.0 before them); I did not see separate publishes of 1.3.0 and 1.4.0.
- Each version is published to PortHub in its own paired PR (for example `P2-F8: publish OpenAPI 1.2.0`).

### 3.8 What `PortHub/contracts/openapi/port-api.yaml` is

The **published copy** of the backend source. It is written only by `composer contracts:openapi`, and PortHub is where Frontend, Mobile and QA read it (CT-02 "Output"). Frontend generates types from it (FE-035) and contract tests (FE-101). I compared the two files again on 2026-11-06 (backend `9559d35` against the PortHub working tree, whose latest copy commit is `292cad9`, 1.6.0): they are identical apart from line endings (the Windows checkout of PortHub shows CRLF; `diff --strip-trailing-cr` prints no difference). The backend repo normalises to LF (`.gitattributes` has `* text=auto eol=lf`); how PortHub stores the file was not checked (Unverified).

### 3.9 `RouteVersionCoverageTest` (does it belong here? No)

`tests/Feature/Api/RouteVersionCoverageTest.php` does **not** test the OpenAPI file. It asserts that every `PUT` or `PATCH` route under `api/v1/` carries the middleware `version.token:<param>` (P2-D4, FR-GL-05), so no update endpoint can skip the stale-write check. Its allow-list is empty. Today there is no `PUT` or `PATCH` route, so the test passes with nothing to check (I ran it: 1 test, 1 assertion). It belongs to the version-token feature, **F12**. The only link to this feature: when the first update endpoint arrives, the same PR must add the `version` field and the `VersionConflict` response to the OpenAPI operation.

## 4. Why this design

| Choice | Reason | Source |
| --- | --- | --- |
| The Backend Team is the single owner; the file changes in the same PR as the endpoint | A field renamed in the API but not in the file breaks screens built from it. Option 2 (each team updates its own part) was rejected. | DEC-13, Closed 2026-10-29 |
| Source in the backend repo, copy in PortHub, paired PR | Backend CI can run the drift test and Spectral only on files in its own repo; CT-02 names `contracts/openapi/port-api.yaml` in PortHub as the output. | P1-A4 Q1 (Marwan, 2026-10-29); CT-02 |
| Hand-written, guarded by a drift test | Generating from code (Scramble) was explained and rejected: the file is a contract, not a by-product of the code. | P1-A4 Q2 |
| Spectral through a pinned `npx`, no `package.json`; `symfony/yaml` as an explicit dev dependency | No Node dependency in the project; the drift test needs a YAML reader that was only present through `laravel/roster`. | P1-A4 Q4 |
| First file = shared components only (`paths: {}`) | Each endpoint task adds its own path. The drift test kept "matches what the server really does" true while no route existed. | P1-A4 Q5 |
| `info.version` 0.1.0 then minor bump per endpoint PR; 1.0.0 at approval | A `0.x` version told Frontend the file was a draft. | P1-A4 Q6 |
| One response component per error code, named by the case | Endpoints reference errors with one `$ref`; the drift test makes a forgotten response a red build. | P1-A4 plan section 3 |
| Drift test checks only what can be proven mechanically | Field-by-field equality needs a human review (`openapi-sync` skill, per endpoint). | P1-A4 review, note 5 |
| `.spectral.yaml` in `ATLANTECH/`, not the repo root | CI jobs run in `ATLANTECH/`. | P1-A4 log, step 3 |
| `cookieAuth.name` must equal `config('session.cookie')` | An early draft said `laravel_session` while the server sent `laravel-session`: the file described behaviour the server did not have. | P1-A4 review 1 (must fix 1) |

Rejected: a generated file; each team owning its part; the file living only in PortHub; Spectral as an npm dependency.

## 5. How it connects

- **Reads:** `routes/api.php` (the registered routes, through the router), `ErrorCode` (F03), `PageRequest` constants (F03), `config('session.cookie')` (F01), `lang/en/errors.php` (examples).
- **Used by:** Frontend (FE-030, FE-034 fixtures, FE-035 types, FE-101 contract tests), Mobile, QA (CT-26, error responses), the readiness pack (CT-23 links the OpenAPI diff, `api-readiness-pack` skill).
- **Depends on:** F03 (every shared schema and response mirrors the error catalogue and paging rules), F02 (the CI jobs `tests` and `openapi`), F14 (the other three publishing commands, the PortHub flow, the skills `openapi-sync`, `contract-change`, `team-handoff`).
- **Every endpoint feature** (F07 to F12 and all later ones) must update this file in its PR; a new `ErrorCode` needs a new response here.
- **Module layer:** `PublishOpenApi` sits in `Shared` (layer 1) and uses no other module.

## 6. Data

None. The file is text in the repository; the PortHub copy is text in another repository.

## 7. Tests

| Test | Proves | Run |
| --- | --- | --- |
| `tests/Feature/Api/OpenApiDriftTest.php` (6 tests) | Section 3.4 | `vendor/bin/pest tests/Feature/Api/OpenApiDriftTest.php` |
| `tests/Feature/Console/PublishOpenApiTest.php` (2 tests) | Byte-identical copy, folder creation; refusal when the contracts folder is missing | `vendor/bin/pest tests/Feature/Console/PublishOpenApiTest.php` |
| CI job `openapi` (Spectral) | The file is a valid OpenAPI document with no warnings from `spectral:oas` | `npx --yes @stoplight/spectral-cli@6.17.0 lint openapi/port-api.yaml --fail-severity=warn` |
| `tests/Feature/Api/RouteVersionCoverageTest.php` | Not this feature (section 3.9, see F12) | |

I ran the first two files on my own database (F02 section 3.13): all passed; together with the F03 files `122 passed, 699 assertions` on `106b40e`, and `136 passed, 734 assertions` on `9559d35` (this run also included `SystemInfoTest` and `RouteAbilityCoverageTest`). `OpenApiDriftTest` itself did not change in P1-C3/C4/C5; it now also requires the `DeviceRevoked` response and the 18-value enum, and passes. Neither needs MySQL data, but `OpenApiDriftTest` boots the application and reads the route table. I did not run Spectral (needs `npx` to download the package).

## 8. Gotchas and invariants

- **Never edit PortHub's `contracts/openapi/port-api.yaml` by hand.** It is overwritten by the next publish. Edit the backend source, run the command.
- **Do not publish before the backend change is final.** The PortHub PR is opened with the backend PR but the PortHub pointer moves only after the backend PR merges (A2).
- **Path parameter names must equal the route's.** `/api/v1/berths/{berth}` in the file needs `Route::get('berths/{berth}', ...)`. A different name fails the route test.
- **Every method counts.** A route registered with `Route::match(['put','patch'], ...)` needs both operations in the file.
- **Test-only routes are fine.** Pest tests register routes at run time inside their own application instance; they never appear in the drift test.
- **A new `ErrorCode` case needs a new response and an `ErrorCode.enum` entry in the same PR** (F03 section 9.1), in the same order as the enum.
- **Example values are asserted.** `status`, `code`, `message_key`, `title` and `type` in each response example must equal the enum. `detail` is not asserted but should be the English text from `lang/en/errors.php`.
- **`cookieAuth.name`** follows `SESSION_COOKIE`. Changing the env or `config/session.php` without changing the file fails the drift test.
- **Spectral warnings fail CI** (`--fail-severity=warn`). A new operation needs `operationId`, `tags` that exist, a `summary`, and valid `$ref`s.
- **`oas3-unused-component` is off.** Do not switch it on until the components listed in section 3.6 are used or removed (`IdempotencyKey` and nine error responses today). The `openapi-sync` skill still says to turn it on "with the first path": that instruction is out of date (finding F4-2).
- **Bump `info.version` by hand.** Nothing checks it.
- **The file is large and shared:** edit only your task's operations and schemas and reuse the components (skill rule).
- **Treat `version` as opaque** in schemas: it is `$ref DateTime` for documentation, but clients must not parse it (F03, F12).

## 9. How to extend it

### 9.1 Add an endpoint (the `openapi-sync` skill does the same, mode C)

1. Build the route, Form Request and resource first (names in the SQL and enum vocabulary, CT-21).
2. In `openapi/port-api.yaml` add under `paths` the full `/api/v1/...` path and each method. Fill `tags`, `operationId` (camelCase of the route name), `summary`, `description`, `security` (`cookieAuth` for web; `bearerAuth` for mobile plus `AppVersion`, `DeviceId`, and `IdempotencyKey` on writes), `parameters` (`XsrfToken` on web writes, `Page`/`PerPage` on lists), `requestBody`, and `responses`.
3. Map Form Request rules to the schema (the `reference/mapping.md` table of the skill): `required` to `required: []`, `string|max:n` to `maxLength`, `integer|min|max`, `Rule::enum` to `enum` (SQL names), `ZonedDateTime::rules()` to `$ref DateTime`, `exists:` to `integer`.
4. Add every error status the code or its tests produce, each as `$ref: '#/components/responses/<ErrorCode case>'`. A 409 uses `VersionConflict`, a 422 `ValidationFailed`.
5. For an editable record, include `version` (the `updated_at`, `$ref DateTime`) in the response and in the update body (F12).
6. Add the tag at the top if it is new.
7. Bump `info.version` (minor).
8. Run `vendor/bin/pest tests/Feature/Api/OpenApiDriftTest.php`, then the Spectral command above.
9. After the lead's OK: `composer contracts:openapi`, commit the copy on a PortHub branch, open the paired PR, link it from the backend PR.

### 9.2 Add a new error response

Follow F03 section 9.1, step 7: add the code to `ErrorCode.enum` (end of the list) and add `components.responses.<CaseName>`. If the code has extra members, add a `...Problem` schema with `allOf: [Problem, {...}]`.

### 9.3 Add a shared parameter, header or schema

Add it under `components`, reference it with `$ref`. Because `oas3-unused-component` is off, an unused one does not fail CI; remove dead ones when convenient.

### 9.4 Change an existing endpoint without breaking clients

Edit the operation, minor bump. A breaking change (a removed field, a stricter rule, a changed meaning) is not allowed under `/api/v1`: it needs `/api/v2` and a major bump, and a contract change (`contract-change` skill) with Frontend and Mobile review.

### 9.5 Bump Spectral

Change `@stoplight/spectral-cli@6.17.0` in `.github/workflows/ci.yml` and in the `openapi-sync` skill (it names "the version in ci.yml"), run it locally, fix new warnings.

## 10. Try it

From `project-backend/ATLANTECH`, PHP from Herd.

The routes the file must match (I ran this):

```bash
php artisan route:list --except-vendor
```
Output on `9559d35`: seven `api/v1` routes plus `GET /` ("Showing [8] routes"). The file has exactly the seven `/api/v1/...` paths (`audit-logs`, `auth/change-password`, `auth/login`, `auth/logout`, `auth/me`, `auth/mobile/login`, `system/info`). On `106b40e` it was five.

The command is registered (I ran `php artisan list`): `atlantech:contracts-openapi  Publish openapi/port-api.yaml to PortHub contracts/openapi (CT-02)`.

The guard tests (I ran them on my own database):

```bash
vendor/bin/pest tests/Feature/Api/OpenApiDriftTest.php tests/Feature/Console/PublishOpenApiTest.php
```
Expect all green. To see the drift test fail, **do not edit code**; read the test: it would fail the moment `routes/api.php` gains a route that `paths` lacks (the P1-A4 author proved this with a throw-away `GET api/v1/probe` route and reverted).

Compare the source with the published copy (I ran this):

```bash
diff --strip-trailing-cr openapi/port-api.yaml ../../contracts/openapi/port-api.yaml && echo SAME
```
Output: `SAME` (a plain `diff` without the flag reports every line because the PortHub checkout on Windows has CRLF endings).

Publish (**expected, not run:** it overwrites a file in the PortHub checkout, which is the lead's working tree):

```bash
composer contracts:openapi
```
Expect `Wrote E:\...\PortHub\contracts\openapi\port-api.yaml.`, and `git status` in PortHub showing the file modified only when the source changed.

Lint (**expected, not run:** `npx` downloads Spectral):

```bash
npx --yes @stoplight/spectral-cli@6.17.0 lint openapi/port-api.yaml --fail-severity=warn
```
Expect no findings and exit code 0.

Look the decision and contract up:

```bash
node docs/reference/lookup.mjs DEC-13
node docs/reference/lookup.mjs CT-02
```
Output (first line each): `DEC-13 · decision · Who keeps the OpenAPI file current ... status: Closed`, and `CT-02 · contract · Port API OpenAPI file ... status: Approved v1.0.0 · 2026-10-30 by Marwan (Backend lead), without team review`.

## 11. Open points

- **F4-1 · Field-level drift is unguarded.** The test checks routes, error codes, paging bounds and the cookie name. A wrong `required` list, a missing response field or a wrong status on one operation passes CI. Review notes 5 of P1-A4. Candidates: a response-schema check against resources, or contract tests from the Frontend (FE-101).
- **F4-2 · Stale instructions to switch on Spectral rules.** The `openapi-sync` skill (section 3, step 2) and the comment in `.spectral.yaml` say to turn `oas3-unused-component` and `openapi-tags` back on "with the first path". There are seven paths now and the rule must stay off (section 3.6). Also the skill's first section still says the first version is `0.1.0`. The `.spectral.yaml` comment claims "with one path, 18 of them are still unreferenced" (stale). I did not edit them.
- **F4-3 · Version bump is manual.** Nothing fails if `info.version` is not bumped.
- **F4-4 · Pairing is manual.** Nothing checks that the PortHub copy equals the source (a stale PortHub copy is possible). A CI step or a PortHub check could do it. The two files match today.
- **F4-5 · Spectral runs on a downloaded package.** Pinned, but a deleted npm version would turn the job red; P1-A4 review note 8 lists deprecated transitive packages (`glob@7`, `inflight`).
- **F4-6 · Mobile parts: mostly real now, two still drafts.** Since P1-C3 `bearerAuth`, `AppVersion`, `DeviceId` and `AppUpdateRequired` describe implemented behaviour (`EnsureMinAppVersion`, mobile sign-in). `IdempotencyKey` and `IdempotencyKeyReused` still describe behaviour no code has (mobile writes come later).
- **F4-7 · Reserved responses.** `LicenseReadOnly` and `PlanLimitExceeded` are documented as reserved (DEC-54).
- **F4-8 · Review by Frontend and Mobile.** CT-02 v1.0.0 was approved by the Backend lead without team review (DEC-57: critical changes only). P1-A4 notes that PortHub PR #16 had no Frontend or Mobile review at merge.
- **F4-11 · `DeviceRevoked` response is unreferenced.** The mobile sign-in 403 is written inline with two named examples (`accountDisabled`, `deviceRevoked`) because one status carries two codes. The `DeviceRevoked` component exists (the drift test requires it) but no path points at it.
- **F4-9 · Line endings.** The PortHub working copy has CRLF on Windows; git normalises it. Nobody should be surprised by a full-file diff in an editor; use `--strip-trailing-cr`.
- **F4-10 · `RouteVersionCoverageTest`** is listed under this feature in the index, but it tests the version-token rule (F12). The index should move it there.
