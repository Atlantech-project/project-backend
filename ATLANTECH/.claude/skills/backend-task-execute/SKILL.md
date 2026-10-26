---
name: backend-task-execute
description: Carry out an ATLANTECH backend task from its plan file, deciding step by step what Claude does alone, what the user only skims, what needs the user's approval, and what the user does themselves. Use when the user says "start / implement / execute / continue task X", "نفذ التاسك", or after backend-task-plan finishes. Needs docs/tasks/<ID>.md at status Ready.
---

# Backend task execution

Implements a planned task without spending the user's attention on routine work, and stops for the user exactly where a mistake would be costly.

## 1. Start

1. Read `docs/tasks/<ID>.md`. If it is missing or not **Ready**, run `backend-task-plan` first (or finish its open questions).
2. Run `node docs/reference/sync-check.mjs`. If a source the plan cites changed since the plan was written, tell the user which and re-check the affected steps before coding.
3. Check that every "Waits for" item is still satisfied.
4. Make sure the working tree is clean, then create the branch from the plan (`feature/<task-id>-<slug>`) from `main`. Never work on `main`.
5. Activate `laravel-best-practices` and `testing-best-practices`, and follow `CLAUDE.md` (Boost tools, `php artisan make:` commands, Pint, Pest).

## 2. Agree the modes

Show the steps table from the plan with each step's mode, then ask the user whether to keep or change any mode before starting. Use these rules when setting or checking a mode:

| Mode | Who | Review | Use it for |
| --- | --- | --- | --- |
| **A** | Claude | None; listed in the final summary | Repetitive or mechanical work following a pattern the user already approved in this repository: the second and later CRUD endpoint of the same shape, factories, seeders copied from a spec, Form Request rules copied from the docs, API resources, OpenAPI entries that mirror the code, Pint fixes, tests for straightforward paths |
| **B** | Claude | User skims a short summary after the step; Claude continues unless told otherwise | Standard work with a clear source and no shared impact: an endpoint following an existing pattern, validation taken straight from FR text, read-only queries, notifications using an existing service |
| **C** | Claude drafts | **Stop.** Show the diff summary and the reason; wait for approval before the next step | Migrations, or anything touching a table the AI writes (CT-15/CT-17); any contract artefact (OpenAPI shape, SSE events, status vocabulary, AI payloads, mock behaviour); status transitions, transactions, locks and row versions; the write chain, policies, scopes, licence mode and other security; audit; the first instance of a new pattern; anything resolving a conflict or an Open decision |
| **D** | User | — | Things the user chose to write; decisions or agreements with other teams; anything needing accounts, secrets or servers Claude cannot reach. Claude prepares a checklist and the context, then waits |

When a step needs a fact the plan does not give, look it up following the **Source of truth** rules in `docs/reference/README.md` (missing document → Master Roadmap) and record in the progress log which source was used.

Escalation: if during an A or B step something is unexpected (a source disagrees, a new table or column seems needed, a test fails for a reason outside the step, the change spreads to files outside the plan), stop and treat the step as C. Never lower a mode without the user saying so.

## 3. Do the steps

For each step, in order:

1. Do the change the smallest way that satisfies the plan, reusing existing code.
2. Write or update the tests for it and run the narrowest set (`php artisan test --compact <file or --filter>`).
3. Run `vendor/bin/pint --dirty --format agent`.
4. Commit the step on its own with a message that names the task ID (`P3-G2: add transition service`).
5. Add a line to section 9 (Progress log) of the plan: date, step, result, and any deviation from the plan with its reason.
6. Then, by mode:
   - **A**: continue.
   - **B**: post a two-line summary (what changed, where) and continue.
   - **C**: post what changed, why, the files, and anything the user should look at closely; wait for "ok" or changes.
   - **D**: post the checklist and wait until the user says it is done; then verify it (tests, diff) before moving on.

Keep contract artefacts in the same branch as the code that changes them. If the plan turns out to be wrong, stop, explain, update the plan with the user, then continue.

## 4. Finish

1. Run every test the plan lists, then ask the user to run the full suite (`php artisan test --compact`), as `CLAUDE.md` asks.
2. Check each done-when line in the plan and tick the ones proven; say how each was proven.
3. Set the plan's Status to **In review** and run `backend-task-review`.
4. Summarise for the user: steps done by mode (A ones only listed), what other teams must be told, and anything left.

Do not push, open a PR or merge until the user asks.
