---
name: manual-execution
description: Run a step in mode E of backend-task-execute, where the lead types the code himself and Claude prepares it, explains it under each block and reviews it at the end of the task. Use when a plan step is marked E, or when the lead says "manual", "هكتبها بإيدي", "اديني الكود اكتبه", or asks to type a part himself.
---

# Manual execution (mode E)

Planning and review stay with Claude. In an E step the lead types the code so that he understands the core of the system without having to re-review code Claude wrote alone. Claude prepares the code, gives it in chat with an explanation, and reviews the result at the end of the task.

Use E for core logic he must be able to explain (services, locks, transactions, request rules that encode a business rule, the routes that carry the authorization). Tests, OpenAPI, docs, controllers and other mechanical files stay with Claude.

## Before the first part

1. Prepare and check the whole E group first: write the code in a throwaway worktree of `develop`, make its tests pass and run Pint, so the code the lead types is exactly the code that passed. Delete the throwaway worktree afterwards.
2. Apply the Claude-mode parts of the task and commit them. Say that tests and the OpenAPI drift test fail until he has typed the E files.
3. The lead types in the main checkout, with the task branch checked out there (no second worktree for him). If a worktree was used, move the work to the main checkout first.
4. Check that MySQL is reachable (port 3306, Docker) before any test run, otherwise the run hangs. Use a private test database (`DB_DATABASE` and `DB_SCHEMA_TEST_DATABASE`) and run the full suite once with no override at the end.

## Sending a part

- Send ONE part at a time. A small file comes whole; a big file comes in chunks in writing order, each with the file name and the place in the file. After each part, wait for the lead to say he finished.
- The command that creates the file is part of the answer and its path must be right: `php artisan make:class Modules/...` starts from `app/`; `make:request` and `make:controller` start from `app/Http/...`, so use `make:class` for files under `app/Modules`.
- Clean code with no extra inline comments (docblocks are fine).
- The explanation goes UNDER each block as a numbered list, anchored on the real code: file path, function, numbered lines. Not a narrative. English by default; if he writes "اشرح بالعربي", explain that part in Arabic.
- For EVERY file or class, say whether it already EXISTS on `develop` or is NEW in this task. Never present a future file as existing.
- Examples and scenarios are in Egyptian Arabic with English technical terms. Every Arabic line that starts with an English word or a backticked identifier begins with an RLM mark (U+200F).
- No quizzes or exercises. No markdown tables in chat (they render badly for him): use lists.
- Do not re-teach what he already studied.
- Tell him in two lines what each critical test proves, so he can explain it if asked.
- End every part with one line: if anything is still unclear, ask for the `code-explanation` skill ("اشرح لي الكود" and the route, command or service, for example `POST /users/{user}/deactivate`). It explains one feature as a full call chain in right-to-left Egyptian Arabic, with the real inputs, outputs, data written and failure branches at each step. It is useful after typing a service to see where it sits in the request, to prepare for a review where he must explain the code, and to onboard a teammate. It only explains; it does not plan, review or change code.

## Review at the end

- Compare his files with the prepared version and explain each real difference. A behaviour difference is a defect to fix; formatting is left to Pint. The comparison is not optional: in P2-E1 it caught a typed `generatePassword()` with a wrong length, an off-by-one and `rand` instead of `random_int`, which Pint cannot fix.
- Renames he makes on purpose are accepted and recorded in the task's progress log.
- Then run the tests and `backend-task-review`.
- The final review of security-sensitive or concurrency code is done by a stronger model (Opus), either after the lead switches the model or in a read-only side agent; tell him first.

## Pace

When he understands the pattern he may hand a mechanical part (OpenAPI, a thin controller) to Claude mid-task; Claude does it without asking again.

## Git

Commit his files after he finishes the whole group (or after the final review), in small commits by part. Push and open PRs only after he says so; then Claude pushes, opens the PRs and gives the links; the lead merges.
