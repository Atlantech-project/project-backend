---
name: decision-close
description: Prepare and record the closing of an Open ATLANTECH decision (DEC-xx): gather its options and everything it affects, recommend with sources, get the decision from the right owner, write it in all three places of the decisions document, and follow up on affected tasks, contracts and teams. Use when a plan is blocked by an Open decision, before its close-by sprint, or when asked to "close DEC-xx".
---

# Decision close

Every step is mode C: a decision changes what several teams build. Record formats: `reference/record-template.md`. Follow `context-rules`.

## 1. Gather

- `node docs/reference/lookup.mjs DEC-xx`: title, owner, status, close-by sprint, `→ affects`, `→ same_as` (D-nn, OD-nn, AIBD-nnn), and tasks that wait for it (`← unblocks`). Read `split/decisions/DEC-xx.md` (topic, options, recommendation, story) and the split files of what it affects and of its `same_as` IDs.
- If it is already Closed, stop and quote the decision.
- Look for a live proposal on the topic in `docs/reference/proposals/` (context only, never a source).

## 2. Prepare the choice

| Option | What it means for backend tasks | For other teams | Contracts touched | Sources (`file:lines`) |
| --- | --- | --- | --- | --- |

Then a recommendation with reasons, citing sources; keep the document's recommendation unless a source now contradicts it (say which). If the owner is not the Backend Team (AI Team, UI/UX, Leads Council), prepare the backend position only; the owner decides.

## 3. Decide

Ask the lead with AskUserQuestion (options from step 2, recommendation first). When the owner is another team or the Leads Council, the lead brings the position to them and reports the outcome; record only an outcome that the owner agreed.

## 4. Record, in all three places

The decisions document has the same decision in three places; a missed one leaves the index saying Open (as happened with DEC-14 and DEC-15):

1. Summary table row: Status `Closed`.
2. Section table: `Status | Closed`.
3. Section table: `Decision | Closed (<date>): <decision text, with the CT version if a contract carries the detail>`.

The source is PortHub `contracts/ATLANTECH_Decisions.md` (edit on a PortHub branch, PR to `main` after the lead's OK) and the same file in the shared `Code Notes` folder, kept identical. Then `node docs/reference/sync-check.mjs --apply` and confirm `node docs/reference/lookup.mjs DEC-xx` shows `status: Closed` with no `!` warning.

## 5. Follow up

- Tasks it unblocks: tell the lead which can start; update the plans in `docs/tasks/` that cite it (section 7 decided-by, section 8 answers).
- Contracts: if an approved contract must change, run `contract-change`; if a draft contract carries the detail, update it in its CT task.
- Task texts that now contradict the decision (for example a "Blocked: D-nn" note or an old backend position): list them for the lead to update in the backend tasks file.
- Teams in `Affects`: offer `team-handoff`.
