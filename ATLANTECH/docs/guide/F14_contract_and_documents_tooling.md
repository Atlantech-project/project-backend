# F14 · Contract and documents tooling

Task origin: P1-A4 (OpenAPI), CT-21 (status vocabulary), P2-D1 (role matrix), P1-B3/CT-17 (schema, worker accounts), the workflow work in `docs/tasks/TOOLING.md`. Facts checked against `develop` at `106b40e` and PortHub `main` at `0689c90`, and re-checked on 2026-11-06 against `develop` `9559d35` and PortHub `main` `1810aaa` (changes since the first version say "2026-11-06"). Paths are inside `project-backend/ATLANTECH/` unless stated.

## 1. What it does

This feature is **the machinery that keeps the shared contracts and the project documents honest**. It has three parts:

1. **Publishing commands** (5 Artisan commands): four write a contract file into the PortHub repository from the backend's own code, so the code stays the single source and the published file is a generated copy (`PublishOpenApi`, `PublishStatusVocabulary`, `PublishRoleMatrix`, `GenerateContractsSchema`); the fifth, `CreateWorkerAccounts`, is database tooling for the AI workers' MySQL accounts (listed here because the index puts it in the commands group).
2. **The document reference** `docs/reference/`: copies of the team's documents inside the repo, a manifest, a sync checker, an ID index and a lookup tool, so Claude Code and engineers read the right lines cheaply.
3. **The workflow**: task plan files in `docs/tasks/`, and the team's Claude Code skills and agents in `.claude/`.

It does **not** publish anything to PortHub by itself in CI (publishing is a manual step in a paired PR), does not write `ATLANTECH_Contracts.md` or the decisions file (those are edited by the lead in PortHub and the shared folder), and does not copy anything from the backend into `Code Notes`.

## 2. Request or data flow

**The paired-PR flow** for a backend change that has a contract artefact (A2, A7):

1. Work on a backend branch `feature/<task-id>-<slug>` from `origin/develop`. Change the code (and `openapi/port-api.yaml` for an endpoint, an enum for a status, the role matrix for abilities, a migration for the schema).
2. Run the matching command (`composer contracts:openapi`, `contracts:status-vocabulary`, `contracts:role-matrix`, `contracts:schema`). It writes into `../../contracts/` relative to `ATLANTECH/`, which is the PortHub checkout **when the backend is checked out as a submodule at `PortHub/project-backend/`** (see 3.2). Use a branch of PortHub named the same way (`feature/<task-id>-openapi`, etc.).
3. Commit the generated file in PortHub, open the PortHub PR into `main`, link it from the backend PR (and the backend PR from it). CI of the backend checks the OpenAPI source (Spectral lint, drift test, F02/F04) but nothing checks that the published copy is current.
4. Merge the backend PR into `develop` first. Only afterwards, in one later PortHub PR, move the submodule pointer to the new `develop` commit (A2: Sprint 2 ended with one pointer PR for six backend PRs).

**Inside a command** (all four contract writers follow the same pattern): resolve the output path from `--output` or default to `base_path('../../contracts/<file>')`; if the target folder is missing, print `Folder not found: <dir>. Run from the PortHub checkout or pass --output.` and return failure; otherwise generate and write; print `Wrote <realpath>.`

**Reading documents** (the point of `docs/reference`): `node docs/reference/lookup.mjs <ID>` prints the ID's title, status, split file, exact `file:lines` and linked IDs; the reader opens only those lines (skill `context-rules`).

## 3. Every piece of code

### 3.1 `PublishOpenApi` (`app/Modules/Shared/Console/PublishOpenApi.php`)

- Command `atlantech:contracts-openapi {--output=}`; composer script `contracts:openapi`. Registered in `SharedServiceProvider::boot` (only when `runningInConsole()`).
- Default output: `base_path('../../contracts/openapi/port-api.yaml')`.
- `handle(): int`: (1) the output folder `contracts/openapi` may be created, but only if **its parent** (`contracts/`) exists (`is_dir($folder) || is_dir(dirname($folder))`), otherwise error and `FAILURE`; (2) `mkdir` if needed; (3) `copy(base_path('openapi/port-api.yaml'), $output)` byte for byte; (4) prints `Wrote <path>.`
- Why: the backend owns the OpenAPI file (DEC-13, Closed 2026-10-29, option 1); the PortHub file is a published copy and nobody edits it by hand (CT-02). The source of truth stays in the backend because the drift test (F04) compares it with the real routes.

### 3.2 `PublishStatusVocabulary` (`app/Modules/Shared/Console/PublishStatusVocabulary.php`)

- Command `atlantech:contracts-status-vocabulary {--output=}`; script `contracts:status-vocabulary`. Default output `base_path('../../contracts/status-vocabulary.json')` (CT-21).
- `handle(): int`: for every file `app/Modules/*/Enums/*.php`: derive the class `App\Modules\<Module>\Enums\<Name>`; if it is not an enum, error `<class> is not an enum.` and fail; read its class PHPDoc (`ReflectionEnum::getDocComment`). An enum tagged `@not-stored` (a vocabulary that exists only in code, such as `Ability`) is skipped. Otherwise every `` `table.column` `` in backticks in the doc names a column it describes (regex `` `([a-z0-9_]+\.[a-z0-9_]+)` ``); no such name: error and fail. An enum tagged `@derived` goes under `"derived"` (values computed in code, such as `plan_components.derived_status`), all others under `"columns"`. The value list is the enum's `cases()` values as strings. If two enums name the same column: error and fail.
- The result `{ "version": "1.0", "columns": {…}, "derived": {…} }` is sorted by key (`ksort`) and written as pretty JSON with a trailing newline, so two runs give the same bytes.
- Why: the front ends and the AI team must use the exact SQL names. The PHP enums are backed by the SQL values (F06), so generating from them removes drift. The test also compares the result with the database's real ENUM columns.
- Convention for authors: **every new enum's PHPDoc must name its `table.column` in backticks**, or tag it `@derived` / `@not-stored`; the command fails otherwise.

### 3.3 `PublishRoleMatrix` (`app/Modules/Access/Console/PublishRoleMatrix.php`)

- Command `atlantech:contracts-role-matrix {--output=}`; script `contracts:role-matrix`. Registered in `AccessServiceProvider`. Default output `base_path('../../contracts/role-matrix.md')`.
- Constants: `VERSION = 'v1.0'` and `CHANGE_LOG` (a list of `[version, date, text]`; one row today: "First version: 12 roles, 27 abilities (P2-D1)…"). **Add a row and bump `VERSION` whenever the matrix changes.**
- `handle(): int` as the pattern; `document(): string` builds the Markdown: a header table (Version, Status "Draft: for review by Frontend and QA", Owner, Reviewers, Source), a generated-file note, a table "Ability by role" (one row per `Ability`, one column per `Role`, `yes` where `RolePermissions::allows`), a list "Abilities of each role" (`RolePermissions::abilitiesFor`), a "Rules that go with the matrix" section (open routes, terminal scope roles from `Role::isTerminalScoped()`, field roles from `isFieldRole()`, the Marine Safety Officer note, the check order, terminal type, audit), and the change log.
- Why: Frontend (menus, FE-022) and QA (FE-102) need the matrix; the code (`RolePermissions`, F09) is the truth.

### 3.4 `GenerateContractsSchema` (`app/Modules/AiBridge/Console/GenerateContractsSchema.php`)

- Command `atlantech:contracts-schema {--output=} {--database=atlantech_schema}`; script `contracts:schema`. Registered in `AiBridgeServiceProvider`. Default output `base_path('../../contracts/db/schema.sql')` (CT-17 section 5).
- `handle(): int`: (1) check the output folder exists; (2) **safety:** the scratch database name must be new: it fails with "Database X already exists; the scratch database must be new because it is dropped afterwards" if the name equals the configured database or exists in `information_schema.SCHEMATA`; (3) `CREATE DATABASE` the scratch (utf8mb4, `utf8mb4_0900_ai_ci`), switch the `mysql` connection to it (`useDatabase`), run `migrate:fresh --force`, read the last migration name; (4) dump the structure with `mysqldump --no-data --skip-comments --skip-dump-date --set-gtid-purged=OFF --single-transaction` (the CHECK constraints and generated columns survive in a mysqldump, unlike in a Laravel schema dump); (5) write the file: a header (`-- ATLANTECH port database schema (CT-17 vX.Y).`, the "Generated by `composer contracts:schema`… Do not edit by hand." line, `-- Last migration: <name>`), the dump with ` AUTO_INCREMENT=n` removed and CRLF normalised, then `-- Worker roles (database/grants/workers.sql, DEC-08)` and the contents of `database/grants/workers.sql`; (6) in `finally`: `DROP DATABASE IF EXISTS` the scratch and switch back.
- `mysqldumpCommand(string $database): list<string>`: uses `mysqldump` on this machine if `ExecutableFinder` finds it (CI has it), otherwise `docker compose exec -T -e MYSQL_PWD=… mysql mysqldump …` in the Compose `mysql` service. The password goes through `MYSQL_PWD`, never on the command line.
- `header(string $lastMigration): string`: reads the CT-17 version from `docs/reference/split/contracts/CT-17.md` by matching `^| Status | Approved (v…)`; falls back to `unknown`.
- Helpers: `useDatabase()` (set the connection's database and `DB::purge`), `displayPath()`.
- Why: the AI team builds its own test database from `schema.sql` (CT-17), including the worker roles. The scratch-database rule exists because the command drops the database it creates: it must never be an existing one.

### 3.5 `CreateWorkerAccounts` (`app/Modules/AiBridge/Console/CreateWorkerAccounts.php`)

- Command `atlantech:worker-accounts {--host=%}`. Constant `ACCOUNTS` maps `w_part1 => r_part1`, `w_part2 => r_part2`, `w_yard => r_yard`, `w_edi => r_edi`, `w_validate => r_validate`, `w_part3 => r_part3` (DEC-08 matrix order).
- `handle(): int`: reads each account's password from `config('services.ai_workers.db_passwords.<account>')` (env `WORKER_PART1_PASSWORD` … `WORKER_PART3_PASSWORD`, `config/services.php:41-50`); if any is empty it prints `Missing passwords: <ENV names>` and fails **before touching the database**; otherwise runs `database/grants/workers.sql` (`DB::unprepared`: creates the roles and the grants), and for each account `CREATE USER IF NOT EXISTS`, `ALTER USER … IDENTIFIED BY`, `GRANT <role> TO`, `SET DEFAULT ROLE`, printing `account → role`. Safe to run again.
- `passwordVariable(string $account): string` (static): `WORKER_` + upper-case account without `w_` + `_PASSWORD`.
- Detail belongs to F05 (database). It is listed here because it is tooling and not a runtime feature.

### 3.6 `docs/reference/` (the document reference)

| Item | What it is |
| --- | --- |
| `README.md` | The Source of truth rules (A6) and the "Where to look" table: which document answers which question. Read it before planning a task. |
| `manifest.json` | `sourceRoot` (`<documents root>`), `syncedAt`, `watch` (`Code Notes`, `Documentation`), `ignore` (9 paths) and `files` (68 entries, each `{path, source, sha1}`; one has `extract: "roadmap"`). It says which shared file each copy came from and its SHA-1. |
| `sync-check.mjs` | Compares the copies with the local originals. Default report mode: prints `In sync / Changed locally / Missing locally / Untracked`, runs `build-index.mjs --check` and the mirror check, exit 0 in sync, 1 differences or stale index, 2 originals not found. `--apply` copies changed originals in, updates the manifest hashes and `syncedAt`, rebuilds the index and refreshes the mirrors. `--mirrors` refreshes only the copies for other agents. `ATLANTECH_DOCS_ROOT` overrides `sourceRoot`. For an entry with `extract: "roadmap"` it pulls the `const D = {…}` data out of the progress-tracker page as JSON. **Never run `--apply` casually: it rewrites tracked files; do it on a branch and review the diff.** |
| mirror logic in `sync-check.mjs` | Our own skills (every folder in `.claude/skills` that is not in `boost.json`'s `skills` list) are copied to `.agents/skills` and `.junie/skills` for teammates on other tools, and the "# ATLANTECH workflow" section of `CLAUDE.md` is copied into `AGENTS.md`. The report mode says "Copies for other agents (.agents, .junie, AGENTS.md) match" or lists the stale ones. |
| `build-index.mjs` | Builds `index/index.json` and `split/` from the documents: one node per ID (tasks, roadmap tasks, CT, DEC, AIBD, AI tasks, FR, NFR, state machines, tables, sequences, activities, use cases, UML context). Nodes hold the title verbatim, the status where the source has one, the exact source file and line range, and links between IDs. `--check` exits 1 if the index is stale or an ID used in the backend tasks does not resolve. Titles are navigation only, never summaries. |
| `lookup.mjs` | `node docs/reference/lookup.mjs <ID…>` prints a node and the titles of its links; `--find "<words>"` searches titles; a table name works (prefix `table:` optional). It warns (`!`) when a Decision row is filled in but the summary table still says Open. IDs not in the index are reported as "not in the index" or "referenced, but not defined in docs/reference". |
| `index/index.json` | The generated index (918 nodes on 2026-11-04 and on 2026-11-06: no document was added to it since). Never read it whole, never edit it. |
| `split/` | Verbatim sections, one file per decision (59), contract (26), AIBD item (40), table (98) and four UML files; 298 split files in all. Never edit; each cites its source in its first line. |
| `backend/`, `contracts/`, `decisions/`, `requirements/`, `database/`, `uml/`, `ai/`, `architecture/`, `project/`, `proposals/`, `roadmap/`, `ui/` | The copied documents. `roadmap/roadmap-tasks.json` is the task data (sprint, feature, dependencies for every team). `proposals/` is PROPOSED text and never a source. |

### 3.7 `docs/tasks/` (task memory)

One Markdown file per task, named `<ID>.md` (30 files on `develop` at `9559d35`, including `_TEMPLATE.md` and `TOOLING.md`; `P1-C3.md`, `P1-C4.md` and `P1-C5.md` joined on 2026-11-05 and still say `In review` although their PRs 54 to 57 are merged: a status that was not closed out). Built from `_TEMPLATE.md`, which has ten sections: 1 What is asked (with a "Done when" checklist and "Out of scope"), 2 Sources read, 3 Design (names other tasks use, endpoints, data, behaviour), 4 Steps (each with a mode A/B/C/D) and Tests, 5 Other teams, 6 Impact on other backend work, 7 Conflicts and risks, 8 Open questions (with the lead's answers), 9 Progress log, 10 Review. The header table has **Status**, with values `Draft · Ready · In progress · In review · Done` (from the template). Meaning: Draft = blockers listed at the top; Ready = every question answered, nothing blocks; In progress = executing; In review = code done, `backend-task-review` running or finished; Done = merged (the PR number is written in the Status cell). The plan is the task's **memory**: a new session reads it first and continues. On `develop` at `9559d35` the statuses are: `Done` for the Sprint 1 and Sprint 2 tasks closed out before 2026-11-05, `In review` for `TOOLING.md`, `P1-C3.md`, `P1-C4.md` and `P1-C5.md` (the last three are merged, so `In review` is stale: `backend-pr-prepare` step 4 says to set Done after the merge), and the template. The plan files of P2-E1, P2-E2 and P2-E3 are still not on `develop`; they live on the local branch `claude/p2-e-users-404561` (F15).

**2026-11-06, `docs/guide/`:** `develop` now also carries a copy of this guide in `ATLANTECH/docs/guide/` (`00_INDEX.md`, `A_orientation.md`, `F01` to `F15`, `F05b_database_diagrams.md`, `tools/gen_db_diagrams.mjs`). Its index says it is "the shared copy for the team; it was first written in the team's `Code Notes` folder". `sync-check` does not track it. Which copy is the master is Unverified (ask the lead); the two copies can diverge.

### 3.8 `.claude/skills` (instructions Claude Code loads when the task fits)

All are Markdown `SKILL.md` files (some with a `reference/` folder). Backend-specific ones:

| Skill | What it does | Use it when |
| --- | --- | --- |
| `backend-task-plan` | Turns a task ID into `docs/tasks/<ID>.md`: runs `sync-check`, `lookup`, delegates document research to `docs-navigator`, analyses design, other teams, impact, conflicts, steps with modes and tests; asks every open question with `AskUserQuestion`; sets Status Ready only when nothing blocks. Writes no application code. | "plan task X", "analyse X", before any code |
| `backend-task-execute` | Runs the plan: needs Status Ready; agrees the modes A/B/C/D with the lead (A mechanical, B skim, C stop for approval for migrations, contracts, status transitions, write chain, security, audit; D the lead does it); creates the branch from `origin/develop`; per step: change, test, Pint, one commit naming the task, a progress-log line; ends with the plan In review. Never pushes or opens a PR. | "implement/continue task X" |
| `backend-task-review` | Reviews the branch against the plan, contracts, decisions, schema, behaviour rules; writes section 10 with severities Blocker / Must fix / Should fix / Note and a verdict (Ready for PR / Not ready); asks the lead to decide conflicts. Reports, does not fix. | before a PR, after execute |
| `backend-pr-prepare` | Checks readiness (Status In review, verdict Ready for PR, clean tree, `sync-check` clean, tests pass), writes the PR description from the plan, and, only after the lead says so, pushes and opens the PR into `develop`; after the merge sets Status Done. | "prepare the PR" |
| `context-rules` | How to read documents and code cheaply: `lookup` first, read only the cited lines, never read `index.json` whole, cite `file:lines`, record facts in the plan, keep command output small. Every other backend skill and agent follows it. | always |
| `contract-change` | Changing an **approved** contract: current text, impact on both sides, new version (major breaking, minor additive, patch wording), draft DEC entry, edit on a PortHub branch with header `Proposed vX.Y (DEC-nn)`, approval, hand-off. All steps mode C. | a task needs a contract to say something else |
| `decision-close` | Closing an Open decision: gather options and affected items, recommend, ask the lead, record the decision in all **three** places of the decisions document (summary row, section Status, section Decision), run `sync-check --apply`, follow up. | a plan is blocked by an Open DEC |
| `openapi-sync` | Compares real routes, requests and responses with `openapi/port-api.yaml`, edits the file (mode C), bumps `info.version` minor, runs the drift test and Spectral, and after the lead's OK runs `composer contracts:openapi` on a PortHub branch for the paired PR. Has `reference/mapping.md`. | any task that adds/changes an endpoint |
| `api-readiness-pack` | Builds the CT-23 readiness pack for one feature (`contracts/readiness/FT-xx.md`): endpoints from `route:list`, OpenAPI diff, examples per status, seed data, events, mock, known gaps, only from `develop`; then `contract-checker` compares it. | BE-PKn tasks, before a Feature Readiness Review |
| `team-handoff` | Drafts one message per team (Frontend, Mobile, QA, AI, UI/UX, Leads Council) with what changed, why, where, what to do by when. Never sends. Template in `reference/message-template.md`. | after a PR, contract change, decision, readiness pack |

Generic skills (Laravel Boost and similar; they are in `boost.json`'s list so they are not mirrored): `laravel-best-practices` (rules for writing/reviewing Laravel code), `testing-best-practices` (test design), `infer-conventions` (sweep the code base for conventions; `disable-model-invocation: true`, used only on request), `tailwindcss-development` (Tailwind: **not used** by this API-only backend), `deploying-to-cloud` (Laravel Cloud CLI: **not used**; hosting is on the port server).

The team's own skills are mirrored to `.agents/skills` and `.junie/skills` by `sync-check` for teammates on other tools.

### 3.9 `.claude/agents` (sub-agents with their own context)

| Agent | Model / tools | Job |
| --- | --- | --- |
| `docs-navigator` | Haiku; Read, Grep, Glob, Bash | Read-only researcher for the project documents. Starts from `lookup.mjs`, returns a compact table of sources with `file:lines`, verbatim short quotes, links and conflicts. Used by `backend-task-plan`. |
| `task-reviewer` | Sonnet; Read, Grep, Glob, Bash | Runs `backend-task-review` in its own context and returns findings with a verdict; never edits, commits or pushes; allowed commands are listed (`git diff/log/show`, `lookup`, `sync-check` in report mode, `php artisan test --compact`, `route:list`, `pint --test`). |
| `schema-auditor` | Sonnet; Read, Grep, Glob, Bash | Compares migrations (and models) table by table with the agreed schema (`PortSync.sql`, edit sheet, DDL fixes, reports A/B, coding notes); read-only, may use `php artisan migrate --pretend`, `db:table`, `db:show`. Note: its text says the record version is "an integer `version` column on every editable table (CT-01 decision)"; that is outdated: DEC-57 made the version the `updated_at` string (F12). |
| `contract-checker` | Sonnet; Read, Grep, Glob, Bash | Compares code with the CT documents (CT-01…CT-23: endpoints, sign-in, events, mobile sync, AI envelopes, table ownership, schema, mock workers, status vocabulary); returns a table `Contract | Item | Contract says | Code does | Severity`. |

### 3.10 `CLAUDE.md` / `AGENTS.md` workflow section and `boost.json`

`CLAUDE.md` has a Laravel Boost block (regenerated by `composer update`'s `boost:update`) and, after the marker line `# ATLANTECH workflow`, the team's rules (every task follows plan, execute, review; the plan file is memory; sources in `docs/reference`; reading rules; code layout; Git rules; sessions; models (Sonnet by default, tell the lead before Opus work); agents; reply in Egyptian Arabic with English technical terms). `sync-check --mirrors` copies that section into `AGENTS.md`. `boost.json` lists the agents (`claude_code`, `junie`, `opencode`) and the Boost-owned skills.

### 3.11 The published contract files and the PortHub versioning rule

`PortHub/contracts/README.md` lists the files and says: **versioning:** every contract file carries a version and status in its header and a change log; `Draft` becomes `Approved` after its reviewers approve; changing an approved contract needs, in the same PortHub PR, (1) agreement of both sides, (2) a decision-log entry, (3) a new version and a change-log line. Major (`v2.0`) = breaking, and on the API a new base path `/api/v2` next to `/api/v1`; minor (`v1.1`) = an addition that breaks nobody (adding an error code to `error-codes.md` is a minor change in the PR of the task that introduces it, no review, no decision entry). A code, its HTTP status and its `message_key` never change once approved. **CT-01 and CT-02 are special:** the backend code is the source of truth, Frontend and Mobile review critical changes only (a breaking change, or a change to a rule they build on), other changes need no team review and no decision entry, only a new version and a change-log line; QA tests and does not review. Changes reach `main` only by pull request on branches named `feature/<task-id>-<slug>`.

What the four generated files look like in PortHub today: `contracts/openapi/port-api.yaml` (CT-02, `info.version` **1.6.0**), `contracts/status-vocabulary.json` (CT-21, `"version": "1.0"`), `contracts/role-matrix.md` (P2-D1, `v1.0`) and `contracts/db/schema.sql` (CT-17, header `CT-17 v1.0`, last migration `2026_11_05_100000_create_personal_access_tokens_table`). Real check on 2026-11-06: each of the four, regenerated into a scratch folder or scratch database, is **identical** to the PortHub copy (line endings ignored). On 2026-11-04 `schema.sql` was behind; it has been republished (PortHub commit `292cad9`, "publish port-api.yaml 1.6.0 and schema.sql without the DEMO line").

**Contract and decision versions on 2026-11-06** (read from the PortHub files; they changed with P1-C3, PRs 54 to 57 and PortHub 41 and 44):

| Document | Version and status | What changed |
| --- | --- | --- |
| `api-conventions.md` (CT-01) | v1.4, Approved | v1.4: `X-App-Version` is required on every Bearer request (missing or malformed 422, older than the minimum 426); web requests may omit it (D-B) |
| `error-codes.md` (CT-01) | v1.3 | v1.3: added `DEVICE_REVOKED`, 403, `errors.device_revoked`, introduced by P1-C3 (DEC-60) |
| `auth-web.md` (CT-03) | v1.1, Approved | password change closes the user's other sessions and revokes the other mobile tokens (D-A) |
| CT-04 (in `ATLANTECH_Contracts.md`) | **Proposed v1.2** (DEC-60), "waiting for Mobile Team approval"; Approved v1.1 is the last approved version (the same text is in the PortHub file and the shared `Code Notes` file on 2026-11-06). A hand-over note `Code Notes/Mobile_Team_CT-04_v1.2_Handoff.md` asks the Mobile Team for the OK | the code already follows v1.2 (`DEVICE_REVOKED`); the P1-C3 plan said it must not merge before this contract was approved, and it merged (PRs 55 and 57) while the contract text still says Proposed; whether the approval happened outside these files is Unverified, see F15 |
| DEC-60 | Closed (2026-11-01 decision, Option 1) | a revoked device is refused at sign-in with 403 `DEVICE_REVOKED` and no token; a token of a revoked device gets 401 on its next use |
| DEC-59 | Closed 2026-11-04 | `port_settings` min and max (F13) |
| `role-matrix.md` | v1.0 | unchanged |

Note on the changed rule "CT-01 and CT-02 follow the code": the contracts README says a change to CT-01 needs no team review, only a new version and a change-log line; that is how v1.3 and v1.4 were done (their change logs carry the date and reason).

## 4. Why this design

| Decision | Reason | Source |
| --- | --- | --- |
| The backend owns OpenAPI; PortHub holds a generated copy via a paired PR | One owner; the drift test and Spectral guard the source; frontend and mobile read a stable copy. | DEC-13 (Closed 2026-10-29), CT-02 |
| Contract files are generated from code, never edited by hand | The code is the truth; a hand-edited copy drifts. | CT-17, CT-21, `contracts/README.md` |
| Commands default to `base_path('../../contracts/…')` and accept `--output` | The backend lives inside PortHub as a submodule, two folders below the PortHub root; in a worktree that path does not exist, hence `--output` (P1-B6 R-3, D2 progress log). | P1-B6 plan; P2-D2 progress log |
| `GenerateContractsSchema` uses mysqldump on a scratch database | A Laravel schema dump loses CHECK constraints and generated columns, which the design relies on (F05). | CT-17 section 5 |
| `docs/reference` copies instead of reading the shared folder | The repo must be self-contained for CI, cloud sessions and other people's Claude Code; `manifest.json` + `sync-check` detect drift. | `docs/reference/README.md`; TOOLING plan |
| An ID index and split files | Cuts the tokens a session spends on large documents without changing meaning: titles are pointers, the original lines are the source. | TOOLING plan goal 3 |
| Plan files per task | The work can stop and restart in any session without losing decisions. | `CLAUDE.md` workflow, `context-rules` |
| Modes A/B/C/D | The lead's attention goes only where a mistake is costly. | `backend-task-execute` |
| Rejected | Editing the shared documents from the repo; letting skills push or open PRs on their own; one big workflow skill. | TOOLING plan |

## 5. How it connects

- **Used by:** every task (planning, execution, review, PR) and every endpoint/enum/ability/migration change that must be published; Frontend, Mobile, AI and QA read the generated files.
- **Uses:** `RolePermissions`, `Ability`, `Role` (F09); all `Enums/*` (F06); `openapi/port-api.yaml` (F04); migrations and `database/grants/workers.sql` (F05); `docs/reference/split/contracts/CT-17.md` for the version.
- **Module layer:** the commands live in the module that owns the data: Shared (OpenAPI, vocabulary: they read every module's enums by file glob and reflection, not by import), Access (role matrix), AiBridge (schema and worker accounts). `ModuleArchitectureTest` (F01) allows AiBridge to use only Shared; `GenerateContractsSchema` uses only framework classes.
- **Composer scripts:** `contracts:schema`, `contracts:status-vocabulary`, `contracts:openapi`, `contracts:role-matrix` (`composer.json:61-72`), each `@php artisan atlantech:contracts-…`.
- **Worker accounts:** F05 and the `WorkerGrantsTest` (`the worker-accounts command refuses to run without every password`).

## 6. Data

- `GenerateContractsSchema` creates and drops a scratch database (default `atlantech_schema`) on the configured MySQL server; it never writes to the app database except by switching the connection back at the end.
- `CreateWorkerAccounts` writes MySQL users, roles and grants (server-level objects; needs a MySQL account with `CREATE USER` and `GRANT`; the app's `root` in Docker has it).
- The other commands read code and write one file.
- `docs/reference` is files only.

## 7. Tests

| Test file | Tests | What it proves |
| --- | --- | --- |
| `tests/Feature/Console/PublishOpenApiTest.php` | 2 | Copies `openapi/port-api.yaml` byte for byte and creates the `openapi` folder when the contracts folder exists; refuses (command fails, no file) when the contracts folder is missing. Uses a temp folder. |
| `tests/Feature/Console/PublishStatusVocabularyTest.php` | 7 | Lists columns with their enum values (`notification_logs.status` = `SENT, FAILED, ACKNOWLEDGED`, request statuses, recommendation statuses); lists `plan_components.derived_status` under `derived` and not under `columns`; **the generated `columns` equal the database's real ENUM columns and values** (reads `information_schema`, uses MySQL); refuses a column named by two enums; refuses a file in an `Enums` folder that is not an enum (these two tests write a throw-away file into `app/Modules/Shared/Enums/` and delete it in `finally`: do not run two test processes at once on one checkout); same bytes on every run; refuses when the folder is missing. |
| `tests/Feature/Console/PublishRoleMatrixTest.php` | 4 | Every ability is a row and every role a column; the Marine Safety Officer holds none, `MARINE_PILOT` holds `tasks.own.read, tasks.own.update`, `PORT_GM` starts `port-state.read, users.manage`; same file every time; fails when the output folder is missing. |
| `tests/Feature/Database/ContractsSchemaTest.php` (group `schema`) | 3 | `schema.sql` starts with `-- ATLANTECH port database schema (CT-17 v1.0).`, names the last migration (`2026_11_05_100000_create_personal_access_tokens_table` on `9559d35`; `2026_11_03_183535_alter_port_settings_add_min_max` before P1-C3), contains `CREATE TABLE yard_runs`, the CHECK `chk_br_ports`, `GENERATED ALWAYS AS` and `CREATE ROLE IF NOT EXISTS 'r_yard'`, no `AUTO_INCREMENT=n`; the structure part rebuilds a database with 101 tables (92 port tables + framework tables, now including `personal_access_tokens`; 100 before P1-C3) and 129 CHECK constraints; the generator refuses an existing database name (`atlantech_testing`) without touching it; stops when the folder is missing. Needs `mysqldump` or Docker. |
| `tests/Feature/Database/WorkerGrantsTest.php` | (F05) | includes `the worker-accounts command refuses to run without every password` and, since 2026-11-05, `workers.sql carries no demo or placeholder line, because it is published into schema.sql` (it fails on a line matching DEMO or "do not merge"; a `-- DEMO (do not merge)` line had reached `develop` and was removed in PR 57). |
| `tests/Feature/Console/TokenPruneNotScheduledTest.php` | 2 | (not a publishing command) `sanctum:prune-expired` is not scheduled and not named in `routes/console.php`: mobile tokens are never deleted (FR-GL-03) and that command deletes rows by query. |

No test covers: `sync-check.mjs`, `lookup.mjs`, `build-index.mjs` (they are checked by CI-less convention: `backend-pr-prepare` requires `sync-check` to report no stale index), the skills and agents, `PublishOpenApi` when the source file is missing, or that the published PortHub copies are current.

Run: `vendor/bin/pest tests/Feature/Console` and `vendor/bin/pest tests/Feature/Database/ContractsSchemaTest.php`.

## 8. Gotchas and invariants

- **`../../contracts` only exists inside the PortHub checkout.** In a git worktree of the backend (under `project-backend/.claude/worktrees/`) the default path is wrong; always pass `--output` pointing to the PortHub branch's file. The failing tests that triggered this discovery are in the P2-D2 progress log.
- **Never hand-edit** a generated file. Change the code, run the command.
- **Bump the role matrix `VERSION` and the `CHANGE_LOG`** in `PublishRoleMatrix` whenever `RolePermissions` changes; nothing enforces it.
- **A new enum without `table.column` in its PHPDoc breaks `contracts:status-vocabulary`** (and the test that checks the vocabulary against the database). Tag it `@derived` or `@not-stored` when it has no ENUM column.
- **`contracts:schema` drops a database.** It refuses an existing name for that reason. Never pass `--database` with a name of a database you care about. It needs MySQL reachable with the configured user, and either a local `mysqldump` or Docker Compose with the `mysql` service running.
- **`sync-check --apply` changes tracked files** (copies, manifest, index, mirrors). Run it on a branch, review the diff, commit on its own. In report mode it is read-only.
- **Never edit `index/index.json` or `split/*`**: they are generated; `sync-check` fails when they are stale.
- **The `docs/reference` copies can be older than the shared folder.** Today five copies are behind (real report 2026-11-06): `contracts/ATLANTECH_Contracts.md`, `contracts/api-conventions.md`, `contracts/error-codes.md`, `contracts/auth-web.md` and `decisions/ATLANTECH_Decisions.md` (two of them were behind on 2026-11-04; the CT-01 v1.4, error-codes v1.3 and CT-03 v1.1 changes of P1-C3 added three; DEC-59 shows Open here, Closed in the master).
- **`lookup.mjs` prints status from the repo copy**, so a stale copy lies; the `!` warning only catches one pattern.
- **Skills and agents say things that can age.** `schema-auditor` still talks about an integer `version` column (outdated). `openapi-sync` says `info.version` "is 1.0.0 since CT-02"; check the real value in `openapi/port-api.yaml`.
- **Agents are read-only by design** (`tools: Read, Grep, Glob, Bash` and a list of allowed commands in the prompt); the main conversation writes section 10 of a plan from their report.
- **Do not give a skill the right to push or merge.** All of them stop and ask the lead.
- **Tests that write into `app/`** (two in `PublishStatusVocabularyTest`) leave a stray file if the process dies between write and `finally`; delete `app/Modules/Shared/Enums/Zz*.php` if you find one.

## 9. How to extend it

**Add a new published contract file** (for example another generated file): create an Artisan command in the owning module (copy `PublishRoleMatrix`'s pattern: `--output`, default `base_path('../../contracts/<file>')`, folder check, `Wrote` message, deterministic output), register it in the module provider inside `if ($this->app->runningInConsole())`, add a composer script `contracts:<name>`, add a test in `tests/Feature/Console` (same bytes twice; missing folder fails; content checks), add the file to PortHub's `contracts/README.md` table, and add the command to the paired-PR checklist in `backend-pr-prepare` and the relevant skill.

**Publish after a change** (the usual case):

```bash
# in ATLANTECH/, on the backend feature branch; PortHub checked out on its own feature branch above
composer contracts:openapi          # after an endpoint change
composer contracts:status-vocabulary # after a new or changed enum
composer contracts:role-matrix      # after RolePermissions changes (bump VERSION and CHANGE_LOG first)
composer contracts:schema           # after a migration (Docker MySQL running)
```
then commit the file in PortHub, open the PortHub PR into `main`, link both PRs.

**Add a document to `docs/reference`:** put the file in the shared folder, add it to `manifest.json` `files` (path, source, sha1) or let `sync-check` list it as UNTRACKED and add it, run `node docs/reference/sync-check.mjs --apply` on a branch, commit the diff on its own. If it carries IDs, extend `SOURCES` and the parser in `build-index.mjs`.

**Add a skill:** create `.claude/skills/<name>/SKILL.md` with `name` and `description` front matter (the description says when to trigger), keep it short, point to `context-rules`; run `node docs/reference/sync-check.mjs --mirrors` to refresh the copies for other agents (it needs the skill not to be in `boost.json`). **Add an agent:** `.claude/agents/<name>.md` with `name`, `description`, `tools`, `model`, and a list of allowed commands.

**Start a new task:** `node docs/reference/lookup.mjs <ID>`, copy `docs/tasks/_TEMPLATE.md` to `docs/tasks/<ID>.md` (or let `backend-task-plan` do it), set Status Draft.

**Change an approved contract:** use `contract-change`; remember the PortHub rules in 3.11.

## 10. Try it

From `project-backend/ATLANTECH`. Runs of 2026-11-04, repeated on 2026-11-06 where a line says so.

```bash
node docs/reference/sync-check.mjs
```
Real output (tail, 2026-11-06): `Local docs folder: <documents root>`; `In sync: 63  Changed locally: 5  Missing locally: 0  Untracked local docs: 23`; `CHANGED` for `contracts/ATLANTECH_Contracts.md`, `contracts/api-conventions.md`, `contracts/error-codes.md`, `contracts/auth-web.md`, `decisions/ATLANTECH_Decisions.md`; the untracked ones are the guide files, `Z_findings_register.md`, `Mobile_Team_CT-04_v1.2_Handoff.md`, `Code Notes/contracts/db/schema.sql` and the two CT proposal notes. The 2026-11-04 run (before P1-C3) had `In sync: 66  Changed locally: 2  Untracked local docs: 7`, and then the first lines were: `CHANGED contracts/ATLANTECH_Contracts.md`, `CHANGED decisions/ATLANTECH_Decisions.md`; seven `UNTRACKED` entries (the Backend_Guide files, `Code Notes/contracts/db/schema.sql`, `CT-17_Backend_Proposal.md`, `CT-18_Backend_Suggestion.md`); `Index up to date: 918 nodes, 298 split files; every ID in the backend tasks resolves.`; `Copies for other agents (.agents, .junie, AGENTS.md) match.` (exit code 1 because two files changed; no file was changed by this run.)

```bash
node docs/reference/lookup.mjs DEC-57 P2-D4
```
Real: `DEC-57 · decision · Port API status codes, error catalogue and version token`, `owner: Backend Team · status: Closed`, `read: split/decisions/DEC-57.md`, `source: decisions/ATLANTECH_Decisions.md:1508-1525`, links to P2-D4, P1-A2, P1-C1; and the `P2-D4` task node with `team: BE · sprint: 2 · feature: FT-03`.

```powershell
php artisan list | Select-String "atlantech:"
```
Real: `atlantech:contracts-openapi`, `atlantech:contracts-role-matrix`, `atlantech:contracts-schema`, `atlantech:contracts-status-vocabulary`, `atlantech:worker-accounts`.

```powershell
php artisan atlantech:contracts-openapi --output="<scratch>\openapi\port-api.yaml"
php artisan atlantech:contracts-status-vocabulary --output="<scratch>\sv.json"
php artisan atlantech:contracts-role-matrix --output="<scratch>\rm.md"
```
Real: each prints `Wrote <path>.`; comparing them with `PortHub/contracts/openapi/port-api.yaml`, `status-vocabulary.json` and `role-matrix.md` (CR removed) gave `identical=True` for all three. (A scratch folder was used, so nothing in PortHub changed.)

```powershell
$env:DB_DATABASE="atlantech_testing_g4"; $env:DB_SCHEMA_TEST_DATABASE="atlantech_testing_g4"
vendor/bin/pest tests/Feature/Console
vendor/bin/pest tests/Feature/Database/ContractsSchemaTest.php
```
Real: `13 passed (70 assertions)` for the Console folder on 2026-11-04 (on 2026-11-06 the Console folder has 15 tests, with `TokenPruneNotScheduledTest`, together with the Seeders folder `50 passed`); `3 passed (15 assertions)` for the schema test (about 70 s; it created and dropped its own scratch databases `atlantech_schema_test` and `atlantech_schema_rebuild` on the Docker MySQL).

`composer contracts:schema` itself against PortHub: not run (it would write into the PortHub checkout; the test covers the generator with a temp file).

## 11. Open points

- **`PortHub/contracts/db/schema.sql` was stale on 2026-11-04 and is current now (2026-11-06):** its header says `Last migration: 2026_11_05_100000_create_personal_access_tokens_table`, it contains `min_value`, and a fresh `composer contracts:schema` output (a scratch file, with a scratch database `atlantech_schema_g4b` that the command created and dropped) is identical to it. The first header line still says `CT-17 v1.0`; whether a newer approved CT-17 exists is Unverified (the header reads `docs/reference/split/contracts/CT-17.md`, which is a stale copy while `docs/reference` is behind).
- **CT-04 v1.2 is code-complete but not approved:** `DEVICE_REVOKED` is in the code, the OpenAPI file and `error-codes.md` while CT-04 itself is still "Proposed v1.2". The Mobile Team's OK is the open item (F15).
- **Nothing enforces that published files are current.** A CI step could run the three cheap commands to a temp folder and fail on a diff with the PortHub copy; not built.
- **Stale document copies** (finding, five files on 2026-11-06, see gotchas; the first version of this bullet named two): `docs/reference/contracts/ATLANTECH_Contracts.md` and `decisions/ATLANTECH_Decisions.md` differ from the shared `Code Notes` files (DEC-59, CT-18 v1.1). Run `sync-check --apply` on a branch.
- **`schema-auditor` outdated text** about an integer `version` column (see gotchas); `openapi-sync` hard-codes version facts. Not fixed here (tooling files are not mine to edit).
- **Plan statuses not closed out:** `P1-C3.md`, `P1-C4.md`, `P1-C5.md` are "In review" on `develop` although merged (PRs 54 to 57).
- **`TOOLING.md` is "In review" on `develop`**; its PR note said it would open after P1-A1 merged. Whether its PR merged is Unverified (its content is on `develop`; check the plan's progress log or the merged PR list).
- **Untracked local documents** reported by `sync-check`: `CT-17_Backend_Proposal.md`, `CT-18_Backend_Suggestion.md`, `Mobile_Team_CT-04_v1.2_Handoff.md`, `contracts/db/schema.sql` and the guide files in `Code Notes` (23 untracked on 2026-11-06); add to `manifest.json` or `ignore` when decided.
- **No test for `sync-check`, `lookup`, `build-index`.**
- **`CreateWorkerAccounts` has one test** (missing passwords). The create/alter/grant path needs a MySQL account with `CREATE USER`; its success path is not tested (Unverified; what to check: a run on an own empty MySQL with the six variables set, then `SHOW GRANTS FOR w_part1`).
- **README for the commands:** `project-backend/README.md` has no section on the publishing commands (a `grep -i contracts` finds nothing); the commands are documented only in the skills, `composer.json` and this guide.
