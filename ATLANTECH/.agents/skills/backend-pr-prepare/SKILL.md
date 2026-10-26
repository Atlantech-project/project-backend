---
name: backend-pr-prepare
description: Prepare the pull request for a reviewed ATLANTECH backend task: check it is ready, write the PR description from the plan and the review, and, only after the lead says so, push the branch and open the PR into develop. Use when the user says "prepare the PR", "open the PR for X", "جهز الـ PR", or after backend-task-review returns Ready for PR.
---

# Backend PR preparation

## 1. Check it is ready

Stop and say what is missing if any of these fails:

- `docs/tasks/<ID>.md` exists, Status **In review**, section 10 ends with verdict **Ready for PR**, and no commit came after that review (`git log` after the review commit). If code changed since, run `backend-task-review` again first.
- Working tree clean; branch is `feature/<task-id>-<slug>`; `git fetch` and the branch contains `origin/develop` (otherwise ask whether to merge or rebase develop in first).
- `node docs/reference/sync-check.mjs` reports no stale index or mirrors.
- The plan's tests pass (`php artisan test --compact` for the listed files); ask the lead to confirm the full suite ran.

## 2. Write the description

Fill `reference/pr-template.md` from the plan: title `<ID>: <task title>`; what it does (section 1), each done-when line with how it was proven, notable deviations from the progress log, contract artefacts updated (section 3 / 5), other teams affected (section 5 "this task changes…"), the review verdict and remaining Notes, test results. Link the plan file. Keep it short; the plan holds the detail. End with the attribution line your Claude Code session is configured to add to PR descriptions, if any.

Show the description to the lead.

## 3. Push and open (only after the lead's OK)

- Push: `git push -u origin <branch>`.
- Open the PR into **develop**: with GitHub CLI `gh pr create --base develop --title "…" --body-file <file>`; without it, give the lead the compare link `https://github.com/<owner>/<repo>/compare/develop...<branch>?expand=1` and the description to paste.
- If section 5 lists other teams, offer `team-handoff` to draft their message.

## 4. After merge

When the lead says it is merged: set the plan's Status to **Done**, add the merge date to the progress log, remind the lead to update the progress tracker, and list the tasks it unblocks (`node docs/reference/lookup.mjs <ID>`, `← unblocks`). Never merge yourself.
