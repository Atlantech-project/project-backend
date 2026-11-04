# A · Orientation

Read this before any feature file. It gives the map: what the product is, how the repositories fit, where things live, the vocabulary used everywhere, and how work flows. Facts here were checked against `develop` at `106b40e` (Sprint 2 merged) and PortHub `main` on 2026-11-04.

## A1. What the system is (in one page)

ATLANTECH is a decision-support system for one seaport. It sits above the systems a port already runs and recommends: when a vessel can safely enter, which berth, tugs and pilot to use, and where to place containers in the yard. **A person always confirms.** ATLANTECH recommends and coordinates; the port's own systems execute.

A vessel's journey, as the backend models it:

```
SUBMITTED -> PRELIMINARY_APPROVED -> CUSTOMS_APPROVED
  -> AI Part 1 proposes safe entry windows
  -> ENTRY_WINDOW_CONFIRMED   (a person signs off)
  -> AI Part 2 proposes berth, tugs, pilot
  -> RECOMMENDATION_GENERATED
  -> PLAN_CONFIRMED           (a person signs off again)
  -> COMPLETED
  -> AI Part 3 compares the plan with what happened
```

**Who uses the backend.** The Vue web dashboard (office staff), the Flutter mobile app (pilots, tug masters and terminal supervisors, offline-first), and Python AI workers (they read and write MySQL and take jobs from Redis queues). The backend is the only thing the web and mobile apps talk to.

**One installation per port.** Each port runs its own full copy on its own hardware. A separate central SaaS keeps only licences and the port registry; no operational data leaves the port.

## A2. The repositories

| Repo | What it is | Where |
| --- | --- | --- |
| **PortHub** | The umbrella repository. Holds the shared **contracts**, the decision log, the brand kit, the tracker app, and one **git submodule pointer** per component. | the PortHub repository root |
| **project-backend** | The Laravel app (this guide). The Laravel root is the `ATLANTECH/` folder inside it. | `PortHub/project-backend/` (a submodule) |
| project-ai, project-frontend, project-mobile | The other components (not covered here). | `PortHub/project-*/` |

**The pointer rule.** PortHub does not store the backend's files. It stores one commit hash for `project-backend`. So there are two steps: (1) a backend PR merges into the backend's `develop`; (2) later, one PortHub PR moves the pointer to the new `develop` commit. Moving the pointer before the backend PR merges would point PortHub at a commit that does not exist on `develop`. Sprint 2 ended with a single pointer PR for six backend PRs.

**Why contracts live in PortHub, not in the backend.** Teams that build against the backend (web, mobile, AI, QA) read `PortHub/contracts/`. The backend produces several of those files from its own code (OpenAPI, `status-vocabulary.json`, `role-matrix.md`, `schema.sql`), so the code stays the source of truth and the published file is a generated copy. That is why some backend PRs come with a **paired PortHub PR**.

**Two checkouts of the same repo.** Inside `PortHub/project-backend/` the backend is a normal git repository (its history lives in `PortHub/.git/modules/project-backend`). Extra working copies for tasks ("worktrees") are created under `project-backend/.claude/worktrees/`.

## A3. Map of the backend repo

Paths are inside `project-backend/` unless stated.

| Path | What is there |
| --- | --- |
| `ATLANTECH/app/Modules/<Module>/` | All domain code, one folder per module (see A4). Inside: `Models/`, `Enums/`, `Http/{Controllers,Middleware,Requests,Resources}`, `Services/`, `Console/`, and a `<Module>ServiceProvider.php`. |
| `ATLANTECH/app/Providers/AppServiceProvider.php`, `app/Http/Controllers/Controller.php` | Laravel's defaults; almost empty. |
| `ATLANTECH/bootstrap/app.php` | The application's wiring: routing prefix, the `port.api` middleware group, the middleware priority list, the exception renderer. **Read it early.** |
| `ATLANTECH/bootstrap/providers.php` | The list of service providers, one per module. |
| `ATLANTECH/routes/{api,web,console}.php` | API routes (served under `/api/v1`), the web stub, console schedule. |
| `ATLANTECH/config/` | Laravel config plus project keys: `seeding.php`, `sanctum.php`, `session.php`, `cors.php`. |
| `ATLANTECH/database/migrations/` | 97 migrations (3 Laravel framework tables, 92 port tables, 2 later changes). |
| `ATLANTECH/database/{factories,seeders,grants}` | One factory per model (92), the seeders and `data/*.json`, and `grants/workers.sql` (database accounts for the AI workers). |
| `ATLANTECH/lang/en/` | Error message texts (`errors.php`). |
| `ATLANTECH/openapi/port-api.yaml` | The OpenAPI contract of the port API. |
| `ATLANTECH/tests/` | 45 test files: `Feature/{Access,Api,Audit,Console,Database,Models,Seeders}`, `Unit/Modules/…`, `Pest.php`, `TestCase.php`. |
| `ATLANTECH/docs/reference/` | Copies of the project documents the code is built from (requirements, contracts, decisions, UML, roadmap) plus an index and lookup tool. |
| `ATLANTECH/docs/tasks/` | One plan file per task (`P2-D1.md` …): the task's memory (plan, answers, progress log, review). |
| `ATLANTECH/.claude/{skills,agents}` | The team's Claude Code skills and agents (see A8). |
| `ATLANTECH/CLAUDE.md`, `AGENTS.md` | Instructions Claude Code reads automatically: Laravel Boost rules plus the ATLANTECH workflow section. |
| `ATLANTECH/{docker-compose.yml,Dockerfile,docker/}` | Local and server runtime (feature F01). |
| `.github/` (repo root, not under `ATLANTECH/`) | CI workflows, labeler, `CODEOWNERS`, PR template, issue templates (feature F02). |

Stack (from `composer.json` and the README): Laravel 13, PHP 8.4 in CI and in the container (`composer.json` allows `^8.3`), Sanctum 4, Pest 5, Pint, MySQL 8.4, Redis 7.4. The README badge says PHP 8.3; CI and the docs say 8.4, so assume 8.4.

## A4. The modules and the layer rule

`app/Modules/` has 15 modules. They form **layers**; a module may use code from earlier layers (and the two integration modules may use only Shared), never from its own layer or later ones. `tests/Unit/Modules/ModuleArchitectureTest.php` enforces this with Pest architecture tests.

| Layer | Module(s) | Holds |
| --- | --- | --- |
| 1 | **Shared** | The shared kernel: errors, pagination, audit, the version token, shared enums. Depends on nothing. |
| 2 | **Access** | Users, roles, devices, sign-in, abilities, scopes. |
| 3 | **License** | Licence state and the write-gate middleware. |
| 4 | **MasterData** | Port profile, terminals, berths, tugs, pilots, vessels, storage facilities and other reference data (38 models). |
| 5 | **Environment**, **Berthing** (peers: they do not use each other) | Weather and tide data; berthing requests. |
| 6 | **Baplie** | Vessel load files and their parsing issues. |
| 7 | **Recommendation** | AI recommendations and their review. |
| 8 | **Plan** | Operational plans and plan components. |
| 9 | **Yard**, **Dispatch** (peers) | Container-yard placement; dispatching tasks. |
| 10 | **Execution** | Actual status updates in the field. |
| 11 | **Learning** | Drift, evaluation and retraining records. |
| integration | **AiBridge**, **Realtime** | The link to the AI workers (database accounts, schema publishing) and realtime delivery. They may use only Shared. |

A last test checks that every folder under `app/Modules/` appears in these rules, so a new module cannot be added without placing it.

**Consequence you will meet.** `Access` cannot read a `Plan` table, so "field users see only their own tasks" is implemented as a trait in Access that the **Plan** model uses (Plan is a later layer and may use Access). Rules that need `terminals` (a MasterData table) live in the endpoint, not in Access. Both come from this rule.

## A5. Vocabulary used everywhere

| Term | Meaning |
| --- | --- |
| **CT-nn** | A team **contract** (CT-01 to CT-26): an agreement between two teams on a shape, owner and reviewers. Text is in `PortHub/contracts/ATLANTECH_Contracts.md`; some have their own file. Approved ones change only by a controlled process (skill `contract-change`). Examples: CT-01 API conventions, CT-02 OpenAPI, CT-03 web sign-in, CT-04 mobile sign-in, CT-15 table ownership, CT-17 schema, CT-18 seed data, CT-21 status vocabulary. |
| **DEC-nn** | A recorded **decision** (`PortHub/contracts/ATLANTECH_Decisions.md`), with an owner, a status (Open or Closed) and a close-by sprint. Examples: DEC-35 sign-in identifier is `username`; DEC-36 one active role per user; DEC-57 status codes and the version token; DEC-59 `port_settings` min and max. |
| **FR-…, NFR-…** | Functional and non-functional **requirements**, for example FR-GL-01 (the request check order), NFR-SEC-2 (RBAC). In `docs/reference/requirements/`. |
| **AIBD-nnn, AI-nnn, OD-nn** | The AI team's dependency items on the backend, its tasks, and open integration decisions. |
| **FT-nn** | A **feature** of the roadmap (FT-01 platform, FT-02 sign-in, FT-03 access and audit, …). |
| **P2-D1-style ids** | **Backend tasks**: `P<phase>-<package><n>`. The phase is the project phase, the letter is the package inside it. Other teams use `FE-nnn`, `MB-…`, `AI-nnn`, `UX-…`, `QA-…`. |
| **Sprint** | A roadmap period. Sprint 2 (finished) was sign-in, access control and audit. |
| **ai-impact** | A PR label added automatically when a PR touches migrations or worker grants, because AI workers read and write those tables. The AI lead reviews such PRs. |
| **Modes A, B, C, D** | How a task step is run: A Claude alone, B Claude and the lead skims, C Claude drafts and **stops for approval**, D the lead does it. |
| **Write chain** | The fixed order of checks every write request passes (FR-GL-01); feature F11. |
| **Version token** | The record's `updated_at` (with microseconds), used to detect a lost update (409); feature F12. |
| **Ability / Role / Scope** | What a role may do, the 12 port roles, and the extra limits (terminal, own tasks); feature F09. |
| **REJECTED_ATTEMPT** | An audit-log action recording a request that was refused (401, 403, 409, 422), including who tried. |
| **Port Admin** | The role code `PORT_GM`, the port's application administrator. |
| **Terminal-scoped roles** | `TERMINAL_ADMIN`, `TERMINAL_OFFICER`, `TERMINAL_SUPERVISOR`: tied to one terminal. |
| **Field roles** | `MARINE_PILOT`, `TUG_MASTER`, `TERMINAL_SUPERVISOR`: they work from the mobile app. |
| **Problem response** | The JSON error format (RFC 9457) every API error uses (feature F03). |

## A6. Where an answer lives (use before guessing)

The backend never decides silently. Order of authority (from `docs/reference/README.md`):

1. An **approved contract** or a **Closed decision** wins on what it settles.
2. Otherwise the **Master Roadmap** wins on scope, timing and dependencies; team documents add the detail.
3. Text marked **PROPOSED** never overrides anything.
4. If nothing covers the point, it is an open question for the lead, not a choice to make alone.

Tools in `ATLANTECH/docs/reference/`:

```bash
node docs/reference/sync-check.mjs             # are these copies current with the shared documents folder?
node docs/reference/lookup.mjs P2-D1           # one id: title, owner, status, source lines, links
node docs/reference/lookup.mjs DEC-59          # a decision (status, what it affects)
node docs/reference/lookup.mjs part1_run       # a table
node docs/reference/lookup.mjs --find "version"
```

The shared documents folder (`Code Notes`) holds the master copies; `docs/reference/` is a copy inside the repo, and `manifest.json` records where each file came from. The code itself is the final source of truth for behaviour; documents explain intent.

## A7. How work flows (daily workflow)

1. **Branch** `feature/<task-id>-<slug>` from `origin/develop`. All PRs target **`develop`**, never `main`.
2. **Plan first.** Each task has a plan file `docs/tasks/<ID>.md` (status Draft → Ready → In progress → In review → Done) with sources, design, steps with modes, tests, open questions with the lead's answers, a progress log and the review.
3. **Implement in steps**, one commit per step, running the narrow tests as you go and **Pint** on dirty files.
4. **CI on every PR into `develop`** runs four checks: `pint` (style), `tests` (full Pest suite on MySQL and Redis), `openapi` (Spectral lint of `port-api.yaml`) and `labeler` (adds `ai-impact`). A PR also needs one approval; the lead merges. Never push, open a PR or merge without the lead's OK.
5. **Contracts travel with code.** An endpoint change updates `openapi/port-api.yaml` (minor version bump) in the same PR and opens a paired PortHub PR (`composer contracts:openapi`). Schema changes publish `schema.sql` (`composer contracts:schema`). New enums regenerate `status-vocabulary.json`.
6. **Close the task**: set the plan status to Done and note the merged PR number; the PortHub pointer moves after the batch merges.
7. **Team review rule.** Backend is the source of truth for CT-01 and CT-02. Frontend and Mobile review *critical* changes only (breaking changes or changes to a rule they build on). QA tests; it does not review.

**Running tests safely.** The test database is hard-coded as `atlantech_testing` in `phpunit.xml` and `tests/Pest.php`. Two test runs against the same MySQL at once overwrite each other's tables. If you run in parallel (several worktrees or sessions), give each its own empty database and set both `DB_DATABASE` and `DB_SCHEMA_TEST_DATABASE` in your shell. Never drop or `migrate:fresh` a database that is not empty or not a test database. Docker must be running or the suite hangs; the full suite takes several minutes.

## A8. The team's tooling in the repo

`ATLANTECH/.claude/skills` (instructions Claude Code loads):

| Skill | Use it for |
| --- | --- |
| `backend-task-plan`, `backend-task-execute`, `backend-task-review` | The three-step task workflow. |
| `backend-pr-prepare` | Checking a reviewed task and writing the PR description. |
| `context-rules`, `infer-conventions` | How to read the documents and code cheaply; how to infer conventions. |
| `contract-change`, `decision-close` | Changing an approved contract; closing an Open decision. |
| `openapi-sync` | Keeping `port-api.yaml` equal to the real routes. |
| `api-readiness-pack`, `team-handoff` | Readiness pack for a feature; the message to another team. |
| `laravel-best-practices`, `testing-best-practices`, `tailwindcss-development`, `deploying-to-cloud` | Generic Laravel guidance (the last two are rarely used here). |

`ATLANTECH/.claude/agents`: `docs-navigator` (reads large documents), `task-reviewer`, `schema-auditor`, `contract-checker`.

## A9. Reading order for the feature files

`F01` runtime → `F02` CI → `F03` API base → `F04` OpenAPI → `F05` database → `F06` models → `F07` sign-in → `F08` password gate → `F09` roles and scope → `F10` audit → `F11` write chain → `F12` version token → `F13` seeders → `F14` tooling → `F15` what is not built. Each file follows the fixed shape listed in `00_INDEX.md` section 2.

## A10. Try it (orientation)

```bash
# 1. get the code with its submodules
git clone --recurse-submodules https://github.com/Atlantech-project/PortHub.git
cd PortHub/project-backend/ATLANTECH

# 2. start MySQL and Redis
docker compose up -d

# 3. prove the module layer rule
vendor/bin/pest tests/Unit/Modules/ModuleArchitectureTest.php

# 4. find a decision and a task in the documents
node docs/reference/lookup.mjs DEC-36
node docs/reference/lookup.mjs P2-D1
```

Expected: the architecture tests pass; `DEC-36` shows `status: Closed` and the task it affects; `P2-D1` shows its sprint, feature and what it unblocks.
