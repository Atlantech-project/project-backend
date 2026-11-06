# F02 · CI and quality gates

Task origin: P1-A3 (Sprint 1, CI), with the `openapi` job added by P1-A4 and the labeler, CODEOWNERS and templates added by P1-B1 and later tasks. Facts first checked against `develop` at `106b40e`, then **updated on 2026-11-06 against `9559d35`** (P1-C5, P1-C3, P1-C4 and the review fixes merged as PRs #54 to #57). The CI workflows, labeler, CODEOWNERS, templates, `phpunit.xml`, `tests/Pest.php` and `.spectral.yaml` did not change in that range (`git diff 106b40e..9559d35` is empty for them); `tests/TestCase.php` and the test files did. Paths are inside `project-backend/ATLANTECH/` unless they start with `.github/`; **`.github/` sits one level up, at the root of the `project-backend` repository** (GitHub only reads workflows from the repository root). See A3 and A7 in `A_orientation.md` for the repo map and the daily workflow.

## 1. What it does

This feature is the **gate every pull request must pass before it can merge into `develop`**, plus the files that tell reviewers what to look at.

It gives you:

- **Three automatic checks** on every non-draft pull request into `develop`: `pint` (code style), `tests` (the whole Pest suite against real MySQL 8.4 and Redis 7.4) and `openapi` (a Spectral lint of the API contract file). A fourth workflow, `labeler`, adds the `ai-impact` label when a PR touches migrations or worker grants.
- **Review routing:** `CODEOWNERS` asks the AI lead to review the same two folders, and a PR template lists what the author must tick.
- **A defect form** (issue template) for QA and reviewers, tied to CT-26.
- **The test harness** the checks run: `phpunit.xml`, `tests/Pest.php` (including the `useSchemaDatabase()` helper), `tests/TestCase.php` and the two test groups `schema` and `infra`.

It does **not** give you: static analysis (Larastan or PHPStan: the lead said no on 2026-10-28), coverage thresholds (NFR-EXT-4, deferred), CI on pushes or on pull requests into `main`, contract tests against the AI JSON Schemas (P3-I8, later), a deployment pipeline (P6-R6), or any check of PortHub's own files. The web and AI repositories have their own CI.

## 2. Request or data flow

What happens when someone opens a pull request into `develop`:

1. GitHub reads the workflows at the repo root. Two react to a `pull_request` event: `CI` (`.github/workflows/ci.yml:5-8`, types `opened`, `synchronize`, `reopened`, `ready_for_review`, base branch `develop` only) and `Labeler` (`.github/workflows/labeler.yml:5-7`, types `opened`, `synchronize`, `reopened`).
2. `CI` joins a **concurrency group** named after the workflow and the PR number (`ci.yml:10-12`). A new push to the same PR cancels the run in progress.
3. Three jobs start in parallel. Each one checks `github.event.pull_request.draft == false` and is skipped for a draft PR (`ci.yml:25, 58, 75`). Every `run:` step starts in `ATLANTECH/` because of `defaults.run.working-directory` (`ci.yml:17-19`).
4. `pint` installs Composer packages (no scripts) and runs `vendor/bin/pint --test`.
5. `openapi` installs Node 22 and runs Spectral 6.17.0 on `openapi/port-api.yaml` with the ruleset `.spectral.yaml`. It fails on warnings.
6. `tests` starts the MySQL and Redis service containers, installs PHP and Composer packages, copies `.env.example` to `.env`, generates the app key, creates the database `atlantech_testing`, prints the `mysqldump` version and runs `php artisan test --compact`.
7. In parallel, `Labeler` runs `actions/labeler@v5`. It reads `.github/labeler.yml` and, if a changed file matches, adds `ai-impact`.
8. GitHub shows each job as a check on the PR. The `develop-guard` ruleset (set up by the lead, not stored in the repo) requires `pint`, `tests` and `openapi` plus one approval before merge (see section 4).

For a local run of the same checks, see section 10.

## 3. Every piece of code

### 3.1 `.github/workflows/ci.yml`, workflow `CI`

| Key | Value | What it does and why |
| --- | --- | --- |
| `on.pull_request.branches` | `[develop]` | Runs only on PRs whose base is `develop`. No `push` trigger and nothing for `main` (lead decision, P1-A3 Q3). |
| `on.pull_request.types` | `opened, synchronize, reopened, ready_for_review` | `synchronize` is a new commit. `ready_for_review` is what makes the checks start when a draft PR is marked ready (jobs were skipped while it was a draft). |
| `concurrency.group` | `ci-${{ github.workflow }}-${{ github.event.pull_request.number }}` | One group per PR. |
| `concurrency.cancel-in-progress` | `true` | A new push cancels the older run, saving minutes and avoiding two runs racing. |
| `permissions` | `contents: read` | Least privilege: CI never writes to the repo. |
| `defaults.run.working-directory` | `ATLANTECH` | The Laravel root. The cache key `hashFiles('ATLANTECH/composer.lock')` uses the repo-root path because `hashFiles` is not affected by `working-directory`. |

**Job `pint`** (`ci.yml:23-52`). `name: pint` is the **required check name**. Runs on `ubuntu-latest`, `timeout-minutes: 10`.

1. `actions/checkout@v4`.
2. `shivammathur/setup-php@v2`, PHP `8.4`, `coverage: none`. It lists no `extensions:`, because Pint needs none.
3. Gets Composer's cache directory into `steps.composer-cache.outputs.dir` and restores it with `actions/cache@v4`. Key `composer-<os>-<hash of composer.lock>`, fallback `composer-<os>-`.
4. `composer install --no-interaction --no-progress --prefer-dist --no-scripts`. `--no-scripts` skips `package:discover` and `boost:update`, which are not needed to run Pint.
5. `vendor/bin/pint --test`. `--test` reports violations and exits non-zero without changing files. There is no `pint.json`, so the **default Laravel preset** applies (P1-A3 plan, "Done when").

**Job `openapi`** (`ci.yml:54-70`). `name: openapi`, required check. Timeout 10 minutes.

1. `actions/checkout@v4`.
2. `actions/setup-node@v4`, Node `22`. There is no `package.json` step: the project's own `package.json` is for Vite and is not used here.
3. `npx --yes @stoplight/spectral-cli@6.17.0 lint openapi/port-api.yaml --fail-severity=warn`. The version is **pinned** (a moving `latest` could turn the check red without any change in the repo). `--fail-severity=warn` makes a warning fail the job (Spectral's default is to fail on errors only). Spectral finds the ruleset itself: it looks for `.spectral.yaml` in the current directory, which is `ATLANTECH/` (the file lives next to `openapi/`, P1-A4 log step 3).

**Job `tests`** (`ci.yml:72-143`). `name: tests`, required check. Timeout 20 minutes.

| Part | Detail |
| --- | --- |
| `services.mysql` | `mysql:8.4`, `MYSQL_ALLOW_EMPTY_PASSWORD: 'yes'`, `MYSQL_DATABASE: atlantech`, port 3306, health check `mysqladmin ping -h 127.0.0.1 --silent` every 5 s, up to 20 retries. Same image and settings as `docker-compose.yml` (F01 section 3.1). The database `atlantech` must exist because `InfrastructureTest` connects to it by name. |
| `services.redis` | `redis:7.4-alpine`, port 6379, health check `redis-cli ping`. |
| PHP step | PHP 8.4, `extensions: pdo_mysql, redis, intl, bcmath, pcntl, zip`, `coverage: none`. The comment says "same extensions as the Dockerfile", but the Dockerfile also installs `opcache` (finding F2-3). |
| Composer cache | Same two steps as `pint`. |
| `composer install` | `--no-interaction --no-progress --prefer-dist`, **with** scripts (this job needs `package:discover`). |
| Prepare application | `cp .env.example .env` then `php artisan key:generate`. `.env.example` supplies `DB_HOST=127.0.0.1`, `DB_USERNAME=root`, an empty `DB_PASSWORD`, `REDIS_HOST=127.0.0.1` (F01 section 3.5), which match the service containers. |
| Create the test database | `mysql -h 127.0.0.1 -P 3306 -u root -e "CREATE DATABASE IF NOT EXISTS atlantech_testing"`. This is the database `phpunit.xml` selects (section 3.7). The comment above it states the rule: **do not set `DB_DATABASE` in the workflow**, because a real environment variable overrides the `<env>` value in `phpunit.xml` (section 3.7) and would move the tests off `atlantech_testing`. |
| Report the `mysqldump` client version | `mysqldump --version`. `ContractsSchemaTest` (F05) needs a `mysqldump` binary; this line prints which one the runner has, so a version mismatch with the 8.4 server is easy to diagnose. The plan's risk about an 8.0 client did not happen: the first run passed (P1-A3 log, 2026-10-28). |
| Run the test suite | `php artisan test --compact`: the **whole** suite, all groups, no parallel option. |

The Redis container is needed by the `infra` group (`InfrastructureTest` stores and reads a key). Everything else runs with `CACHE_STORE=array`, `SESSION_DRIVER=array` and `QUEUE_CONNECTION=sync` from `phpunit.xml`.

### 3.2 `.github/workflows/labeler.yml`, workflow `Labeler`

| Key | Value | Why |
| --- | --- | --- |
| `on.pull_request.types` | `opened, synchronize, reopened` | Re-evaluated when files change. No branch filter and no draft skip, so it labels PRs into any base. |
| `permissions` | `contents: read`, `pull-requests: write` | Needed to add a label. |
| Job `label` (`name: labeler`) | `actions/labeler@v5`, timeout 5 minutes | The check shows as `labeler`. |

The comment in the file explains the trigger choice: `pull_request`, **not** `pull_request_target`. A fork PR therefore gets a read-only token and the label step cannot write. For same-repo PRs (the normal case here) it works. The workflow has no `with:` block, so the action uses its defaults (config file `.github/labeler.yml`). Whether `sync-labels` (remove a label when the files no longer match) is on is **Unverified**: I did not read the v5 defaults in this session; check the action's README before relying on label removal.

### 3.3 `.github/labeler.yml`

```yaml
ai-impact:
  - changed-files:
      - any-glob-to-any-file:
          - ATLANTECH/database/migrations/**
          - ATLANTECH/database/grants/**
```

A PR that changes any file under `database/migrations/` or `database/grants/` gets the label `ai-impact`. **Why:** the Python AI workers read and write MySQL tables directly, and their database accounts are defined by `database/grants/workers.sql` (DEC-08, F05). A column rename or a changed grant can break a worker, so the AI lead must see the PR. The rule comes from CT-17 section 6 (the comment in the file says so). Paths start with `ATLANTECH/` because globs are relative to the repo root.

### 3.4 `.github/CODEOWNERS`

```
/ATLANTECH/database/migrations/  @mostafa-rashwan
/ATLANTECH/database/grants/      @mostafa-rashwan
```

`@mostafa-rashwan` is Rashwan, the AI lead. The same two folders as the labeler. The file's own comment says it is **review requests only**: the `develop-guard` ruleset does not require code-owner approval, so GitHub asks for his review but does not block the merge on it (in practice the team treats it as required for `ai-impact` PRs, see section 4).

### 3.5 `.github/pull_request_template.md`

Two parts: a free text "What and why" (task id, link to `ATLANTECH/docs/tasks/<ID>.md`) and a checklist:

| Checklist line | Meaning |
| --- | --- |
| Base branch is `develop` (never `main`) | Backend PRs go to `develop`. |
| Touches `database/migrations` or `database/grants` → `ai-impact` label and Rashwan's review | Matches the labeler and CODEOWNERS. |
| Adds or changes an endpoint → `openapi/port-api.yaml` updated, `composer contracts:openapi`, paired PortHub PR linked | The OpenAPI rules of F04. |
| `docs/tasks/<ID>.md` progress log and done-when updated | The task file is the task's memory (A7). |
| `composer lint` and `php artisan test --compact` pass locally | Run the same two checks before pushing. |

The template is a reminder. Nothing in CI reads the ticks.

### 3.6 `.github/ISSUE_TEMPLATE/defect.yml`

An issue form named "Defect" ("Report a defect found while testing a release branch (CT-26)"), title prefix `[Defect] <short summary>`, label `defect`. Fields (all required except Evidence): **Feature** (dropdown FT-01 to FT-13), **Page ID / Endpoint** (text, for example `POST /api/v1/sync/events`), **Repository** (dropdown: `project-backend`, `project-frontend`, `project-mobile`, `project-ai`, `PortHub`), **Found on (release branch)** (text, for example `release@abc1234`), **Severity** (Blocker stops the release; Major breaks a feature with no workaround; Minor is cosmetic or has a workaround), **Steps**, **Expected**, **Actual** and **Evidence** (the description says a defect without evidence is not accepted). There is no `config.yml`, so blank issues are still allowed. The form is for QA and the release process (CT-26), not for the CI.

`.github/assets/` holds the logos and SVGs used by `README.md`. It is not code.

### 3.7 Pint

- Installed: `laravel/pint` v1.32.1 (`composer.lock`). No `pint.json`: the default `laravel` preset applies.
- CI runs `vendor/bin/pint --test` (fails, never fixes). `composer lint` runs the same command (`composer.json:58-60`).
- Locally, to fix style, run `vendor/bin/pint --dirty` (formats only changed files). The Boost rule in `CLAUDE.md` tells AI agents to run `pint --dirty`, not `--test`; that is a rule for editing, and CI must still use `--test` (P1-A3 plan section 7).
- Larastan is deliberately absent (lead decision, P1-A3 Q1). The module-order rule that NFR-EXT-3 asks a static analyser for is covered by `tests/Unit/Modules/ModuleArchitectureTest.php`, which runs in job `tests` (F01).

### 3.8 `.spectral.yaml` (mention only; F04 owns the OpenAPI file)

`ATLANTECH/.spectral.yaml` extends `spectral:oas` (recommended) and switches off one rule, `oas3-unused-component`. Its comment is out of date (finding F2-2). The OpenAPI structure, the lint rules and the version rule are in F04.

### 3.9 `phpunit.xml`

| Element | Value | Meaning |
| --- | --- | --- |
| `bootstrap` | `vendor/autoload.php` | |
| `testsuite Unit` / `Feature` | `tests/Unit`, `tests/Feature` | Two suites. |
| `source/include` | `app` | Used only for coverage reports (not a gate). |
| `APP_ENV` | `testing` | |
| `APP_MAINTENANCE_DRIVER` | `file` | |
| `BCRYPT_ROUNDS` | `4` | Fast password hashing in tests. |
| `BROADCAST_CONNECTION` | `null` | |
| `CACHE_STORE` | `array` | No Redis for the cache or the rate limiter in tests. |
| `DB_CONNECTION` | `mysql` | **Tests always use MySQL**: SQLite cannot run the CHECK constraints, generated columns and ENUMs of the port schema (comment in `tests/Pest.php:60-63`, CT-17). |
| `DB_DATABASE` | `atlantech_testing` | The default test database. |
| `DB_URL` | empty | Stops a `DATABASE_URL` in the shell from replacing the settings. |
| `MAIL_MAILER` | `array` | |
| `QUEUE_CONNECTION` | `sync` | Jobs run inline. |
| `SESSION_DRIVER` | `array` | |
| `PULSE_ENABLED`, `TELESCOPE_ENABLED`, `NIGHTWATCH_ENABLED` | `false` | Skeleton defaults for packages this project does not install. |

**Rule that matters:** PHPUnit's `<env>` does **not** override a variable that is already set in the real environment (no `force="true"`). So `DB_DATABASE=other php artisan test` wins over the value in `phpunit.xml`. I proved it in this session: with `DB_DATABASE=atlantech_testing_g1` set in the shell, the tests built their tables in `atlantech_testing_g1` and not in `atlantech_testing` (section 8). This is the per-session database fix of section 3.12.

### 3.10 `tests/TestCase.php`

`abstract class TestCase extends Illuminate\Foundation\Testing\TestCase`. Until P1-C3 it was empty. It now has one override, **`be(Authenticatable $user, $guard = null): static`**:

```php
$this->app['auth']->forgetGuards();
return parent::be($user, $guard);
```

**Why:** signed-in routes use `auth:sanctum` since P1-C3 (F11). Sanctum's guard keeps the first user it resolved for the life of the application instance. A test that signs in a second user (`actingAs($a)` then `actingAs($b)` in one test) would still be answered as the first user. `forgetGuards()` drops the cached guard instances before each sign-in. `actingAs()` calls `be()`, so every Feature test gets the fix. Anyone who builds a test with two users does not need to do anything; anyone who bypasses `actingAs()`/`be()` (for example by setting the user on a guard by hand) gets the old behaviour.

### 3.11 `tests/Pest.php`

| Item | What it does |
| --- | --- |
| `pest()->extend(TestCase::class)->in('Feature')` | Every test under `tests/Feature` runs inside a booted Laravel application (`$this->getJson(...)`, `config()`, the container). Tests under `tests/Unit` are **plain Pest tests with no application** unless they bind it themselves (for example `ErrorCodeTest` and `ModuleArchitectureTest` need none). |
| `// ->use(RefreshDatabase::class)` | Commented out on purpose: `RefreshDatabase` would wrap tests in a transaction on whatever database is configured and could not run `migrate:fresh` once on MySQL. The project uses `useSchemaDatabase()` instead. |
| `expect()->extend('toBeOne', ...)` and `function something()` | Skeleton leftovers from `pest --init`, unused. Safe to delete in a cleanup. |
| `useSchemaDatabase(): void` | See below. |
| `makeUserWithRole(?Role $role, array $attributes = [], ?OrganizationUnit $unit = null): User` | Test helper used by the Access tests (F07 to F09). Creates a `User` from its factory; with a null role returns a user with no active role. Otherwise picks the unit for the role: a terminal-scoped role gets a **new** terminal unit (`OrganizationUnit::factory()`) unless `$unit` is passed; `PORT_GM`, `PORT_STAFF` and `MARINE_SAFETY_OFFICER` reuse (or create) the single Harbour Authority unit; every other role reuses (or creates) the single Marine Services unit. Then it creates one `UserRoleAssignment` for the user, unit, role and `unit_type`. Returns the user. |

**`useSchemaDatabase()`** (`tests/Pest.php:64-78`):

1. It sets `database.default` to `mysql` and `database.connections.mysql.database` to `env('DB_SCHEMA_TEST_DATABASE', 'atlantech_testing')`. Note the environment variable it reads is `DB_SCHEMA_TEST_DATABASE`, **not** `DB_DATABASE`.
2. `DB::purge('mysql')` drops the cached connection so the new database name takes effect.
3. A `static $migrated` flag makes the next step run **once per PHP process**: `Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true])`. `migrate:fresh` drops every table in that database and runs all migrations.

Consequences:

- The first test in a run that calls the helper wipes the database. Never point it at a database you care about (section 8).
- After the first call the schema is reused: later tests do **not** rebuild it. They must leave the data clean. The common pattern is `DB::beginTransaction()` in `beforeEach` and `DB::rollBack()` plus `DB::disconnect()` in `afterEach` (see `PaginationTest`, `UtcDateTimeTest`). Tests that commit data must delete it.
- 30 test files call the helper (the Access tests including `MobileSignInTest`, `MobileTokenTest` and `MobileLogoutTest`; the Audit, Database, Models and Seeders tests; `SystemInfoTest`; plus `PublishStatusVocabularyTest`, `PaginationTest`, `UtcDateTimeTest`, `VersionTokenTest` and `WriteChainOrderTest`). Tests that do not call it (for example `ProblemResponseTest`, `OpenApiDriftTest`) never touch the schema.

### 3.12 Test groups and how to select tests

`vendor/bin/pest --list-groups` printed (checked in this session):

```
 - arch (14 tests)
 - default (406 tests)
 - infra (3 tests)
 - schema (390 tests)
```

| Group | Where it is set | Meaning |
| --- | --- | --- |
| `schema` | `->group('schema')` at the end of tests in `SchemaTablesTest`, `SchemaConstraintsTest`, `DatabaseRulesTest`, `ContractsSchemaTest`, `WorkerGrantsTest`, `ModelLayerTest` and `WebSignInTest` (one test) | Tests that need the real MySQL schema (CHECK constraints, generated columns, grants, `mysqldump`). The counts include dataset rows. |
| `infra` | `InfrastructureTest` (2 tests) and the MySQL-timezone test in `UtcDateTimeTest` | Tests that reach the real services from `docker-compose.yml`: MySQL answers, Redis stores a key, the MySQL session time zone is `+00:00`. |
| `arch` | added by Pest to tests written with `arch()` (`ModuleArchitectureTest`) | The module-layer rules (F01). |
| `default` | Pest's name for tests with no explicit group | |

The group name is **not** a reliable marker for "needs MySQL": many tests that call `useSchemaDatabase()` (for example `AbilityMiddlewareTest`, `PaginationTest`) have no `schema` group. Treat every Feature test as "needs MySQL up" unless it is clearly a pure routing or renderer test (`ProblemResponseTest`, `OpenApiDriftTest`, ...).

### 3.13 Parallel runs and the per-session database

**The problem.** The default test database is one name, `atlantech_testing`. Each PHP process that calls `useSchemaDatabase()` runs `migrate:fresh` on it once. Two runs at the same time (two Claude Code sessions, two worktrees, a CI-like run plus your own) therefore drop each other's tables in the middle of a test: random failures such as "Table doesn't exist" or foreign-key errors. The same shared names exist for scratch objects (finding F2-4).

**The fix, used for every parallel session:**

1. Create an empty database of your own, for example in the Compose MySQL: `docker exec atlantech-mysql-1 mysql -uroot -e "CREATE DATABASE IF NOT EXISTS atlantech_testing_g1"`. (The container name depends on your Compose project folder; `docker compose ps` shows it.)
2. In the shell that runs Pest, set **both** variables to that name:
   - `DB_DATABASE`: used by tests that do not call the helper (they read `phpunit.xml`'s value unless the real environment overrides it).
   - `DB_SCHEMA_TEST_DATABASE`: used by `useSchemaDatabase()`.
3. Run the tests. Do not edit `phpunit.xml` or `tests/Pest.php`.

PowerShell:

```powershell
$env:DB_DATABASE = 'atlantech_testing_g1'
$env:DB_SCHEMA_TEST_DATABASE = 'atlantech_testing_g1'
vendor/bin/pest tests/Feature/Api/PaginationTest.php
```

bash: `DB_DATABASE=atlantech_testing_g1 DB_SCHEMA_TEST_DATABASE=atlantech_testing_g1 vendor/bin/pest ...`.

In CI there is only one run per job, so no per-session name is needed, and the workflow must not set `DB_DATABASE` (section 3.1).

## 4. Why this design

| Choice | Reason | Source |
| --- | --- | --- |
| CI on pull requests into `develop` only, not on every push and not on `main` | Saves minutes and noise; the gate is the PR. Overrides the roadmap wording "on every push". | Lead, 2026-10-28 (P1-A3 Q3) |
| No Larastan or PHPStan | Not needed for a graduation project; overrides the task text "Pint, Larastan, Pest". | Lead, 2026-10-28 (P1-A3 Q1) |
| `setup-php` plus service containers, not the project's Docker image | Standard, fast, a `mysqldump` client is on the runner; PHP 8.4 and the same extensions as the Dockerfile. | Lead, P1-A3 Q4 |
| Public repository, Actions on | Free minutes. | Lead, P1-A3 Q7 |
| No coverage gate | No domain code when planned; add a coverage report first, per-area gates later (NFR-EXT-4). | Lead, P1-A3 Q6 |
| `openapi` job with a pinned Spectral through `npx`, no `package.json` | A moving version could break the check; the project does not need a Node dependency for one lint. | Lead, P1-A4 Q4 |
| Jobs skip draft PRs | Do not spend minutes on work in progress. | P1-A3 plan section 3 |
| `labeler` uses `pull_request`, not `pull_request_target` | `pull_request_target` runs with a write token on code from a fork; the team has no forks, so the safe event is enough. | `labeler.yml` comment |
| `ai-impact` for migrations and grants | AI workers read and write those tables (CT-15) and have their own database roles (DEC-08); a schema change needs the AI lead. | CT-17 section 6 |
| `CODEOWNERS` for review requests only | Rashwan is asked, but the ruleset does not block on code-owner approval. | `CODEOWNERS` comment |
| Tests always on MySQL | The schema relies on CHECK constraints, generated columns and ENUMs that SQLite cannot run. | CT-17; `Pest.php:60-63` |
| `migrate:fresh` once per process, not `RefreshDatabase` | Building 92 tables for each test would take minutes. | P1-B1 plan |
| Required checks are `pint`, `tests`, `openapi`, one approval, admin bypass kept, head branches deleted after merge | The lead set GitHub **rulesets** (the free plan has no classic protection): `develop-guard` on `develop` (PR required, 1 approval, review threads resolved, no deletion or force push, the three checks), plus `main-lockdown` and `release-guard`. Org admins keep an `always` bypass, since Marwan and Rashwan review each other. The plan said 0 approvals and "do not delete head branches"; the lead changed both. | P1-A3 log, 2026-10-29 |

Rejected: Larastan; CI on every push; building the Docker image in CI (to be reconsidered when deployment work starts, P6); a custom `pint.json`.

GitHub settings are not stored in the repository. I could not check the live ruleset from here: the table above is the plan file's record (Unverified against GitHub).

## 5. How it connects

- **F01:** CI builds the same shape as Compose (same images, same extension list except `opcache`). `ModuleArchitectureTest` is the project's static dependency analysis.
- **F03 and F04:** `OpenApiDriftTest` and `ErrorCodeTest` run inside job `tests`; Spectral runs in job `openapi`. A new error code or endpoint must satisfy both.
- **F05:** the `ai-impact` rule watches the migrations and grants. `ContractsSchemaTest` needs `mysqldump`.
- **F14:** the tooling that writes PortHub files (`composer contracts:*`) is outside CI; the paired PortHub PR flow is documented there and in F04.
- **Every later PR** must pass the three checks. A feature that needs a new service (Reverb in P5-N2, object storage) must add it to the `tests` job.
- **Module layer rule:** the workflow and templates are outside `app/Modules`; no layer rule applies.
- **PortHub** has its own `.github` (not covered here).

## 6. Data

None. CI creates and discards its own MySQL service. The test databases:

| Database | Used by | Who creates it |
| --- | --- | --- |
| `atlantech` | `InfrastructureTest` only (`select 1`); CI creates it with `MYSQL_DATABASE` | Compose, or the CI service |
| `atlantech_testing` | Default for `phpunit.xml` and `useSchemaDatabase()`; `migrate:fresh` rebuilds it (101 tables after a run on `9559d35`: 92 port tables plus framework tables, now including `personal_access_tokens` from P1-C3) | Created by hand (`CREATE DATABASE`) or by the CI step |
| `atlantech_testing_<yours>` | Your per-session override | You |
| `atlantech_schema_test`, `atlantech_schema_rebuild` | Scratch databases created and dropped by `ContractsSchemaTest` | The test itself |

## 7. Tests

The CI is itself the thing under test; the P1-A3 plan added no Pest tests. The proofs are the runs:

| Evidence | What it proves | Where |
| --- | --- | --- |
| PR #9 run 40657506647 | First green run: `pint` 19 s, `tests` 56 s (at that time) including `ContractsSchemaTest` | P1-A3 log |
| Demo PR #26 (`tests/Unit/CiGateDemoTest.php`, failing on purpose) | A failing test turns `tests` red, `pint` and `openapi` stay green, merge state BLOCKED; closed without merge | P1-A3 log, 2026-10-29 |
| `ModuleArchitectureTest` | The module-layer rule runs in CI (F01) | `tests/Unit/Modules/ModuleArchitectureTest.php` |
| PRs #55 (P1-C3), #56 (P1-C4), #57 (review fixes), read with `gh pr view` on 2026-11-06 | All three carry the label `ai-impact` (P1-C3 added the `personal_access_tokens` migration, so the labeler rule fired as designed). All three merged with `reviewDecision` still `REVIEW_REQUIRED`, which means the lead used the admin bypass that the ruleset keeps (section 4). #55 and #56 show the four checks `pint`, `labeler`, `openapi`, `tests` all `SUCCESS`. **#57 shows only `labeler`**: no `pint`, `tests` or `openapi` result is attached to it, so it merged without the three required checks (Unverified why: possibly the bypass, possibly the checks did not run; check the PR's Actions tab) | GitHub, `Atlantech-project/project-backend` |

Test files that exist only as skeleton: `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php` (the Laravel samples; harmless).

## 8. Gotchas and invariants

- **Never change the job `name:` values** `pint`, `tests`, `openapi`. They are the required check names in the `develop-guard` ruleset; renaming one leaves the PR waiting for a check that never reports.
- **Do not set `DB_DATABASE` in `ci.yml`.** A real environment variable beats `phpunit.xml` and would move the tests off `atlantech_testing` (the step that creates that database would then be pointless).
- **`MYSQL_DATABASE: atlantech` must stay** in the `tests` service: `InfrastructureTest` hard-codes `atlantech` (`tests/Feature/InfrastructureTest.php:13`).
- **`useSchemaDatabase()` wipes its database.** Never set `DB_SCHEMA_TEST_DATABASE` (or leave the default `atlantech_testing`) pointing at a database that holds data you need. Never use the lead's `atlantech` database for tests.
- **Parallel runs on one database corrupt each other** (section 3.13). Set both variables.
- **Docker must be running for most Feature tests.** With MySQL down, a test that needs it fails on the connection; the F01 note says the suite can hang, so start Compose first.
- **PR template ticks are not enforced.** The drift test and Spectral enforce the OpenAPI rule; nothing enforces the others.
- **Only same-repo PRs are labelled.** A fork PR cannot write the label (by design).
- **The cache key uses `ATLANTECH/composer.lock`.** Adding dependencies changes the key; the fallback `restore-keys` still restores a nearby cache.
- **`composer install --no-scripts` in the `pint` job** means anything that needs `package:discover` must not be added to that job.
- **`composer post-update-cmd` runs `boost:update`**, which can rewrite `CLAUDE.md` and `AGENTS.md` after `composer update` (F01). Check `git status` before committing.

## 9. How to extend it

**Add a CI step or job.** Edit `.github/workflows/ci.yml`. Give the job `if: github.event.pull_request.draft == false`, a `timeout-minutes` and a `name:`. If it must block merges, ask the lead to add its `name` to the `develop-guard` ruleset; a new job is not required until then. A job that needs a service (Redis, MySQL) copies the service block of `tests`.

**Add a PHP extension.** Add it to the `install-php-extensions` line in `Dockerfile` and to `extensions:` in the `tests` job (F01 section 9).

**Change what triggers `ai-impact`.** Add a glob under `any-glob-to-any-file` in `.github/labeler.yml` (paths start with `ATLANTECH/`), and add the same folder to `CODEOWNERS` if the AI lead should be asked to review it.

**Add a CODEOWNERS rule.** One line: path, then `@login`. Remember the ruleset does not enforce it.

**Add a test group.** End the test with `->group('name')`. Select it with `--group=name` or skip it with `--exclude-group=name`. Document the group in this file.

**Write a test that needs the database.** Call `useSchemaDatabase()` in `beforeEach`. Wrap the test in `DB::beginTransaction()` and `DB::rollBack()` plus `DB::disconnect()`, or clean up the rows you created. Use model factories (`CLAUDE.md` Pest rules).

**Write a test that needs a user with a role.** Use `makeUserWithRole(Role::PortGm)` (section 3.11).

**Bump the Spectral version.** Change the number in `ci.yml:70`, run the lint locally with the same command, and update the version quoted in the `openapi-sync` skill if it is named there.

## 10. Try it

From `project-backend/ATLANTECH`. PHP comes from Herd, so in this environment run these from PowerShell.

Style, exactly what CI runs (I ran this):

```bash
vendor/bin/pint --test
```
Output I got: `{"tool":"pint","result":"passed"}` (the tool prints JSON when called by an agent; in a normal terminal it prints a table or nothing on success).

Test groups (I ran this):

```bash
vendor/bin/pest --list-groups
```
Output (on `9559d35`): `arch (14 tests)`, `default (406 tests)`, `infra (3 tests)`, `schema (390 tests)`.

A narrow run on your own database (I ran the set below with `DB_DATABASE` and `DB_SCHEMA_TEST_DATABASE` both set to `atlantech_testing_g1`, created empty first):

```powershell
docker exec atlantech-mysql-1 mysql -uroot -e "CREATE DATABASE IF NOT EXISTS atlantech_testing_g1"
$env:DB_DATABASE = 'atlantech_testing_g1'; $env:DB_SCHEMA_TEST_DATABASE = 'atlantech_testing_g1'
vendor/bin/pest tests/Feature/Api/PaginationTest.php tests/Feature/Api/UtcDateTimeTest.php
```
The 10-file F03/F04 set (errors, pagination, UTC, OpenAPI drift, publish command) gave: `tests 122, passed 122, assertions 699, 42 s` (first run on `106b40e`). Re-run on 2026-11-06 on `9559d35` with `SystemInfoTest` and `RouteAbilityCoverageTest` added: `tests 136, passed 136, assertions 734, 51 s`. Afterwards `atlantech_testing_g1` held 100 tables and `atlantech_testing` was not touched by this run, which shows the override works.

Only the infrastructure group (needs `docker compose up -d`; not run by me because `InfrastructureTest` uses the lead's `atlantech` database name, read-only):

```bash
vendor/bin/pest --group=infra
```

Full suite, what CI runs (**expected, not run:** it takes several minutes and shares the MySQL with other sessions):

```bash
php artisan test --compact
```
or `composer test`. Expect all green. `composer lint` is the local alias of the style check.

Spectral, what the `openapi` job runs (**expected, not run:** `npx` downloads the package from the network):

```bash
npx --yes @stoplight/spectral-cli@6.17.0 lint openapi/port-api.yaml --fail-severity=warn
```
Expect no output and exit code 0.

To watch the gate itself, open a draft PR (jobs are skipped), mark it ready (they start), and push a second commit quickly (the first run is cancelled).

## 11. Open points

- **F2-1 · README says two jobs.** `project-backend/README.md:159-167` ("Continuous integration") says "Two jobs run in parallel" and "Both are required checks". There are three jobs and three required checks since P1-A4; the paragraph was not updated.
- **F2-2 · Stale comment in `.spectral.yaml`.** It says "with one path, 18 of them are still unreferenced" and "Turn it back on once they are used". The file now has five paths, and some components are still unused (`AppVersion`, `DeviceId`, `IdempotencyKey`, `bearerAuth`, the mobile headers), so `oas3-unused-component` must stay off until the mobile endpoints land. The `openapi-sync` skill says to turn it on "with the first path": that instruction would make the job fail today. See F04.
- **F2-3 · `opcache` missing in CI.** The `tests` job comment says the extensions match the Dockerfile; `opcache` is not in the list. Harmless for tests (CLI), but the comment is inaccurate.
- **F2-4 · Shared scratch names break per-session isolation.** `ContractsSchemaTest` creates `atlantech_schema_test` and `atlantech_schema_rebuild` (names fixed in the test, `ContractsSchemaTest.php:15-18`), and `WorkerGrantsTest` creates the server-wide MySQL user `t_worker` and roles `t_r_*` (`WorkerGrantsTest.php:25-44`). Two sessions running these tests at the same time collide even with separate test databases. Also `ContractsSchemaTest.php:46-50` asserts that a database literally named `atlantech_testing` already exists and has **101 tables**, regardless of `DB_SCHEMA_TEST_DATABASE`: on a fresh MySQL where nobody has built `atlantech_testing`, that test fails with a message that points at the wrong cause. I did not run these two files (shared names; see F05). Since P1-C3 the same test hard-codes the number **101** in two places (`ContractsSchemaTest.php:36` and `:50`) and the name of the last migration (`:25`): every new table or migration needs this test edited, and the `:50` count reads the shared `atlantech_testing`, not your own database.
- **F2-5 · No automatic check for the PR template.** Ticks (including "paired PortHub PR linked") rely on the author and reviewer.
- **F2-6 · Unverified GitHub settings.** The ruleset content, the label `ai-impact` existing (or being created by the action) and the labeler's `sync-labels` default were not checked against GitHub from this session.
- **F2-7 · Skeleton leftovers in `tests/Pest.php`** (`toBeOne`, `something()`) and the two `ExampleTest.php` files.
- **F2-8 · Deferred by decision:** coverage gates (NFR-EXT-4), a Docker image build job (P6), the contract-test job (P3-I8), the AI CI (AI-016) and the web CI (FE-012).
- **F2-9 · Known advisories.** The P1-A4 review noted `composer audit` advisories on `phpunit/phpunit` and `symfony/mime` (2026-10-29). Not re-checked here; `phpunit/phpunit` is at 13.4.3 in `composer.lock`.
- **F2-10 · PR #57 merged with only `labeler` attached** (section 7). If the three required checks did not run on a PR into `develop`, that is a hole in the gate; if the lead bypassed them on purpose, say so in the PR. Not verified.
- **F2-11 · Hard-coded table count in `ContractsSchemaTest`.** The number 101 (two places) and the last-migration name must be edited with every new table or migration (they were changed in P1-C3 from 100 and `2026_11_03_183535_alter_port_settings_add_min_max`). A guard test added in P1-C3 to `WorkerGrantsTest` fails if `database/grants/workers.sql` contains a line with `DEMO` or `do not merge`, because that file is published into `schema.sql` (F05).
- **F2-12 · README and `.spectral.yaml` still stale** at `9559d35` (F2-1, F2-2 unchanged); the PortHub `project-backend` pointer was still `106b40e` when I checked on 2026-11-06.
