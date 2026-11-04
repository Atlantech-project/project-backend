# Z · Findings register

Everything the guide writers found while reading the code that is **not** the way it should be, or that a new engineer should know before changing things. Nothing here was fixed: the guide only documents. Each row names the guide file that explains it in detail.

Written 2026-11-04 from the reports of the four writing sessions and the completeness pass. Code state: backend `develop` at `106b40e`, PortHub `main` after #38.

## Z1. Needs a decision or a fix first

| # | Finding | Why it matters | Where | Suggested next step |
| --- | --- | --- | --- | --- |
| 1 | **Other sessions after a password change.** The P1-C2 plan (the lead's answer of 2026-10-31) says other web sessions stay open. But `config/sanctum.php` enables `AuthenticateSession`, so they are closed (a probe returned 401 after the hash changed). No test covers either behaviour. | The code and the recorded decision disagree. Mobile tokens (P1-C3, question 8) will need the same answer. | F07, F08 | Decide: remove the middleware, or change the plan text and add a test. |
| 2 | **The audit log is append-only only in Eloquent.** Nothing in the repo limits the application database account to INSERT and SELECT on `audit_logs`; `workers.sql` restricts only the AI workers. `DB::table('audit_logs')->delete()` still works. | The "audit rows can never be changed" promise depends on deployment work (P6). | F10, F05 | Track as a P6 task: a grant or trigger for the app account. |
| 3 | **`terminal.scope` protects only a route parameter named `terminal`.** A `terminal_id` in the body or query, and any list, needs the controller to call `TerminalScope::authorize` or `restrict`. Nothing warns if it forgets. | Every new terminal-aware endpoint (E-chain, master data, BAPLIE) can leak another terminal's data by omission. | F09 | Add a test or a review checklist item for each such endpoint. |
| 4 | **`RouteVersionCoverageTest` checks only PUT and PATCH.** A POST "action" route that changes an existing record (for example `POST users/{user}/deactivate` in the P2-E1 plan) can skip `version.token`. No test forces an endpoint to call `lockAndAssert`; no race test with a second connection. | The 409 protection is optional where the plan most needs it. | F12 | Widen the test to POST routes with a model parameter, and decide per route. |
| 5 | **`AuditService::rejectedAttempt` called inside an open transaction is rolled back with it.** It runs in its own transaction and must be called after the refused write rolled back. | A refused write inside a transaction silently loses its audit row. | F10, F12 | Keep the rule in the PR checklist. |
| 6 | **`abort(409\|422\|423\|426)` answers 400 `BAD_REQUEST`.** The renderer maps only the codes it knows. | A developer using `abort(409)` gets the wrong code, and nothing stops it. | F03 | Ban `abort()` for catalogue codes (already the team rule); consider a test. |
| 7 | **Parallel test runs still collide, even with a separate database each.** `ContractsSchemaTest` creates databases with fixed names (`atlantech_schema_test`, `atlantech_schema_rebuild`) and expects `atlantech_testing` with exactly 100 tables whatever `DB_SCHEMA_TEST_DATABASE` says. `WorkerGrantsTest` creates server-wide users and roles (`t_worker`, `t_r_*`). | Two sessions running these tests at once break each other. | F02, F05 | Run these two tests in one place at a time; consider giving them unique names. |
| 8 | **Published contract files are behind.** PortHub `contracts/db/schema.sql` lacks `min_value` and `max_value` (DEC-59) and its header still says CT-17 v1.0. The backend's `docs/reference` copies of the Contracts and Decisions files show DEC-59 Open and CT-18 v1.1 as proposed (a sync PR is pending). | Other teams build from the published files. | F05, F13, F14 | Run `composer contracts:schema` on a PortHub branch; do the `sync-check --apply` PR. |
| 9 | **`workers.sql:592` contains `-- DEMO (do not merge)`** and is on `develop`. | It will be copied into the next `schema.sql`. | F05 | Remove the line in the next change to that file. |
| 10 | **Plans for the mobile and E chains sit on local branches** (`claude/p1-c-mobile-signin-097e28`, `claude/p2-e-users-404561`) and name B6 as a blocker although B6 merged. | They can be lost, and they are out of date. | F15 | Put them in a PR and refresh the "waits for" lines before implementing. |

## Z2. Know before you build the next tasks (E1 to E3, C3 to C5)

| Finding | Why it matters | File |
| --- | --- | --- |
| `EnsureMinAppVersion` and `EnsureLicenseAllowsWrite` are empty pass-throughs. The first test of `WriteChainOrderTest` assumes that and must change when they are filled. | C3 fills the first one. | F11 |
| `personal_access_tokens` does not exist yet. | C3 creates it; E3 revokes tokens through it. | F05, F15 |
| `VersionToken` is not used by any real endpoint, and no Resource returns a `version`. | E1 to E3 are the first users. | F12 |
| `Gate` definitions and the `EnsureAbility` middleware are two routes to the same answer; routes use only the middleware. | Pick one style for new code (the middleware). | F09 |
| `SignInService::recordRejectedAttempt` still exists and is used by `EnsureAccountUsable` and `ChangePasswordRequest`. | It forwards to `AuditService`; do not add new callers. | F10 |
| `after_state.username` stores what was typed, so a password typed into the username field is stored (lower-cased). Only the Port Admin can read it. | Privacy note for the audit page. | F07, F10 |
| `trustProxies` is not configured, so behind a reverse proxy the 20-per-minute IP limit may apply to the whole port. | Tablets make this more likely. | F07 |
| The OpenAPI 403 for `audit-logs` mentions only `FORBIDDEN`, but `PASSWORD_CHANGE_REQUIRED` and `ACCOUNT_DISABLED` can also occur. | Keep the response list accurate for new routes. | F10, F04 |
| 33 of 92 factories fail with FK error 1452 on an empty database (59 work alone). | A factory is not a stand-alone fixture; build parents first. | F06 |
| Demo users start with `must_change_password = true`. | Anyone signing in as a demo user must change the password first. | F13 |
| The fresh Docker environment needs `docker compose exec app php artisan migrate --seed`; nothing runs it automatically (the README documents it). | Expected for Frontend, Mobile and QA setup. | F13 |

## Z3. Documentation and tooling drift

| Finding | File |
| --- | --- |
| `project-backend/README.md` says CI has "two jobs" and "both required"; there are three (`pint`, `tests`, `openapi`) plus the `labeler`. | F02 |
| `.spectral.yaml` has an old comment ("one path, 18 unreferenced"); the file now has 5 paths and still-unused components. | F02, F04 |
| The `openapi-sync` skill tells you to enable `oas3-unused-component` and `openapi-tags` "with the first path"; doing so would fail the `openapi` job. It also still names `0.1.0`. | F04, F14 |
| The `schema-auditor` agent still says the version is an integer column (outdated since DEC-57). | F14 |
| CI's `tests` job comment says its extensions match the Dockerfile, but `opcache` is missing from CI (no effect on tests). | F02 |
| P2-D5 and P2-D6 appear in the backend tasks file but not in `roadmap-tasks.json` (no sprint, no plan). | F15 |
| The plan of P1-A4 says 16 error codes; the code and `error-codes.md` have 17 (with `BAD_REQUEST`). | F03 |
| The published `port-api.yaml` in PortHub has CRLF line endings; compare with `diff --strip-trailing-cr`. | F04 |
| 46 of 92 model docblocks are placeholders ("The `x` table."); `TerminalRecommendation` has a broken UTF-8 character. | F06 |
| `yard_runs.retry_count` has no CHECK (`chk_yr_retry` was proposed and never added). | F05 |
| `password_reset_tokens` is keyed by `email`, but users have no email. | F05 |
| `ZonedDateTime` accepts an offset such as `+25:00` because it checks only the shape. | F03 |
| Pest leftovers: `toBeOne`, `something()` in `tests/Pest.php`; two `ExampleTest` files. | F02 |
| No tests for the `OriginMismatchException` branch (403) or `redirectGuestsTo` on `api/*`. | F03 |
| The drift test checks routes, error codes, paging limits and the cookie name only; the `info.version` bump and the PortHub copy are manual. | F04 |
| Open decisions that matter: DEC-30 (numeric thresholds, AI, close-by Sprint 2 has passed) and DEC-38 (UI/UX, Sprint 3). | F15 |

## Z4. Corrections made in the guide itself

| Correction | Detail |
| --- | --- |
| README seeding claim | An earlier draft of F13 and F15 said the project README has no `migrate --seed` instructions. That came from searching `ATLANTECH/README.md` (Laravel's default file). The real README (`project-backend/README.md`, lines 113, 129, 132) has them. F13 and F15 were corrected. |
| `RouteVersionCoverageTest` | It was listed under F04 in the index; it tests the version-token rule and belongs to F12. Fixed in the index. |
| "8 generated unique keys" | There are 12 unique indexes over 8 distinct column names. F05 explains it; the index wording was clarified. |
| Model file count | `app/Modules/*/Models/*.php` has 93 files (92 models plus `AuditLogBuilder`); the 92-model figure is correct. |
