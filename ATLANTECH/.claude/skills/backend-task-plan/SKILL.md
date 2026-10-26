---
name: backend-task-plan
description: Plan an ATLANTECH backend task before any code is written. Use when the user names a backend task ID (P1-A1 … P7-T2, or a DEC/CT/BE item owned by the Backend Team) and wants to start it, asks "plan task X", "what does X need", "analyse X", or "خطط التاسك". Reads the task, requirements, contracts, decisions, schema and roadmap; works out what other teams must deliver and what the task affects; asks the open questions; writes docs/tasks/<ID>.md for backend-task-execute and backend-task-review.
---

# Backend task planning

Turns one task ID into an agreed plan file, `docs/tasks/<ID>.md`, built from `docs/tasks/_TEMPLATE.md`. No application code is written in this skill.

The documents are in `docs/reference/`; its `README.md` says which file answers which question and how IDs look in each. Paths below are relative to the Laravel app root (`ATLANTECH/`). Follow the `context-rules` skill for every read: index lookup first, then only the split file or line range it points to.

## 1. Check the documents are current

Run `node docs/reference/sync-check.mjs`.

- Exit 0: continue.
- Exit 1: list the changed files to the user and ask whether to run it with `--apply` (and commit the result on its own) before planning. Plan from the newer files.
- Exit 2: the local documents folder is not on this machine. Say the check was skipped and continue with the repository copies.

If `docs/tasks/<ID>.md` already exists, read it and continue from where it stopped (update it, do not start over).

## 2. Read the task

Run `node docs/reference/lookup.mjs <ID>`. It gives the task's title, phase and package, sprint and feature, its source lines in `backend/atlantech_backend_tasks.md` and `roadmap/roadmap-tasks.json`, and two lists with titles:

- **Waits for** (`→ depends_on`, any team). For each, find its state: a merged task has its plan file at status Done or its code on `develop`; otherwise ask or mark "unknown".
- **Unblocks** (`← unblocks`): every task of any team that waits for this one.

Then read only the lines it points to: the task row, the roadmap entry (plain "done when" in `desc`), the phase goal and exit check (`grep -n "^## Phase"`), and any integration decision `D-nn` listed under `← blocked_by`.

## 3. Collect what the task must satisfy

Look up every ID the task links to (`→ refs`, `→ depends_on`) and every topic it touches (`lookup.mjs --find "<table or status>"`), and read their split files. Tables: `lookup.mjs <table>` gives `split/tables/<table>.md` with the CREATE TABLE and its notes. Read at least:

| Always | When it applies |
| --- | --- |
| Functional requirements for the feature (`FR-…`) | State diagram (`SM-n`) for any status the task changes |
| `Users & Actors.md` for the roles involved | Sequence / activity diagrams for flows that cross Laravel, a worker, web or mobile |
| Contracts (`CT-nn`) the task sends or receives | Class diagram for the area |
| Decisions (`DEC-nn`) on the topic, with their Status | NFRs on performance, audit, security |
| Tables in `database/PortSync.sql` or `SaaS.sql`, plus the edit sheet, DDL fixes and reports A/B | For every `AIBD-nnn` link: its entry in `ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md` (§2.2 details, §4 table ownership, §5 workflow duties, §7 checklist); for every `AI-nnn`: its row in `ai/ATLANTECH_AI_TASKS.md` |
| `backend/Coding_Notes_and_Mapping.md` sections 2–6 | Screens PDF for the pages that call the endpoint |

Then look at the code that exists today (`app/`, `routes/`, `database/migrations/`, `tests/`) so the plan reuses what is there; prefer Boost's `database-schema` and `php artisan route:list` over reading files.

Record every source in section 2 of the plan as `file:lines` (from the lookup), so the review reads exactly the same text.

Follow the **Source of truth** rules in `docs/reference/README.md`. When a source points to a document that is not in `docs/reference/` (or only an older version is), use the Master Roadmap for that point and add a row to section 2 saying which document was missing and what the roadmap says instead. When two present documents disagree, apply the same rules and still list the disagreement in section 7.

## 4. Analyse

Work out and write down:

- **Exactly what is asked** and a **done-when list** where each line can be proven by a test or a short demo. Note what is out of scope and which task owns it.
- **Design:** endpoints (method, path, roles, request, success, every error code from CT-01), tables and columns touched with their owner (CT-15), migrations needed, enums (CT-21), rules to enforce in code, the write-chain steps that apply, the transaction boundary and locks, audit rows, licence-mode behaviour (READ_ONLY / SETUP → 423), and events, notifications or AI jobs, all sent after commit.
- **Other teams:** what this task needs from AI, Frontend, Mobile, UI/UX or QA (contract, data, decision), and what this task changes that they use. List the contract artefacts the PR must update (OpenAPI CT-02, SSE list CT-05, status vocabulary CT-21, `schema.sql` CT-17, mock scenarios CT-19, readiness pack CT-23).
- **Impact:** later backend tasks that build on this one, shared services it creates or changes, behaviour they rely on.
- **Conflicts and risks:** any place where two sources disagree (for example the SQL and a diagram, a contract and a decision), any decision the task needs that is still Open, any dependency not merged. Propose a resolution for each; never pick one silently.
- **Steps:** an ordered list small enough that each is one reviewable change, each with a mode A / B / C / D and a reason. Use the rules in the `backend-task-execute` skill; when unsure, choose the stricter mode.
- **Tests:** the Pest tests that prove each done-when line, including every error code and the licence-mode case.

## 5. Ask before work starts

Put every question in section 8 of the plan, then ask the user, a few at a time, with the AskUserQuestion tool. Always ask about:

- each conflict and each Open decision the task depends on;
- any dependency that is not merged (wait, stub it, or reorder);
- anything the documents leave to choice (naming, defaults, pagination, which role);
- anything from other teams that is not delivered yet (use a mock, a fixture, or wait);
- finally: "Is there anything you know about this task that the documents don't say?"

Write each answer, who gave it and the date into the plan, and update the design and steps to match.

## 6. Finish

Write `docs/tasks/<ID>.md` from the template with every section filled (write "none" rather than leaving a section empty). Set Status to **Ready** only when no question is unanswered and nothing blocks the task; otherwise **Draft**, with the blockers listed at the top.

Tell the user in a few lines: what the task does, what it waits for, what it changes for other teams, the steps with their modes, and anything still open. Offer to start `backend-task-execute`.

Do not write application code, migrations or tests in this skill, and do not commit unless the user asks.
