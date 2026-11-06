# ATLANTECH Backend Guide: index and walkthrough plan

Location: this folder (`ATLANTECH/docs/guide/` in the backend repo) is the shared copy for the team; it was first written in the team's `Code Notes` folder (`Code Notes/Backend_Guide/`). Change the repo copy from now on, through a pull request into `develop`.

Status: **all files written** (2026-11-04), refreshed 2026-11-06 for the mobile sign-in stack, from `develop` at `2abf6c0`; the completeness pass is done (section 8). The plan below is the original; where a feature file differs, the file is right.
Language: English. Reader: a new software engineer who knows Laravel but not this project. Codebase: `PortHub/project-backend/ATLANTECH/` (the Laravel app) plus `PortHub/contracts/` (shared contracts).

## 1. How this guide works

**For the person (or Claude Code) who continues the work:** read this file first, then one feature file at a time, in order. Each feature file explains the code well enough that you could extend it without asking the person who wrote it.

**Rules the guide follows (agreed with the lead):**

| Rule | Meaning |
| --- | --- |
| Scope | All backend code on `develop`, foundation to Sprint 2. Not the AI workers, the Vue web app or the Flutter app (other repos). |
| Depth | Every service, resource, middleware, command, enum, model pattern and helper is named, and its job and reasons are given. |
| Repetitive code | ~150 models, enums and factories: the pattern is explained once in depth (feature F06), then a catalogue table lists every file with its table, purpose and relations. |
| Laravel level | Laravel knowledge is assumed. Framework concepts are explained only where this project uses them in an unusual way. |
| Delivery per feature | The guide file is written from the code first, then the lead and the writer walk through it in chat, and the file is corrected where gaps appear. |
| Order | Foundation first, then in build order. Each feature builds on the one before. |
| Try it | Every feature ends with short runnable steps (curl, tinker or a test command). |
| Source of truth | The code. Where the guide and the code disagree, the code wins and the guide is a bug. |

## 2. Fixed shape of every feature file

Each `F##_name.md` has these sections, in this order:

1. **What it does** (the behaviour a user or another system sees, in plain words) and what it does *not* do.
2. **Request or data flow** (numbered steps from entry point to response, citing `file:line`).
3. **Every piece of code** (each class, method and config key: what it does, its inputs and outputs, why it is built that way).
4. **Why this design** (the decision or contract behind it, with the ID: `DEC-nn`, `CT-nn`, `FR-…`; what was rejected).
5. **How it connects** (what calls it, what it calls, what other features depend on it, which module-layer rules apply).
6. **Data** (tables and columns read or written, constraints that back the rules, who owns the table).
7. **Tests** (which test proves which rule, and how to run it).
8. **Gotchas and invariants** (things that break silently, rules a change must not violate).
9. **How to extend it** (the exact steps to add the next thing: a new ability, a new error code, a new endpoint).
10. **Try it** (short runnable commands and the output to expect).
11. **Open points** (stubs, known gaps, follow-ups).

## 3. Part A: orientation (read before any feature)

File: `A_orientation.md`. Covers:

- **Two repos and a pointer.** `project-backend` (the Laravel app, in `ATLANTECH/`) and `PortHub` (contracts, shared docs, a submodule pointer to the backend). Why the paired-PR flow exists; why the pointer moves only after a backend PR merges.
- **Repo map.** `app/Modules/*`, `routes/`, `bootstrap/app.php`, `config/`, `database/{migrations,factories,seeders,grants}`, `lang/`, `openapi/`, `tests/`, `docs/reference` (copies of the project documents), `docs/tasks` (plan files), `.claude/skills` and `.claude/agents`, `docker/`, `.github/`.
- **Module layers.** Shared, Access, License, MasterData, then the later modules (Berthing, Baplie, Environment, Recommendation, Plan, Dispatch, Execution, Yard, Learning, AiBridge, Realtime). A module may use only earlier ones; `tests/Unit/Modules/ModuleArchitectureTest.php` enforces it. Why (and what it forces, for example `Access` reading no `Plan` table).
- **Glossary of project vocabulary.** `CT-nn` (team contracts), `DEC-nn` (decisions), `FR-…` / `NFR-…` (requirements), `AIBD-nnn`, `FT-nn` (features), `P1-A1` style task ids and sprints, `ai-impact` label, modes A/B/C/D, `REJECTED_ATTEMPT`, version token, write chain, terminal scope, own tasks.
- **How decisions are recorded and found.** `docs/reference/README.md` (Source of truth rules), `lookup.mjs`, `sync-check.mjs`, `build-index.mjs`, the manifest.
- **Daily workflow.** Branches `feature/<task>-<slug>` from `develop`, PRs into `develop`, CI checks (`pint`, `tests`, `openapi`, `labeler`), the team review rule, how to run the suite and why parallel runs need separate test databases.

## 4. Part B: the features, in order

Each entry lists the **main points** the file must teach, the **files** to read, and the **tests**. Counts come from the code on `develop`.

### F01 · Runtime and platform

- **Points:** Docker Compose (MySQL 8.4, Redis 7.4 with AOF, optional `server` profile with `app` + `nginx`, `tools` profile with phpMyAdmin); `Dockerfile` and `docker/{php,nginx}`; why PHP runs in Herd for daily work and in Docker for CI; ports bound to 127.0.0.1; `composer` scripts (`setup`, `dev`, `test`, `lint`, `contracts:*`); the module layout and `bootstrap/providers.php`; the `/up` health route; `config/{database,sanctum,session,cors,seeding}.php`; `.env` keys that matter.
- **Files:** `docker-compose.yml`, `Dockerfile`, `docker/`, `composer.json`, `bootstrap/{app,providers}.php`, `config/*`, every `*ServiceProvider.php`, `README.md`.
- **Tests:** `HealthCheckTest`, `InfrastructureTest`, `ModuleArchitectureTest`.

### F02 · CI and quality gates

- **Points:** the `CI` workflow (`pint`, `tests` with MySQL and Redis services, `openapi` with Spectral); the `labeler` workflow and the `ai-impact` rule (which paths trigger it, and why); `CODEOWNERS`; the PR template; branch protection as used by the team; Pest setup (`tests/Pest.php`, `TestCase.php`), the hard-coded test database `atlantech_testing` and how to override it with `DB_DATABASE` and `DB_SCHEMA_TEST_DATABASE`; test groups (`schema`, `infra`).
- **Files:** `.github/workflows/ci.yml`, `.github/workflows/labeler.yml`, `.github/labeler.yml`, `.github/CODEOWNERS`, `.github/pull_request_template.md`, `phpunit.xml`, `tests/Pest.php`, `tests/TestCase.php`.

### F03 · API base: errors, pagination, time

- **Points:** `/api/v1` prefix and `statefulApi`; RFC 9457 problem responses (`type`, `title`, `status`, `code`, `message_key`, `detail`); `ErrorCode` (the one catalogue: status, message key, title per code) and `ApiProblemException`; `ProblemRenderer` (maps every framework exception to a stable code, never leaks a stack trace); `lang/en/errors.php` and why `detail` is the English text of the key; the `FORBIDDEN`, `CONFLICT`-style codes and how to add a new one; `PageRequest` and `PaginatedCollection` (`data`, `meta`; `per_page` 25 default, 100 max); UTC everywhere and `ZonedDateTime` input handling.
- **Files:** `app/Modules/Shared/Errors/*`, `Shared/Http/{Pagination,Resources,Validation}/*`, `lang/en/errors.php`, `bootstrap/app.php` (exceptions section).
- **Tests:** `ProblemResponseTest`, `StableCodesTest`, `ErrorMessagesTest`, `ValidationProblemTest`, `PaginationTest`, `UtcDateTimeTest`, `ApiRoutingTest`, `ApiProblemExceptionTest`, `ErrorCodeTest`.

### F04 · The OpenAPI contract

- **Points:** `openapi/port-api.yaml` as the shared contract for the Vue and Flutter teams (DEC-13: the backend owns it); shared components (problem schemas, one response per error code, pagination, `bearerAuth`, headers); the drift test and what makes it fail; Spectral in CI; the version bump rule (minor per endpoint change); the paired PortHub PR and `php artisan atlantech:contracts-openapi`.
- **Files:** `openapi/port-api.yaml`, `Shared/Console/PublishOpenApi.php`, `.spectral.yaml`.
- **Tests:** `OpenApiDriftTest`, `PublishOpenApiTest`. (`RouteVersionCoverageTest` was listed here by mistake: it checks the version-token rule and belongs to F12.)

### F05 · Database schema and migrations

- **Points:** how the 92 tables came from the PortSync SQL; plural table names; `BIGINT UNSIGNED` ids and foreign-key naming; `updated_at DATETIME(6)` as the version token; the 129 named CHECK constraints and 17 generated columns, with the generated unique keys (12 unique indexes over 8 distinct column names: `active_key`, `single_key`, `active_assignment_key`, …) and what each one stops; strict ENUMs; the worker grants (`database/grants/workers.sql`, DEC-08, one database account per AI worker); `GenerateContractsSchema` and publishing `schema.sql` (CT-17); `CreateWorkerAccounts`; why the app connection runs in strict mode.
- **Files:** `database/migrations/*`, `database/grants/workers.sql`, `AiBridge/Console/{GenerateContractsSchema,CreateWorkerAccounts}.php`.
- **Tests:** `SchemaTablesTest`, `SchemaConstraintsTest`, `DatabaseRulesTest`, `ContractsSchemaTest`, `WorkerGrantsTest`.

### F06 · The model layer (pattern once + catalogue)

- **Points:** one model per table, per module; `#[Table]`, `#[Fillable]` attributes and `timestamps` choices; PHP enums backed by the exact SQL values and cast on models; generated columns read-only; AI-owned columns not fillable (CT-15); relations allowed by the module rules; factories; the catalogue of all 92 models with table, module, key enums and relations.
- **Files:** `app/Modules/*/Models/*`, `app/Modules/*/Enums/*`, `database/factories/*`.
- **Tests:** `ModelLayerTest` (one row-round-trip test per model, enum-versus-column guard).

### F07 · Web sign-in and session

- **Points:** Sanctum SPA flow (`/sanctum/csrf-cookie`, session cookie, no fixation); `POST auth/login`, `GET auth/me`, `POST auth/logout`; `LoginRequest` (username lower-cased and trimmed, DEC-35); `SignInService` (throttle 5/min per username+IP and 20/min per IP, unknown user and wrong password give the same 401, fair timing with one `Hash::make`, refusals audited); `UserProfileResource` (`id`, `name`, `role`, `terminal_ids`, `must_change_password`); the `User` model (`username`, `password_hash`); the `UserDevice` and organisation-unit models used here.
- **Files:** `Access/Http/Controllers/AuthController.php`, `Access/Http/Requests/LoginRequest.php`, `Access/Services/SignInService.php`, `Access/Http/Resources/UserProfileResource.php`, `Access/Models/{User,UserDevice,OrganizationUnit,UserRoleAssignment}.php`, `routes/api.php`.
- **Tests:** `WebSignInTest`, `ProfileTest`.

### F08 · Password-change gate and account state

- **Points:** `EnsureAccountUsable` (inactive account gives 403 `ACCOUNT_DISABLED`, `must_change_password` gives 403 `PASSWORD_CHANGE_REQUIRED`, and the short allow-list of routes still reachable); `PasswordChangeService` and `ChangePasswordRequest` (8+ characters, letters and numbers, differs from the current one); why a deactivated user is refused on the next request.
- **Files:** `Access/Http/Middleware/EnsureAccountUsable.php`, `Access/Services/PasswordChangeService.php`, `Access/Http/Requests/ChangePasswordRequest.php`.
- **Tests:** `PasswordChangeTest`.

### F09 · Roles, abilities and scope (P2-D1)

- **Points:** the 12 roles (`Role`), one active role per user (DEC-36, `uq_ura_active`); `Ability` (27 values by function area) and the matrix `RolePermissions`; one `Gate` per ability (`AccessServiceProvider`); `EnsureAbility` (alias `ability:<name>`); `AccessScope` (role, terminals, user id, memoised); `TerminalScope` and `EnsureTerminalScope` (alias `terminal.scope`, 403 not 404); `RestrictsToOwnTasks` on `PlanComponent` (field users see only their own tasks); `AccessRefusal` and `AccessRefusalReason` (one place that audits and throws 403); why `Access` cannot read `Plan` or `MasterData` tables; Marine Safety Officer holds no ability for now (Non-Container deferred); the published `role-matrix.md` and `PublishRoleMatrix`.
- **Files:** `Access/Enums/{Role,Ability,AccessRefusalReason,UnitType}.php`, `Access/Services/{RolePermissions,AccessScope,TerminalScope,AccessRefusal}.php`, `Access/Http/Middleware/{EnsureAbility,EnsureTerminalScope}.php`, `Access/Models/Concerns/RestrictsToOwnTasks.php`, `Access/Console/PublishRoleMatrix.php`, `Access/AccessServiceProvider.php`.
- **Tests:** `RolePermissionsTest`, `AbilityMiddlewareTest`, `TerminalScopeTest`, `OwnTasksTest`, `RefusalAuditTest`, `CrossRoleSecurityTest`, `RouteAbilityCoverageTest`, `PublishRoleMatrixTest`.

### F10 · Audit service and audit-log browsing (P2-D3, P2-F8)

- **Points:** `AuditService` (writes the audit row in the same transaction as the change; `rejectedAttempt(...)` writes `REJECTED_ATTEMPT` in its own transaction after the refused write rolled back; actor id is a plain integer so Shared stays below Access; `actor_id` NULL for system); `AuditLog` and `AuditLogBuilder` (append-only: update, delete, truncate, increment and friends are refused); `AuditLogAction`; the browsing endpoint `GET audit-logs` (Port Admin only, filters, master-data history as a filter on entity); why the database grant still matters (DEC-08).
- **Files:** `Shared/Audit/AuditService.php`, `Shared/Models/{AuditLog,AuditLogBuilder}.php`, `Shared/Enums/AuditLogAction.php`, `Shared/Http/Controllers/AuditLogController.php`, `Shared/Http/Requests/AuditLogIndexRequest.php`, `Shared/Http/Resources/{AuditLogCollection,AuditLogEntryResource}.php`.
- **Tests:** `AuditServiceTest`, `AuditLogBrowsingTest`.

### F11 · The write chain (P2-D2)

- **Points:** the fixed check order of FR-GL-01 (authentication 401, password gate 403, ability 403, terminal scope 403, app version 426, licence mode 423, route binding, version token 409, then business rules and plan limits inside the endpoint); the `port.api` middleware group and the `priority` list in `bootstrap/app.php` (why the order holds whatever order a route lists its middleware in, and why route binding runs after the scope checks so an unauthorised caller never gets a 404); the hook classes `EnsureMinAppVersion` and `EnsureLicenseAllowsWrite` (pass-through today, who fills them: P1-C3 and the licence work); how a new route is written.
- **Files:** `bootstrap/app.php`, `routes/api.php`, `Access/Http/Middleware/EnsureMinAppVersion.php`, `License/Http/Middleware/EnsureLicenseAllowsWrite.php`.
- **Tests:** `WriteChainOrderTest`.

### F12 · Version token and optimistic locking (P2-D4)

- **Points:** version = `updated_at` string with microseconds (DEC-57, CT-01); `VersionToken` (`format`, `of`, `matches`, `rules`, `assertCurrent`, `lockAndAssert`); `EnsureRecordVersion` (alias `version.token:<param>`: safe methods pass, body `version` validated 422, stale gives 409 `VERSION_CONFLICT` with `current: {id, version}` and saves nothing); audit of the refused 409 and 422 through `AuditService`.
- **Files:** `Shared/Support/VersionToken.php`, `Shared/Http/Middleware/EnsureRecordVersion.php`.
- **Tests:** `VersionTokenHelperTest`, `VersionTokenTest`, `RouteVersionCoverageTest`.

### F13 · Seeders and the demo port (P1-B6)

- **Points:** `DatabaseSeeder` order; `ReferenceDataSeeder` (upsert by code from `reference-seed.json`, CT-18); `PortProfileSeeder` (the one `port_profiles` row, id 1) and `PortSettingsSeeder` (including `min_app_version`); `DemoPortSeeder` (8 terminals, 36 berths, channel, shifts) and `DemoPeopleSeeder` (12 role users, 12 pilots, 10 tugs and masters, 7 days of shifts) from `damietta-demo-port.json`; `config/seeding.php` and the production guard (`SEED_DEMO_PORT`, `DEMO_USER_PASSWORD`); the `port_settings.min_value` / `max_value` columns (DEC-59, CT-18 v1.1).
- **Files:** `database/seeders/*`, `database/seeders/data/*`, `config/seeding.php`, the `port_settings` migration.
- **Tests:** `ReferenceDataSeederTest`, `PortProfileAndSettingsSeederTest`, `PortSettingsMinMaxTest`, `DemoPortStructureTest`, `DemoPeopleSeederTest`.

### F14 · Contract and documents tooling

- **Points:** the four publishing commands (`PublishOpenApi`, `PublishStatusVocabulary`, `PublishRoleMatrix`, `GenerateContractsSchema`) and the PortHub files each writes; the paired-PR flow; `docs/reference` (copies of project documents), `manifest.json`, `sync-check.mjs`, `lookup.mjs`, `build-index.mjs`; the Source of truth rules; `docs/tasks` plan files as task memory; the skills and agents in `.claude/` (what each does and when to use it).
- **Files:** `Shared/Console/*`, `Access/Console/PublishRoleMatrix.php`, `docs/reference/*`, `docs/tasks/*`, `.claude/skills/*`, `.claude/agents/*`.
- **Tests:** `PublishOpenApiTest`, `PublishStatusVocabularyTest`, `PublishRoleMatrixTest`, `ContractsSchemaTest`.

### F15 · What is not built yet, and what comes next

- **Points:** stubs and hooks (`EnsureMinAppVersion`, `EnsureLicenseAllowsWrite`, the `License` module beyond `LicenseState`, `Realtime` and most modules are models only); planned but not implemented (P2-E1, E2, E3 users, roles, devices; P1-C5, C3, C4 `/system/info`, mobile sign-in, mobile logout; BE-PK2 readiness pack); open dependencies (CT-04 change for a revoked device, the `personal_access_tokens` migration, DEC-30 numbers); Sprint 3 work unblocked by Sprint 2; known risks and trade-offs; the lead's decisions that shape the next work.
- **Source:** the plan files in `docs/tasks`, section 6 and 7 of each, and `docs/reference/roadmap`.

## 5. Walkthrough protocol (how we will go through it)

1. Lead approves this index (changes welcome: add, remove, reorder features).
2. For feature **n**: the writer reads the code first, writes `F##_name.md` in `Code Notes/Backend_Guide/`, then tells the lead it is ready.
3. The lead and writer walk through the file in chat: the lead asks questions, the writer answers from the code and fixes the file where it was unclear or wrong.
4. The lead says "next". Only then does feature **n+1** start.
5. When all features are done, the writer runs a **completeness pass**: every file under `app/`, every route, every command and every test file appears in at least one feature file; the missing ones are listed and fixed.

## 6. Handing this to someone else's Claude Code

Give them the `Backend_Guide/` folder and this prompt:

> Read `Backend_Guide/00_INDEX.md`, then `A_orientation.md`, then the feature files in order. The code on `develop` is the source of truth: if a guide file disagrees with the code, trust the code and tell the lead. Before you change anything, find the feature that owns it, read its "Gotchas and invariants" and "How to extend it", and run the tests named in its "Tests" section.

## 7. The files

| File | Contents |
| --- | --- |
| `00_INDEX.md` | This plan and index. |
| `A_orientation.md` | Orientation: the system, repos, map, module layers, glossary, workflow. Read first. |
| `F01_runtime_and_platform.md` | Docker, nginx, config, providers, composer scripts. |
| `F02_ci_and_quality_gates.md` | CI workflows, labeler, Pint, Pest setup and test databases. |
| `F03_api_base_errors_pagination_time.md` | Problem responses, error catalogue, pagination, UTC. |
| `F04_openapi_contract.md` | The OpenAPI file, the drift test, publishing. |
| `F05_database_schema_and_migrations.md` | 92 tables, constraints, generated columns, worker grants, schema publishing. |
| `F05b_database_diagrams.md` | The same 92 tables as Mermaid diagrams, one business group at a time, plus a group overview. Generated from `schema.sql`. |
| `F06_model_layer.md` | Model, enum and factory conventions, with the full catalogues. |
| `F07_web_sign_in_and_session.md` | Sanctum SPA sign-in, profile, sign-out. |
| `F08_password_change_gate.md` | The password-change gate and account state. |
| `F09_roles_abilities_and_scope.md` | Roles, the ability matrix, terminal and own-task scope. |
| `F10_audit_service_and_log_browsing.md` | Audit service, append-only log, browsing endpoint. |
| `F11_write_chain.md` | The fixed check order of every write. |
| `F12_version_token.md` | Optimistic locking with `updated_at`. |
| `F13_seeders_and_demo_port.md` | Reference data, the demo port, production guard. |
| `F14_contract_and_documents_tooling.md` | Publishing commands, `docs/reference`, plan files, skills and agents. |
| `F15_not_built_yet_and_next.md` | What is not built, what is planned, what comes next. |

## 8. Coverage check (completeness pass, 2026-11-04; refreshed 2026-11-06)

A script compared the guide text with the code on `develop` (`2abf6c0`, after the mobile sign-in stack): every item below appears by name in at least one guide file.

| Area | Count on `develop` | Covered |
| --- | --- | --- |
| PHP files in `app` other than models and enums (services, middleware, commands, controllers, requests, resources, providers) | 62 | 62 / 62 (the ten empty providers are listed by class name in F01 section 3.9) |
| Models, enums, factories | 93 (92 port models plus Sanctum's `PersonalAccessToken`), 80, 92 | all, in the F06 catalogues |
| Seeders | 6 (plus 2 data files) | 6 / 6 (F13) |
| Test files | 50 | 50 / 50 |
| Migrations | 98 (3 framework, 92 port tables, 3 later changes) | all (F05 catalogue) |
| Config files | 13 | 13 / 13 (F01 and the feature that owns each key) |
| Routes | `GET audit-logs`, `POST auth/login`, `POST auth/mobile/login`, `POST auth/logout`, `GET auth/me`, `POST auth/change-password`, `GET system/info`, plus the skeleton `GET /`, `/up` and Sanctum's CSRF route | all |
| Artisan commands | 5 (`atlantech:contracts-openapi`, `-role-matrix`, `-schema`, `-status-vocabulary`, `atlantech:worker-accounts`) | all |
| Error codes, abilities | 18, 27 | all (F03, F09) |
| Skills, agents, docker, CI and ops files, `docs/reference` tools | 15, 4, 12, 6, 4 | all (F02, F14, F01) |
| Links between guide files | every `.md` link | none broken |
| Diagrams (F05b) | 16 group diagrams and 1 overview, 144 entities, 127 relationships | syntax-linted, no errors |

Facts spot-checked against code in the first pass: the throttle numbers (F07), the `VersionToken` and `AuditService` method lists (F10, F12), the seeder counts (F13) and the README seeding claim (found wrong and corrected). The 2026-11-06 refresh was made by the four writing sessions against the mobile stack, each re-running the tests of its own files on its own database (136, 377-plus, 217 and 100-plus tests, all passing).

What this pass does **not** prove: that every sentence is right. Where a writer could not verify something, the file says "Unverified". Walk through the files with the lead, and treat a disagreement between a file and the code as a bug in the file.
